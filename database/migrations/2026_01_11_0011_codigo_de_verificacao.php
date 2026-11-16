<?php

/**
 * Confirmação por código de 6 dígitos, no lugar do link por e-mail.
 *
 * O código é curto — um milhão de combinações — então precisa de um contador
 * de tentativas: sem ele, alguém que saiba o e-mail poderia varrer os códigos
 * até acertar. Cinco erros derrubam o código e obrigam a pedir outro.
 *
 * As colunas `token` e `password_reset_token` continuam servindo, agora
 * guardando o sha256 do código. Os links que ainda estiverem em circulação
 * param de funcionar, e é isso mesmo: a pessoa pede um código novo.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Contadores de tentativa para os códigos de verificação',

    'executar' => function (PDO $pdo, Esquema $e): array {
        $passos = [];

        $passos[] = $e->coluna(
            'users',
            'token_attempts',
            "TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Erros seguidos no código de ativação' AFTER `token_expires_at`"
        );

        $passos[] = $e->coluna(
            'users',
            'password_reset_attempts',
            "TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Erros seguidos no código de recuperação' AFTER `password_reset_expires`"
        );

        // Links antigos viraram código: o que estava pendente não vale mais.
        // Quem estiver no meio do caminho simplesmente pede um código novo.
        $limpou = $pdo->exec(
            "UPDATE users
                SET token = NULL, token_expires_at = NULL,
                    password_reset_token = NULL, password_reset_expires = NULL
              WHERE token IS NOT NULL OR password_reset_token IS NOT NULL"
        );

        if ($limpou > 0) {
            $passos[] = "Tokens de link pendentes invalidados ({$limpou} conta(s))";
        }

        return array_values(array_filter($passos));
    },
];
