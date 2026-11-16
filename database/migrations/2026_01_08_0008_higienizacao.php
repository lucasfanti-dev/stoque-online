<?php

/**
 * Ajustes nos dados herdados da versão 1.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Higienização dos dados vindos da versão anterior',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            // Licença vencida não pode continuar marcada como ativa
            $e->executar(
                "UPDATE user_licenses SET status = 0, is_closed = 1
                  WHERE end_date < CURDATE() AND is_closed = 0",
                [],
                'licenças vencidas encerradas'
            ),

            // As cargas migradas vieram com o contador de itens zerado
            $e->executar(
                "UPDATE inventory_batches b
                    SET b.total_items = (SELECT COUNT(*) FROM inventory i WHERE i.batch_id = b.id)
                  WHERE b.total_items = 0
                    AND EXISTS (SELECT 1 FROM inventory i WHERE i.batch_id = b.id)",
                [],
                'contagem de itens das cargas corrigida'
            ),
        ];
    },
];
