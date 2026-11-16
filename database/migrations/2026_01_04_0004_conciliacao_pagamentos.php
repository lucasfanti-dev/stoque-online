<?php

/**
 * Vínculo entre pagamento e licença.
 *
 * É o que impede o callback do PicPay de gerar licença duplicada: ele
 * notifica mais de uma vez (primeiro "paid", depois "completed") e só a
 * primeira execução encontra license_id vazio.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Conciliação de pagamentos e origem das licenças',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            $e->coluna('payments', 'license_id', 'INT NULL DEFAULT NULL'),
            $e->coluna('payments', 'paid_at', 'DATETIME NULL DEFAULT NULL'),
            $e->coluna('payments', 'last_checked_at', 'DATETIME NULL DEFAULT NULL'),
            $e->coluna('payments', 'cancelled_at', 'DATETIME NULL DEFAULT NULL'),
            $e->indice('payments', 'idx_payments_status', '(`status`)'),
            $e->coluna('user_licenses', 'payment_id', 'INT NULL DEFAULT NULL'),
            $e->coluna('user_licenses', 'origin', "VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'trial | payment | manual'"),
        ];
    },
];
