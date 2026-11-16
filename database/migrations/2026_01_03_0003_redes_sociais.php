<?php

/**
 * Campos de redes sociais que existiam na versão 1 e não vieram na migração
 * inicial dos dados.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Redes sociais no cadastro da empresa',

    'executar' => function (PDO $pdo, Esquema $e): array {
        $feito = [];

        foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'twitter', 'skype'] as $rede) {
            $feito[] = $e->coluna('companies', $rede, 'VARCHAR(255) NULL DEFAULT NULL');
        }

        return $feito;
    },
];
