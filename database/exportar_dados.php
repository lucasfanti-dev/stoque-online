<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Exportação do banco local para arquivo .sql
 * =====================================================================
 *
 * Gera um arquivo pronto para ser importado no phpMyAdmin da Hostinger
 * (ou em qualquer outro MySQL/MariaDB).
 *
 * O arquivo NÃO contém "CREATE DATABASE" nem "USE": o banco da Hostinger
 * tem nome próprio, e o dump é importado dentro do banco já selecionado.
 *
 * Uso:
 *   php database/exportar_dados.php
 *   php database/exportar_dados.php --somente-estrutura
 *   php database/exportar_dados.php --max-mb=20
 *   php database/exportar_dados.php --saida=C:/caminho/arquivo.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pela linha de comando.');
}

require_once dirname(__DIR__) . '/config/app.php';

// ---------------------------------------------------------------------
// Parâmetros
// ---------------------------------------------------------------------
$options = getopt('', ['somente-estrutura', 'max-mb::', 'saida::']);
$somenteEstrutura = isset($options['somente-estrutura']);
$maxBytes = max(1, (int) ($options['max-mb'] ?? 20)) * 1024 * 1024;

$outputDir = dirname(__DIR__) . '/storage';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0775, true);
}

$outputFile = $options['saida'] ?? $outputDir . '/stoqueonline_' . date('Y-m-d_His') . '.sql';

// ---------------------------------------------------------------------
// Conexão com o banco local
// ---------------------------------------------------------------------
$config = require dirname(__DIR__) . '/config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    exit("Não foi possível conectar ao banco local: " . $e->getMessage() . "\n");
}

/**
 * Ordem que respeita as chaves estrangeiras na hora de importar.
 */
const TABELAS = [
    'users',
    'companies',
    'contacts',
    'plans',
    'user_licenses',
    'inventory_batches',
    'inventory',
    'payments',
    'user_logs',
    'login_attempts',
];

/**
 * Tabelas cujo conteúdo não faz sentido levar para produção.
 */
const TABELAS_SEM_DADOS = [
    'login_attempts', // tentativas de login antigas do ambiente local
];

echo "=== EXPORTAÇÃO DO BANCO StoqueOnline v2 ===\n";
echo "Origem : {$config['database']} em {$config['host']}\n";
echo "Destino: {$outputFile}\n\n";

$parteAtual = 1;
$arquivoAtual = $outputFile;
$handle = fopen($arquivoAtual, 'w');

if (!$handle) {
    exit("Não foi possível criar o arquivo de saída.\n");
}

$arquivosGerados = [$arquivoAtual];
$bytesEscritos = 0;

/**
 * Escreve no arquivo, abrindo uma nova parte quando o limite é atingido.
 */
$escrever = function (string $texto, bool $podeQuebrar = false) use (
    &$handle, &$bytesEscritos, &$parteAtual, &$arquivosGerados, $maxBytes, $outputFile
): void {
    if ($podeQuebrar && $bytesEscritos > 0 && ($bytesEscritos + strlen($texto)) > $maxBytes) {
        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        $parteAtual++;
        $novoArquivo = preg_replace('/\.sql$/i', '', $outputFile) . '_parte' . $parteAtual . '.sql';
        $handle = fopen($novoArquivo, 'w');
        $arquivosGerados[] = $novoArquivo;
        $bytesEscritos = 0;

        $cabecalho = "-- StoqueOnline v2 - continuação (parte {$parteAtual})\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        fwrite($handle, $cabecalho);
        $bytesEscritos += strlen($cabecalho);

        echo "   (arquivo dividido: parte {$parteAtual})\n";
    }

    fwrite($handle, $texto);
    $bytesEscritos += strlen($texto);
};

// ---------------------------------------------------------------------
// Cabeçalho
// ---------------------------------------------------------------------
$escrever("-- =====================================================================\n");
$escrever("-- StoqueOnline v2 - Dump gerado em " . date('d/m/Y H:i:s') . "\n");
$escrever("-- Importe este arquivo DENTRO do banco já criado na hospedagem.\n");
$escrever("-- =====================================================================\n\n");
$escrever("SET NAMES utf8mb4;\n");
$escrever("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
$escrever("SET FOREIGN_KEY_CHECKS = 0;\n\n");

$totalLinhas = 0;

foreach (TABELAS as $tabela) {
    // A tabela pode não existir em bancos mais antigos
    $existe = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tabela))->fetchColumn();
    if (!$existe) {
        echo " - {$tabela}: não existe no banco local, ignorada\n";
        continue;
    }

    // -----------------------------------------------------------------
    // Estrutura
    // -----------------------------------------------------------------
    $createSql = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch()['Create Table'] ?? '';

    $escrever("-- ---------------------------------------------------------------------\n");
    $escrever("-- Estrutura da tabela `{$tabela}`\n");
    $escrever("-- ---------------------------------------------------------------------\n");
    $escrever("DROP TABLE IF EXISTS `{$tabela}`;\n");
    $escrever($createSql . ";\n\n");

    if ($somenteEstrutura || in_array($tabela, TABELAS_SEM_DADOS, true)) {
        echo " - {$tabela}: estrutura exportada (sem dados)\n";
        continue;
    }

    // -----------------------------------------------------------------
    // Dados
    // -----------------------------------------------------------------
    $total = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();

    if ($total === 0) {
        echo " - {$tabela}: estrutura exportada (tabela vazia)\n";
        continue;
    }

    echo " - {$tabela}: exportando {$total} registro(s)...";

    $colunas = $pdo->query("SHOW COLUMNS FROM `{$tabela}`")->fetchAll();
    $nomesColunas = '`' . implode('`, `', array_column($colunas, 'Field')) . '`';

    $stmt = $pdo->query("SELECT * FROM `{$tabela}`");
    $buffer = [];
    $tamanhoBuffer = 0;
    $exportados = 0;

    $descarregar = function () use (&$buffer, &$tamanhoBuffer, $escrever, $tabela, $nomesColunas): void {
        if (empty($buffer)) {
            return;
        }

        $escrever(
            "INSERT INTO `{$tabela}` ({$nomesColunas}) VALUES\n" . implode(",\n", $buffer) . ";\n",
            true
        );

        $buffer = [];
        $tamanhoBuffer = 0;
    };

    while ($linha = $stmt->fetch()) {
        $valores = [];

        foreach ($linha as $valor) {
            if ($valor === null) {
                $valores[] = 'NULL';
            } elseif (is_int($valor) || is_float($valor)) {
                $valores[] = (string) $valor;
            } else {
                $valores[] = $pdo->quote((string) $valor);
            }
        }

        $registro = '(' . implode(', ', $valores) . ')';
        $buffer[] = $registro;
        $tamanhoBuffer += strlen($registro);
        $exportados++;
        $totalLinhas++;

        // Instruções de no máximo ~500 KB evitam estourar o max_allowed_packet
        if ($tamanhoBuffer > 512000) {
            $descarregar();
        }
    }

    $descarregar();
    $escrever("\n");

    echo " OK ({$exportados})\n";
}

$escrever("\nSET FOREIGN_KEY_CHECKS = 1;\n");
fclose($handle);

// ---------------------------------------------------------------------
// Resumo
// ---------------------------------------------------------------------
echo "\n=== EXPORTAÇÃO CONCLUÍDA ===\n";
echo "Registros exportados: " . number_format($totalLinhas, 0, ',', '.') . "\n\n";

foreach ($arquivosGerados as $arquivo) {
    $tamanho = filesize($arquivo);
    $legivel = $tamanho > 1048576
        ? round($tamanho / 1048576, 2) . ' MB'
        : round($tamanho / 1024, 1) . ' KB';

    echo "  {$arquivo}  ({$legivel})\n";
}

if (count($arquivosGerados) > 1) {
    echo "\nATENÇÃO: importe os arquivos no phpMyAdmin na ordem das partes.\n";
}

echo "\nPróximo passo: phpMyAdmin da Hostinger -> selecione o banco -> aba Importar.\n";
