<?php

/**
 * Remove as chaves do crédito do rodapé.
 *
 * O crédito chegou a ser configurável, mas não é dado de cliente: é uma linha
 * fixa do rodapé. Deixar as duas chaves no banco só encheria a tela de
 * configurações com campo que ninguém vai mexer.
 *
 * Em instalação nova as chaves nem chegam a existir — a migração anterior
 * parou de semeá-las — e aqui o DELETE simplesmente não encontra nada.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Remove as configurações do crédito do rodapé',

    'executar' => function (PDO $pdo, Esquema $e): array {
        if (!$e->temTabela('settings')) {
            return [];
        }

        $apagar = $pdo->prepare(
            "DELETE FROM `settings` WHERE `chave` IN ('rodape_credito_texto', 'rodape_credito_url')"
        );
        $apagar->execute();

        $removidas = $apagar->rowCount();

        return $removidas > 0
            ? ["Chaves do crédito do rodapé removidas ({$removidas})"]
            : [];
    },
];
