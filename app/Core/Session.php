<?php

namespace App\Core;

class Session
{
    private const IDLE_TIMEOUT = 1800; // 30 minutos de inatividade

    /**
     * O nome mudou de 'stoqueonline_sess' de proposito. A versao antiga
     * prendia o cookie ao caminho da instalacao; quem ja tinha visitado o
     * site ficava com dois cookies de mesmo nome e caminhos diferentes, e o
     * navegador manda os dois. O PHP le o primeiro da lista - o antigo, que
     * nao vale mais - e a sessao nunca colava: o login dava certo e a tela
     * seguinte aparecia deslogada. Nome novo, sombra impossivel.
     */
    private const COOKIE_NAME = 'stoque_sessao';
    private const LEGACY_COOKIE_NAME = 'stoqueonline_sess';

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = self::isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        self::dropLegacyCookie();

        session_name(self::COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Expiração por inatividade
        if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity'] > self::IDLE_TIMEOUT)) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'] = 'Sua sessão expirou por inatividade. Entre novamente.';
        }

        // Rotação periódica do ID da sessão (mitiga session fixation)
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        } elseif (time() - (int) $_SESSION['_created_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Expira o cookie da versao anterior em todos os caminhos onde ele pode
     * ter sido gravado, para ele parar de viajar junto em cada requisicao.
     */
    private static function dropLegacyCookie(): void
    {
        if (!isset($_COOKIE[self::LEGACY_COOKIE_NAME]) || headers_sent()) {
            return;
        }

        $caminhos = ['/'];
        $base = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');
        if ($base !== '') {
            $caminhos[] = $base . '/';
            $caminhos[] = $base;
        }
        $atual = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($atual !== '') {
            $caminhos[] = $atual . '/';
            $caminhos[] = $atual;
        }

        foreach (array_unique($caminhos) as $caminho) {
            setcookie(self::LEGACY_COOKIE_NAME, '', [
                'expires'  => time() - 42000,
                'path'     => $caminho,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        unset($_COOKIE[self::LEGACY_COOKIE_NAME]);
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        // Atrás de proxy/CDN (Cloudflare, load balancer da hospedagem)
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * O cookie vale para todo o domínio.
     *
     * Prender o cookie ao caminho de APP_URL quebrava o login quando o
     * mesmo site é aberto pela raiz e por /public (o navegador deixa de
     * enviar o cookie quando o caminho não bate). O nome próprio da sessão
     * já evita conflito com outros sistemas na mesma máquina.
     */
    private static function cookiePath(): string
    {
        return '/';
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires'  => time() - 42000,
                        'path'     => $params['path'],
                        'domain'   => $params['domain'],
                        'secure'   => $params['secure'],
                        'httponly' => $params['httponly'],
                        'samesite' => $params['samesite'] ?? 'Lax',
                    ]
                );
            }
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
    }

    /**
     * IP do cliente. Cabeçalhos de proxy só são considerados quando a
     * aplicação declara explicitamente que está atrás de um proxy confiável,
     * pois qualquer visitante pode forjar X-Forwarded-For.
     */
    public static function getClientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (!env('TRUST_PROXY', false)) {
            return substr($remote, 0, 45);
        }

        $candidates = [];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // O primeiro endereço da lista é o cliente original
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidates[] = trim($parts[0]);
        }

        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return substr($candidate, 0, 45);
            }
        }

        return substr($remote, 0, 45);
    }

    public static function getUserAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 500);
    }

    public static function detectOS(?string $userAgent = null): string
    {
        $ua = $userAgent ?? self::getUserAgent();
        return match (true) {
            (bool) preg_match('/windows|win32/i', $ua) => 'Windows',
            (bool) preg_match('/macintosh|mac os x/i', $ua) => 'Mac OS',
            (bool) preg_match('/android/i', $ua) => 'Android',
            (bool) preg_match('/iphone|ipad|ipod/i', $ua) => 'iOS',
            (bool) preg_match('/linux/i', $ua) => 'Linux',
            default => 'Outro',
        };
    }

    public static function detectBrowser(?string $userAgent = null): string
    {
        $ua = $userAgent ?? self::getUserAgent();
        return match (true) {
            (bool) preg_match('/edg/i', $ua) => 'Microsoft Edge',
            (bool) preg_match('/opera|opr/i', $ua) => 'Opera',
            (bool) preg_match('/chrome|crios/i', $ua) => 'Google Chrome',
            (bool) preg_match('/firefox|fxios/i', $ua) => 'Mozilla Firefox',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            default => 'Outro',
        };
    }
}
