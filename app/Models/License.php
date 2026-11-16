<?php

namespace App\Models;

use App\Core\Database;

class License
{
    public static function getActiveByUserId(int $userId): ?array
    {
        self::recalculate();

        $today = date('Y-m-d');

        return Database::fetch(
            "SELECT ul.*, p.name as plan_name, p.period_name
             FROM user_licenses ul
             JOIN plans p ON p.id = ul.plan_id
             WHERE ul.user_id = :user_id
               AND ul.status = 1
               AND ul.is_closed = 0
               AND ul.start_date <= :today_start
               AND ul.end_date >= :today_end
             ORDER BY ul.end_date DESC, ul.id DESC
             LIMIT 1",
            ['user_id' => $userId, 'today_start' => $today, 'today_end' => $today]
        );
    }

    public static function getAllByUserId(int $userId): array
    {
        self::recalculate();

        return Database::fetchAll(
            "SELECT ul.*, p.name as plan_name, p.period_name
             FROM user_licenses ul
             JOIN plans p ON p.id = ul.plan_id
             WHERE ul.user_id = :user_id
             ORDER BY ul.id DESC",
            ['user_id' => $userId]
        );
    }

    public static function findById(int $licenseId): ?array
    {
        return Database::fetch(
            "SELECT ul.*, p.name as plan_name FROM user_licenses ul
             JOIN plans p ON p.id = ul.plan_id
             WHERE ul.id = :id LIMIT 1",
            ['id' => $licenseId]
        );
    }

    public static function create(array $data): int
    {
        $id = Database::insert('user_licenses', $data);
        self::recalculate();
        return $id;
    }

    public static function cancel(int $licenseId, int $userId): int
    {
        $rows = Database::update(
            'user_licenses',
            ['status' => 0, 'is_closed' => 1],
            'id = :id AND user_id = :user_id',
            ['id' => $licenseId, 'user_id' => $userId]
        );
        self::recalculate();
        return $rows;
    }

    /**
     * Ajusta a vigência das licenças (dispensa stored procedure no MySQL).
     *
     * Importante: apenas ENCERRA o que venceu. A ativação nunca acontece aqui,
     * pois uma licença só pode passar a valer por um caminho auditável
     * (pagamento confirmado, teste grátis ou concessão manual do administrador).
     */
    public static function recalculate(): void
    {
        static $alreadyRan = false;
        if ($alreadyRan) {
            return;
        }
        $alreadyRan = true;

        Database::query(
            "UPDATE user_licenses
                SET status = 0, is_closed = 1
              WHERE end_date < CURDATE()
                AND is_closed = 0"
        );
    }
}
