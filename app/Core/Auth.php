<?php

namespace App\Core;

class Auth
{
    /** Cache por requisição: evita repetir os mesmos SELECTs a cada chamada */
    private static ?array $cachedUser = null;
    private static bool $userLoaded = false;
    private static ?bool $cachedLicense = null;

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function user(): ?array
    {
        if (self::$userLoaded) {
            return self::$cachedUser;
        }

        self::$userLoaded = true;
        $id = self::id();

        if (!$id) {
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = Database::fetch(
            "SELECT u.*, c.trading_name, c.company_name, c.whatsapp, c.phone as company_phone
             FROM users u
             LEFT JOIN companies c ON c.user_id = u.id
             WHERE u.id = :id LIMIT 1",
            ['id' => $id]
        );

        // Conta removida ou bloqueada durante a sessão: derruba o acesso.
        // O caso de remoção importa porque o admin pode excluir uma conta
        // enquanto a pessoa navega: sem isso a sessão sobrevive sem dono,
        // a barra continua mostrando 'Sair' e qualquer tela que leia o nome
        // do usuário quebra.
        if (!self::$cachedUser || empty(self::$cachedUser['allowed_access'])) {
            Session::destroy();
            self::$cachedUser = null;
        }

        return self::$cachedUser;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user && !empty($user['is_admin']);
    }

    public static function hasActiveLicense(): bool
    {
        if (self::$cachedLicense !== null) {
            return self::$cachedLicense;
        }

        $user = self::user();
        if (!$user) {
            return self::$cachedLicense = false;
        }

        // Administradores sempre têm acesso liberado
        if (!empty($user['is_admin'])) {
            return self::$cachedLicense = true;
        }

        $today = date('Y-m-d');

        // 1. Licença própria ativa
        $directLicense = Database::fetch(
            "SELECT id FROM user_licenses
             WHERE user_id = :user_id
               AND status = 1
               AND is_closed = 0
               AND start_date <= :today_start
               AND end_date >= :today_end
             LIMIT 1",
            ['user_id' => $user['id'], 'today_start' => $today, 'today_end' => $today]
        );

        if ($directLicense) {
            return self::$cachedLicense = true;
        }

        // 2. Colaborador incluído na licença de uma empresa
        $colleagueLicense = Database::fetch(
            "SELECT ul.id
             FROM contacts c
             JOIN user_licenses ul ON ul.user_id = c.user_id
             WHERE c.email = :email
               AND c.include_in_license = 1
               AND ul.status = 1
               AND ul.is_closed = 0
               AND ul.start_date <= :today_start
               AND ul.end_date >= :today_end
             LIMIT 1",
            ['email' => $user['email'], 'today_start' => $today, 'today_end' => $today]
        );

        return self::$cachedLicense = (bool) $colleagueLicense;
    }

    /**
     * Confere a senha aceitando o formato legado da v1 (MD5) e, quando for o
     * caso, regrava o hash em bcrypt de forma transparente para o usuário.
     */
    public static function verifyAndUpgradePassword(array $user, string $plainPassword): bool
    {
        $stored = (string) ($user['password'] ?? '');
        if ($stored === '') {
            return false;
        }

        // Formato atual (bcrypt/argon)
        if (str_starts_with($stored, '$')) {
            if (!password_verify($plainPassword, $stored)) {
                return false;
            }

            // Reforça o hash caso o algoritmo padrão do PHP tenha mudado
            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                self::storeNewHash((int) $user['id'], $plainPassword);
            }

            return true;
        }

        // Formato legado da v1: MD5 sem salt (32 caracteres hexadecimais)
        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            if (!hash_equals(strtolower($stored), md5($plainPassword))) {
                return false;
            }

            // Senha correta: migra para bcrypt neste exato momento
            self::storeNewHash((int) $user['id'], $plainPassword);
            self::logAudit((int) $user['id'], $user['email'] ?? null, 'password_upgraded');

            return true;
        }

        return false;
    }

    private static function storeNewHash(int $userId, string $plainPassword): void
    {
        Database::update(
            'users',
            ['password' => password_hash($plainPassword, PASSWORD_DEFAULT)],
            'id = :id',
            ['id' => $userId]
        );
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_email', $user['email']);
        Session::set('is_admin', (bool) $user['is_admin']);

        self::flushCache();

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $user['id']]);
        self::logAudit((int) $user['id'], $user['email'], 'login');
    }

    public static function logout(): void
    {
        $id = self::id();
        $user = self::user();
        if ($id && $user) {
            self::logAudit($id, $user['email'], 'logout');
        }

        self::flushCache();
        Session::destroy();
    }

    public static function flushCache(): void
    {
        self::$cachedUser = null;
        self::$userLoaded = false;
        self::$cachedLicense = null;
    }

    public static function logAudit(?int $userId, ?string $email, string $action): void
    {
        try {
            Database::insert('user_logs', [
                'user_id'    => $userId,
                'email'      => $email,
                'action'     => $action,
                'ip_address' => Session::getClientIp(),
                'user_agent' => Session::getUserAgent(),
                'os'         => Session::detectOS(),
                'browser'    => Session::detectBrowser(),
            ]);
        } catch (\Throwable) {
            // Não quebra a requisição caso falhe o log de auditoria
        }
    }
}
