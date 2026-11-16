<?php

/**
 * =====================================================================
 * StoqueOnline v2 - upgrade.php (mantido por compatibilidade)
 * =====================================================================
 *
 * A atualização do banco passou a ser feita por migrações versionadas.
 * Este arquivo apenas encaminha para o novo atualizador, para que
 * instruções e agendamentos antigos continuem funcionando.
 *
 * Use preferencialmente:
 *   php database/atualizar.php          (linha de comando)
 *   public/atualizar.php?token=...      (navegador)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pela linha de comando.');
}

echo "Aviso: upgrade.php foi substituído por atualizar.php (migrações versionadas).\n";
echo "Encaminhando...\n\n";

require __DIR__ . '/atualizar.php';
