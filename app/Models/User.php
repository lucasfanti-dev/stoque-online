<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE id = :id LIMIT 1", ['id' => $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
    }

    /**
     * Token de ativação válido (não expirado e de conta ainda não ativada).
     */
    public static function findByActivationToken(string $token): ?array
    {
        return Database::fetch(
            "SELECT * FROM users
             WHERE token = :token
               AND confirmed_activation = 0
               AND (token_expires_at IS NULL OR token_expires_at >= NOW())
             LIMIT 1",
            ['token' => $token]
        );
    }

    /**
     * Token de redefinição de senha válido (guardado como hash sha256).
     */
    public static function findByResetToken(string $hashedToken): ?array
    {
        return Database::fetch(
            "SELECT * FROM users
             WHERE password_reset_token = :token
               AND password_reset_expires IS NOT NULL
               AND password_reset_expires >= NOW()
             LIMIT 1",
            ['token' => $hashedToken]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('users', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::update('users', $data, 'id = :id', ['id' => $id]);
    }

    public static function getAll(string $search = ''): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE (u.name LIKE :search OR u.email LIKE :search2 OR c.trading_name LIKE :search3 OR c.cnpj LIKE :search4)";
            $like = '%' . $search . '%';
            $params = ['search' => $like, 'search2' => $like, 'search3' => $like, 'search4' => $like];
        }

        return Database::fetchAll(
            "SELECT u.*, c.trading_name, c.company_name, c.cnpj, c.phone as company_phone,
                    (SELECT COUNT(*) FROM inventory WHERE user_id = u.id) as total_stock_items,
                    (SELECT p.name FROM plans p
                       JOIN user_licenses ul ON ul.plan_id = p.id
                      WHERE ul.user_id = u.id
                        AND ul.status = 1
                        AND ul.is_closed = 0
                        AND ul.end_date >= CURDATE()
                      ORDER BY ul.end_date DESC LIMIT 1) as active_plan,
                    (SELECT ul.end_date FROM user_licenses ul
                      WHERE ul.user_id = u.id
                        AND ul.status = 1
                        AND ul.is_closed = 0
                        AND ul.end_date >= CURDATE()
                      ORDER BY ul.end_date DESC LIMIT 1) as license_end_date
             FROM users u
             LEFT JOIN companies c ON c.user_id = u.id
             {$where}
             ORDER BY u.id DESC",
            $params
        );
    }

    /**
     * Contas com pagamento registrado não podem ser apagadas: isso levaria
     * junto o histórico financeiro (a chave estrangeira é em cascata).
     */
    public static function hasPayments(int $id): bool
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM payments WHERE user_id = :id",
            ['id' => $id],
            0
        ) > 0;
    }

    public static function delete(int $id): int
    {
        return Database::delete('users', 'id = :id', ['id' => $id]);
    }

    /**
     * Impede que o sistema fique sem nenhum administrador ativo.
     */
    public static function countOtherAdmins(int $exceptUserId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM users WHERE is_admin = 1 AND allowed_access = 1 AND id <> :id",
            ['id' => $exceptUserId],
            0
        );
    }
}
