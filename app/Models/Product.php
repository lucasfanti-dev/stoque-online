<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    /** Campos que a administração pode gravar */
    private const FILLABLE = [
        'name', 'period_name', 'duration_days', 'price',
        'max_users', 'description', 'is_free_trial', 'status',
    ];

    public static function getAllActive(bool $includeFreeTrial = false): array
    {
        $sql = "SELECT * FROM plans WHERE status = 1";
        if (!$includeFreeTrial) {
            $sql .= " AND is_free_trial = 0";
        }
        $sql .= " ORDER BY is_free_trial DESC, price ASC";

        return Database::fetchAll($sql);
    }

    /**
     * @param bool $includeInactive usado na conciliação de pagamentos: um plano
     *        pode ser desativado para novas vendas sem invalidar cobranças já
     *        emitidas para ele.
     */
    public static function findById(int $id, bool $includeInactive = false): ?array
    {
        $sql = "SELECT * FROM plans WHERE id = :id";
        if (!$includeInactive) {
            $sql .= " AND status = 1";
        }
        $sql .= " LIMIT 1";

        return Database::fetch($sql, ['id' => $id]);
    }

    // -----------------------------------------------------------------
    // Administração dos planos
    // -----------------------------------------------------------------

    /**
     * Todos os planos, inclusive os desativados, com quantas licenças e
     * vendas cada um gerou.
     */
    public static function getAllForAdmin(): array
    {
        return Database::fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM user_licenses ul
                      WHERE ul.plan_id = p.id
                        AND ul.status = 1 AND ul.is_closed = 0 AND ul.end_date >= CURDATE()) AS licencas_ativas,
                    (SELECT COUNT(*) FROM user_licenses ul WHERE ul.plan_id = p.id) AS licencas_total,
                    (SELECT COALESCE(SUM(pa.amount), 0) FROM payments pa
                      WHERE pa.plan_id = p.id AND pa.status IN ('paid','completed')) AS faturamento
             FROM plans p
             ORDER BY p.is_free_trial DESC, p.price ASC"
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('plans', array_intersect_key($data, array_flip(self::FILLABLE)));
    }

    public static function update(int $id, array $data): int
    {
        return Database::update(
            'plans',
            array_intersect_key($data, array_flip(self::FILLABLE)),
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Ativa ou desativa o plano. Desativar só impede novas contratações:
     * quem já assinou mantém a licença até o fim da vigência.
     */
    public static function toggleStatus(int $id): ?array
    {
        $plan = self::findById($id, true);
        if (!$plan) {
            return null;
        }

        $novo = empty($plan['status']) ? 1 : 0;
        Database::update('plans', ['status' => $novo], 'id = :id', ['id' => $id]);

        $plan['status'] = $novo;

        return $plan;
    }

    /**
     * Um plano só pode ser apagado de vez se nunca tiver sido usado.
     */
    public static function canDelete(int $id): bool
    {
        $usos = (int) Database::fetchColumn(
            "SELECT (SELECT COUNT(*) FROM user_licenses WHERE plan_id = :p1)
                  + (SELECT COUNT(*) FROM payments WHERE plan_id = :p2)",
            ['p1' => $id, 'p2' => $id],
            0
        );

        return $usos === 0;
    }

    public static function delete(int $id): int
    {
        return Database::delete('plans', 'id = :id', ['id' => $id]);
    }
}
