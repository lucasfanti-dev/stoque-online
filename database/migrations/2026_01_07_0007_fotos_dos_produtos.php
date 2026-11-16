<?php

/**
 * Fotos das peças.
 *
 * O vínculo é pelo código normalizado somado à marca, e não pelo id da linha
 * de estoque: cada planilha enviada apaga e recria as linhas de `inventory`,
 * então uma foto presa ao id seria perdida a cada atualização de estoque.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Fotos dos produtos, vinculadas ao código da peça',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            $e->tabela('product_photos', "
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `product_search` VARCHAR(255) NOT NULL COMMENT 'Código normalizado, igual ao de inventory',
                `brand` VARCHAR(100) NOT NULL,
                `product_label` VARCHAR(255) NOT NULL COMMENT 'Código como o fornecedor escreveu',
                `filename` VARCHAR(120) NOT NULL COMMENT 'Imagem ampliada',
                `thumbname` VARCHAR(120) NOT NULL COMMENT 'Miniatura da listagem',
                `size_bytes` INT NOT NULL DEFAULT 0,
                `width` SMALLINT UNSIGNED DEFAULT NULL,
                `height` SMALLINT UNSIGNED DEFAULT NULL,
                `position` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_photo_lookup` (`user_id`, `product_search`, `brand`),
                INDEX `idx_photo_user` (`user_id`),
                CONSTRAINT `fk_photos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            "),
        ];
    },
];
