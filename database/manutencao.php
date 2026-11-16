<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Rotina de manutenção
 * =====================================================================
 *
 * Feita para rodar sozinha, uma vez por dia, pelo agendador de tarefas
 * (cron) da hospedagem:
 *
 *   php /home/SEU_USUARIO/domains/.../stoque/database/manutencao.php
 *
 * O que ela faz:
 *   1. Gera um backup do banco e mantém os N mais recentes
 *   2. Encerra licenças vencidas
 *   3. Limpa tokens de ativação/senha já expirados
 *   4. Apaga tentativas de login e logs antigos
 *   5. Remove arquivos de log grandes demais
 *
 * Opções:
 *   --sem-backup          pula a geração do backup
 *   --dias-log=180        quantos dias de log manter (padrão 180)
 *   --backups=7           quantos backups manter (padrão 7)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pela linha de comando.');
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(dirname(__DIR__) . '/app');

use App\Core\Database;

$options = getopt('', ['sem-backup', 'dias-log::', 'backups::']);
$diasLog = max(7, (int) ($options['dias-log'] ?? 180));
$manterBackups = max(1, (int) ($options['backups'] ?? 7));
$fazerBackup = !isset($options['sem-backup']);

$inicio = microtime(true);

echo "=== MANUTENÇÃO StoqueOnline v2 - " . date('d/m/Y H:i:s') . " ===\n\n";

try {
    // -----------------------------------------------------------------
    // 1. Backup do banco
    // -----------------------------------------------------------------
    if ($fazerBackup) {
        echo "1. Backup do banco de dados\n";

        $pastaBackup = base_path('storage/backups');
        if (!is_dir($pastaBackup)) {
            @mkdir($pastaBackup, 0775, true);
        }

        $arquivo = $pastaBackup . '/backup_' . date('Y-m-d_His') . '.sql';
        $comando = sprintf(
            '%s %s --saida=%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/exportar_dados.php'),
            escapeshellarg($arquivo)
        );

        exec($comando . ' 2>&1', $saida, $codigo);

        if ($codigo === 0 && file_exists($arquivo)) {
            $tamanho = filesize($arquivo);

            // Compacta para ocupar menos espaço na hospedagem
            if (function_exists('gzopen')) {
                $gz = gzopen($arquivo . '.gz', 'wb9');
                $entrada = fopen($arquivo, 'rb');

                while (!feof($entrada)) {
                    gzwrite($gz, (string) fread($entrada, 262144));
                }

                fclose($entrada);
                gzclose($gz);

                if (file_exists($arquivo . '.gz')) {
                    $comprimido = filesize($arquivo . '.gz');
                    unlink($arquivo);
                    $arquivo .= '.gz';

                    printf(
                        "   + %s (%s, compactado de %s)\n",
                        basename($arquivo),
                        formatarTamanho($comprimido),
                        formatarTamanho($tamanho)
                    );
                }
            } else {
                printf("   + %s (%s)\n", basename($arquivo), formatarTamanho($tamanho));
            }

            // Mantém apenas os backups mais recentes
            $existentes = glob($pastaBackup . '/backup_*.sql*') ?: [];
            usort($existentes, static fn($a, $b) => filemtime($b) <=> filemtime($a));

            $removidos = 0;
            foreach (array_slice($existentes, $manterBackups) as $antigo) {
                if (@unlink($antigo)) {
                    $removidos++;
                }
            }

            echo "   · {$removidos} backup(s) antigo(s) removido(s), mantendo os {$manterBackups} mais recentes\n";
        } else {
            echo "   ! FALHA ao gerar o backup\n";
            echo '     ' . implode("\n     ", array_slice($saida, -3)) . "\n";
        }
    } else {
        echo "1. Backup ignorado (--sem-backup)\n";
    }

    // -----------------------------------------------------------------
    // 2. Licenças vencidas
    // -----------------------------------------------------------------
    echo "\n2. Licenças\n";

    $encerradas = Database::query(
        "UPDATE user_licenses SET status = 0, is_closed = 1
          WHERE end_date < CURDATE() AND is_closed = 0"
    )->rowCount();

    echo "   · {$encerradas} licença(s) vencida(s) encerrada(s)\n";

    $ativas = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM user_licenses WHERE status = 1 AND is_closed = 0 AND end_date >= CURDATE()",
        [],
        0
    );
    echo "   · {$ativas} licença(s) ativa(s) no momento\n";

    // Avisa sobre licenças que vencem nos próximos 7 dias
    $vencendo = Database::fetchAll(
        "SELECT u.name, u.email, ul.end_date
           FROM user_licenses ul
           JOIN users u ON u.id = ul.user_id
          WHERE ul.is_closed = 0
            AND ul.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          ORDER BY ul.end_date"
    );

    if ($vencendo) {
        echo "   ! " . count($vencendo) . " licença(s) vencem em até 7 dias:\n";
        foreach ($vencendo as $v) {
            printf("       %s (%s) em %s\n", $v['name'], $v['email'], date('d/m/Y', strtotime((string) $v['end_date'])));
        }
    }

    // -----------------------------------------------------------------
    // 3. Tokens expirados
    // -----------------------------------------------------------------
    echo "\n3. Tokens expirados\n";

    $tokensAtivacao = Database::query(
        "UPDATE users SET token = NULL, token_expires_at = NULL
          WHERE token IS NOT NULL AND token_expires_at IS NOT NULL AND token_expires_at < NOW()"
    )->rowCount();

    $tokensSenha = Database::query(
        "UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL
          WHERE password_reset_token IS NOT NULL AND password_reset_expires < NOW()"
    )->rowCount();

    echo "   · {$tokensAtivacao} token(s) de ativação expirado(s) limpo(s)\n";
    echo "   · {$tokensSenha} token(s) de redefinição de senha expirado(s) limpo(s)\n";

    // -----------------------------------------------------------------
    // 4. Registros antigos
    // -----------------------------------------------------------------
    echo "\n4. Limpeza de registros antigos\n";

    $tentativas = Database::query(
        "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->rowCount();
    echo "   · {$tentativas} tentativa(s) de login com mais de 7 dias\n";

    $logs = Database::query(
        "DELETE FROM user_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL {$diasLog} DAY)"
    )->rowCount();
    echo "   · {$logs} registro(s) de auditoria com mais de {$diasLog} dias\n";

    // Histórico de cargas já substituídas e sem nenhuma peça
    $lotes = Database::query(
        "DELETE FROM inventory_batches
          WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.batch_id = inventory_batches.id)"
    )->rowCount();
    echo "   · {$lotes} carga(s) de estoque sem itens e com mais de 1 ano\n";

    // Cobranças que nunca foram pagas e já expiraram
    $cobrancas = Database::query(
        "UPDATE payments SET status = 'expired'
          WHERE status = 'pending'
            AND expires_at IS NOT NULL
            AND expires_at < NOW()"
    )->rowCount();
    echo "   · {$cobrancas} cobrança(s) pendente(s) marcada(s) como expirada(s)\n";

    // -----------------------------------------------------------------
    // 4.1 Imagens sem dono
    // -----------------------------------------------------------------
    echo "\n4.1 Fotos dos produtos\n";

    $nomes = [];
    foreach (Database::fetchAll("SELECT filename, thumbname FROM product_photos") as $foto) {
        $nomes[] = $foto['filename'];
        $nomes[] = $foto['thumbname'];
    }

    $orfaos = \App\Services\ImageService::limparOrfaos($nomes);
    echo "   · {$orfaos} arquivo(s) de imagem sem registro no banco removido(s)\n";

    $espaco = (int) Database::fetchColumn("SELECT COALESCE(SUM(size_bytes), 0) FROM product_photos", [], 0);
    $quantas = (int) Database::fetchColumn("SELECT COUNT(*) FROM product_photos", [], 0);
    echo "   · {$quantas} foto(s) cadastrada(s), ocupando " . formatarTamanho($espaco) . "\n";

    // -----------------------------------------------------------------
    // 5. Arquivos de log
    // -----------------------------------------------------------------
    echo "\n5. Arquivos de log\n";

    $pastaLogs = base_path('storage/logs');
    $limiteBytes = 5 * 1024 * 1024; // 5 MB por arquivo

    foreach (glob($pastaLogs . '/*.log') ?: [] as $log) {
        $tamanho = filesize($log);

        if ($tamanho > $limiteBytes) {
            // Mantém a última metade do arquivo, descarta o começo
            $conteudo = file_get_contents($log);
            file_put_contents($log, substr((string) $conteudo, (int) ($limiteBytes / 2)));

            printf("   · %s reduzido (estava em %s)\n", basename($log), formatarTamanho($tamanho));
        }
    }

    echo "   · verificação concluída\n";

    // -----------------------------------------------------------------
    // Resumo
    // -----------------------------------------------------------------
    $segundos = round(microtime(true) - $inicio, 1);
    echo "\n=== MANUTENÇÃO CONCLUÍDA em {$segundos}s ===\n";
} catch (Throwable $e) {
    echo "\nERRO NA MANUTENÇÃO: " . $e->getMessage() . "\n";
    error_log('[StoqueOnline] Falha na manutenção: ' . $e->getMessage());
    exit(1);
}

function formatarTamanho(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    return round($bytes / 1024, 1) . ' KB';
}
