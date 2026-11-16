<?php

/**
 * Índices que sustentam a busca.
 *
 * O idx_inventory_busca cobre todas as colunas do resultado, permitindo que
 * a busca "Contém" varra somente o índice em vez da tabela inteira. Medido
 * em base de 594 mil itens: 1.556 ms contra 276 ms.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Índices de performance da busca e dos relatórios',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            $e->indice('user_logs', 'idx_logs_user', '(`user_id`)'),
            $e->indice('user_logs', 'idx_logs_created', '(`created_at`)'),
            $e->indice('inventory', 'idx_inventory_user_product', '(`user_id`, `product`)'),
            $e->indice('inventory', 'idx_inventory_busca', '(`product_search`, `product`, `quantity`, `user_id`, `brand`)'),
            $e->indice('user_licenses', 'idx_licenses_lookup', '(`user_id`, `status`, `is_closed`, `end_date`)'),
            $e->indice('contacts', 'idx_contacts_email', '(`email`, `include_in_license`)'),
        ];
    },
];
