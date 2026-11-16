<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Atualizador do banco (linha de comando)
 * =====================================================================
 *
 * Mesma função da página public/atualizar.php, para quem tem acesso a
 * terminal ou quer agendar no cron.
 *
 *   php database/atualizar.php              aplica o que estiver pendente
 *   php database/atualizar.php --conferir   só mostra o que falta
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use a página public/atualizar.php pelo navegador.');
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(dirname(__DIR__) . '/app');

$opcoes = getopt('', ['conferir']);
$apenasConferir = isset($opcoes['conferir']);

echo "=== ATUALIZAÇÃO DO BANCO - StoqueOnline v2 ===\n";

try {
    $migrator = new \App\Core\Migrator();
} catch (Throwable $e) {
    exit("\nNão foi possível conectar ao banco: " . $e->getMessage() . "\n");
}

echo "Banco: " . $migrator->banco() . "\n\n";

$disponiveis = $migrator->disponiveis();
$pendentes = $migrator->pendentes();

printf("  migrações no projeto: %d\n", count($disponiveis));
printf("  já aplicadas:         %d\n", count($disponiveis) - count($pendentes));
printf("  pendentes:            %d\n\n", count($pendentes));

if (empty($pendentes)) {
    echo "Banco em dia. Nada a fazer.\n";
    exit(0);
}

foreach ($pendentes as $arquivo) {
    echo "  [pendente] {$arquivo}\n";
}

if ($apenasConferir) {
    echo "\n(modo conferência: nada foi alterado)\n";
    exit(0);
}

echo "\n--- Aplicando ---\n";

$resultado = $migrator->executar();

foreach ($resultado['aplicadas'] as $m) {
    printf("\n  %s  (%d ms)\n", $m['descricao'], $m['duracao_ms']);

    if (empty($m['alteracoes'])) {
        echo "     · nada a alterar, já estava aplicado\n";
        continue;
    }

    foreach ($m['alteracoes'] as $alteracao) {
        echo "     + {$alteracao}\n";
    }
}

if ($resultado['erro']) {
    echo "\nERRO: " . $resultado['erro'] . "\n";
    echo "As migrações anteriores foram mantidas. Corrija e rode de novo.\n";
    exit(1);
}

echo "\n=== CONCLUÍDO: " . count($resultado['aplicadas']) . " migração(ões) aplicada(s) ===\n";

echo "\nSituação do banco:\n";
foreach ($migrator->resumoDoBanco() as $tabela => $linhas) {
    printf("  %-20s %s\n", $tabela, number_format($linhas, 0, ',', '.'));
}
