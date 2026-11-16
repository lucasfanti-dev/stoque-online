<?php

/**
 * Estrutura original do sistema.
 *
 * Em um banco que já está em uso, nada acontece aqui: todas as tabelas já
 * existem e a migração apenas se registra como aplicada.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Estrutura base: usuários, empresas, planos, estoque e pagamentos',

    'executar' => function (PDO $pdo, Esquema $e): array {
        $feito = [];

        $feito[] = $e->tabela('users', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `phone` VARCHAR(50) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `token` VARCHAR(255) DEFAULT NULL,
            `confirmed_activation` TINYINT(1) DEFAULT 0,
            `allowed_access` TINYINT(1) DEFAULT 1,
            `is_admin` TINYINT(1) DEFAULT 0,
            `first_access_free` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");

        $feito[] = $e->tabela('companies', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `company_name` VARCHAR(255) DEFAULT NULL,
            `trading_name` VARCHAR(255) DEFAULT NULL,
            `cnpj` VARCHAR(30) DEFAULT NULL,
            `address` VARCHAR(255) DEFAULT NULL,
            `number` VARCHAR(50) DEFAULT NULL,
            `district` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `state` VARCHAR(10) DEFAULT NULL,
            `zip_code` VARCHAR(20) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `cell_phone` VARCHAR(50) DEFAULT NULL,
            `whatsapp` VARCHAR(50) DEFAULT NULL,
            `contact_email` VARCHAR(255) DEFAULT NULL,
            `website` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ");

        $feito[] = $e->tabela('contacts', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `occupation` VARCHAR(255) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `include_in_license` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ");

        $feito[] = $e->tabela('plans', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `period_name` VARCHAR(50) NOT NULL,
            `duration_days` INT NOT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `max_users` INT NOT NULL DEFAULT 1,
            `description` TEXT DEFAULT NULL,
            `is_free_trial` TINYINT(1) DEFAULT 0,
            `status` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ");

        $feito[] = $e->tabela('user_licenses', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `plan_id` INT NOT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `max_users` INT NOT NULL DEFAULT 1,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `status` TINYINT(1) DEFAULT 1,
            `is_closed` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_licenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_licenses_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
        ");

        $feito[] = $e->tabela('inventory_batches', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `total_items` INT DEFAULT 0,
            `status` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_batches_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ");

        $feito[] = $e->tabela('inventory', "
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `batch_id` INT NOT NULL,
            `brand` VARCHAR(100) NOT NULL,
            `product` VARCHAR(255) NOT NULL,
            `product_search` VARCHAR(255) NOT NULL,
            `quantity` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_product_search` (`product_search`),
            INDEX `idx_brand` (`brand`),
            INDEX `idx_user_id` (`user_id`),
            CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_inventory_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE
        ");

        $feito[] = $e->tabela('payments', "
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `plan_id` INT NOT NULL,
            `reference_id` VARCHAR(100) NOT NULL UNIQUE,
            `amount` DECIMAL(10,2) NOT NULL,
            `payment_url` TEXT DEFAULT NULL,
            `qrcode_image` LONGTEXT DEFAULT NULL,
            `qrcode_text` TEXT DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT 'pending',
            `authorization_id` VARCHAR(100) DEFAULT NULL,
            `expires_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_payments_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
        ");

        $feito[] = $e->tabela('user_logs', "
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `ip_address` VARCHAR(50) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `os` VARCHAR(50) DEFAULT NULL,
            `browser` VARCHAR(50) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ");

        return $feito;
    },
];
