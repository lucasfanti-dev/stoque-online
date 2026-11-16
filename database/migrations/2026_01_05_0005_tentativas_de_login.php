<?php

/**
 * Registro das tentativas de login, base do bloqueio por força bruta.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Controle de tentativas de login',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            $e->tabela('login_attempts', "
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NULL DEFAULT NULL,
                `ip_address` VARCHAR(50) NOT NULL,
                `successful` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_attempts_email` (`email`, `created_at`),
                INDEX `idx_attempts_ip` (`ip_address`, `created_at`)
            "),
        ];
    },
];
