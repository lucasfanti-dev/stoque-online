<?php

namespace App\Core;

/**
 * Controle de tentativas de login (proteção contra força bruta).
 *
 * Bloqueia por e-mail e por IP em janelas independentes, de modo que um
 * atacante não consiga travar a conta de terceiros apenas errando a senha:
 * o bloqueio por IP é mais curto e o por e-mail exige muitas tentativas.
 */
class RateLimiter
{
    private const WINDOW_MINUTES   = 15;
    private const MAX_PER_EMAIL    = 8;
    private const MAX_PER_IP       = 20;

    public static function tooManyAttempts(string $email): bool
    {
        return self::attemptsByEmail($email) >= self::MAX_PER_EMAIL
            || self::attemptsByIp() >= self::MAX_PER_IP;
    }

    public static function attemptsByEmail(string $email): int
    {
        if ($email === '') {
            return 0;
        }

        $row = Database::fetch(
            "SELECT COUNT(*) AS total FROM login_attempts
             WHERE email = :email
               AND successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE)",
            ['email' => $email]
        );

        return (int) ($row['total'] ?? 0);
    }

    public static function attemptsByIp(): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total FROM login_attempts
             WHERE ip_address = :ip
               AND successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE)",
            ['ip' => Session::getClientIp()]
        );

        return (int) ($row['total'] ?? 0);
    }

    public static function record(string $email, bool $successful): void
    {
        try {
            Database::insert('login_attempts', [
                'email'      => $email !== '' ? $email : null,
                'ip_address' => Session::getClientIp(),
                'successful' => $successful ? 1 : 0,
            ]);
        } catch (\Throwable) {
            // Nunca impedir o login por falha no registro da tentativa
        }
    }

    /**
     * Zera o contador do e-mail após um login bem-sucedido.
     */
    public static function clear(string $email): void
    {
        try {
            Database::delete(
                'login_attempts',
                'email = :email AND successful = 0',
                ['email' => $email]
            );
        } catch (\Throwable) {
        }
    }

    public static function minutesRemaining(): int
    {
        return self::WINDOW_MINUTES;
    }

    /**
     * Limpeza periódica: registros com mais de 7 dias não têm mais utilidade.
     */
    public static function purgeOld(): void
    {
        try {
            Database::query("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        } catch (\Throwable) {
        }
    }
}
