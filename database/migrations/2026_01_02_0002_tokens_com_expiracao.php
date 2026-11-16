<?php

/**
 * Separa o token de ativação do token de redefinição de senha e dá prazo de
 * validade aos dois. Antes, pedir "esqueci a senha" apagava o token de
 * ativação de quem ainda não havia confirmado a conta.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Tokens de ativação e de senha separados, com expiração',

    'executar' => function (PDO $pdo, Esquema $e): array {
        return [
            $e->coluna('users', 'token_expires_at', 'DATETIME NULL DEFAULT NULL AFTER `token`'),
            $e->coluna('users', 'password_reset_token', 'VARCHAR(255) NULL DEFAULT NULL AFTER `token_expires_at`'),
            $e->coluna('users', 'password_reset_expires', 'DATETIME NULL DEFAULT NULL AFTER `password_reset_token`'),
            $e->coluna('users', 'last_login_at', 'DATETIME NULL DEFAULT NULL'),
            $e->indice('users', 'idx_users_token', '(`token`)'),
            $e->indice('users', 'idx_users_reset_token', '(`password_reset_token`)'),
        ];
    },
];
