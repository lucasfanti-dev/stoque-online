<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Envio direto do banco local para a hospedagem
 * =====================================================================
 *
 * Copia estrutura e dados do banco local para o banco da Hostinger sem
 * precisar de phpMyAdmin nem do mysqldump.
 *
 * PRÉ-REQUISITO: o MySQL remoto precisa estar liberado para o seu IP
 * (hPanel -> Bancos de Dados -> MySQL Remoto). Se a hospedagem não
 * permitir acesso remoto, use o caminho alternativo:
 *
 *     php database/exportar_dados.php     (gera .sql para o phpMyAdmin)
 *
 * Uso:
 *   php database/enviar_para_hostinger.php --testar     (só testa a conexão)
 *   php database/enviar_para_hostinger.php              (envia tudo)
 *   php database/enviar_para_hostinger.php --somente-estrutura
 *
 * As credenciais de destino são lidas do arquivo .env.producao
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pela linha de comando.');
}

require_once dirname(__DIR__) . '/config/app.php';

$options = getopt('', ['testar', 'somente-estrutura', 'env::']);
$apenasTestar = isset($options['testar']);
$somenteEstrutura = isset($options['somente-estrutura']);
$envFile = $options['env'] ?? dirname(__DIR__) . '/.env.producao';

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

/** Tabelas cujo conteúdo local não deve ir para produção */
const TABELAS_SEM_DADOS = ['login_attempts'];

/** Registros enviados por instrução INSERT */
const LOTE = 400;

/**
 * Leitor de .env independente do ambiente já carregado.
 */
function lerEnv(string $arquivo): array
{
    if (!file_exists($arquivo)) {
        return [];
    }

    $dados = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        if (strlen($valor) > 1 && (
            ($valor[0] === '"' && str_ends_with($valor, '"')) ||
            ($valor[0] === "'" && str_ends_with($valor, "'"))
        )) {
            $valor = substr($valor, 1, -1);
        }

        $dados[$chave] = $valor;
    }

    return $dados;
}

function conectar(string $host, string $porta, string $banco, string $usuario, string $senha): PDO
{
    return new PDO(
        "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 20,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]
    );
}

echo "=== ENVIO DO BANCO PARA A HOSPEDAGEM ===\n\n";

// ---------------------------------------------------------------------
// Credenciais de destino
// ---------------------------------------------------------------------
$env = lerEnv($envFile);

if (empty($env['DB_NAME']) || empty($env['DB_USER'])) {
    echo "Arquivo de destino não configurado: {$envFile}\n\n";
    echo "Crie o arquivo .env.producao (copie de .env.producao.example) e preencha:\n";
    echo "  DB_HOST=  (ex.: srvXXX.hstgr.io)\n";
    echo "  DB_PORT=3306\n";
    echo "  DB_NAME=  (ex.: u123456789_stoque)\n";
    echo "  DB_USER=  (ex.: u123456789_stoque)\n";
    echo "  DB_PASS=\n";
    exit(1);
}

$destino = [
    'host'  => $env['DB_HOST'] ?? 'localhost',
    'porta' => $env['DB_PORT'] ?? '3306',
    'banco' => $env['DB_NAME'],
    'user'  => $env['DB_USER'],
    'senha' => $env['DB_PASS'] ?? '',
];

// ---------------------------------------------------------------------
// Conexões
// ---------------------------------------------------------------------
$config = require dirname(__DIR__) . '/config/database.php';

try {
    $origem = conectar($config['host'], (string) $config['port'], $config['database'], $config['username'], (string) $config['password']);
    echo "Origem  : {$config['database']} em {$config['host']}  [OK]\n";
} catch (PDOException $e) {
    exit("Falha ao conectar no banco LOCAL: " . $e->getMessage() . "\n");
}

try {
    $alvo = conectar($destino['host'], $destino['porta'], $destino['banco'], $destino['user'], $destino['senha']);
    echo "Destino : {$destino['banco']} em {$destino['host']}  [OK]\n";
} catch (PDOException $e) {
    echo "\nFalha ao conectar no banco REMOTO: " . $e->getMessage() . "\n\n";
    echo "Causas mais comuns:\n";
    echo " 1. MySQL remoto não liberado no hPanel para o seu IP atual\n";
    echo " 2. Host, usuário ou senha incorretos no .env.producao\n";
    echo " 3. Firewall da sua rede bloqueando a porta 3306\n\n";
    echo "Alternativa sem acesso remoto:\n";
    echo "   php database/exportar_dados.php   e importe o .sql pelo phpMyAdmin\n";
    exit(1);
}

$versao = $alvo->query('SELECT VERSION()')->fetchColumn();
echo "Servidor de destino: MySQL/MariaDB {$versao}\n";

if ($apenasTestar) {
    $tabelasExistentes = $alvo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas já existentes no destino: " . (count($tabelasExistentes) ?: 'nenhuma') . "\n";
    if ($tabelasExistentes) {
        echo "  (" . implode(', ', $tabelasExistentes) . ")\n";
        echo "\nATENÇÃO: essas tabelas serão RECRIADAS na migração.\n";
    }
    echo "\nConexão validada. Rode sem --testar para enviar os dados.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Confirmação
// ---------------------------------------------------------------------
$tabelasExistentes = $alvo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if ($tabelasExistentes) {
    echo "\nO banco de destino já contém " . count($tabelasExistentes) . " tabela(s).\n";
    echo "Elas serão APAGADAS e recriadas com os dados locais.\n";
    echo "Digite CONFIRMAR para continuar: ";

    $resposta = trim((string) fgets(STDIN));
    if ($resposta !== 'CONFIRMAR') {
        exit("Operação cancelada.\n");
    }
}

echo "\n--- Iniciando transferência ---\n";
$inicio = microtime(true);

$alvo->exec('SET FOREIGN_KEY_CHECKS = 0');
$alvo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

$resumo = [];

foreach (TABELAS as $tabela) {
    $existe = $origem->query('SHOW TABLES LIKE ' . $origem->quote($tabela))->fetchColumn();
    if (!$existe) {
        echo " - {$tabela}: não existe no banco local, ignorada\n";
        continue;
    }

    // -----------------------------------------------------------------
    // Estrutura
    // -----------------------------------------------------------------
    $createSql = $origem->query("SHOW CREATE TABLE `{$tabela}`")->fetch()['Create Table'] ?? '';

    $alvo->exec("DROP TABLE IF EXISTS `{$tabela}`");
    $alvo->exec($createSql);

    if ($somenteEstrutura || in_array($tabela, TABELAS_SEM_DADOS, true)) {
        echo " - {$tabela}: estrutura criada (sem dados)\n";
        $resumo[$tabela] = 0;
        continue;
    }

    // -----------------------------------------------------------------
    // Dados
    // -----------------------------------------------------------------
    $total = (int) $origem->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();

    if ($total === 0) {
        echo " - {$tabela}: estrutura criada (tabela vazia)\n";
        $resumo[$tabela] = 0;
        continue;
    }

    $colunas = $origem->query("SHOW COLUMNS FROM `{$tabela}`")->fetchAll(PDO::FETCH_COLUMN);
    $listaColunas = '`' . implode('`, `', $colunas) . '`';
    $placeholdersLinha = '(' . implode(', ', array_fill(0, count($colunas), '?')) . ')';

    $stmt = $origem->query("SELECT * FROM `{$tabela}`");

    $enviados = 0;
    $lote = [];
    $valores = [];

    $alvo->beginTransaction();

    $gravar = function () use ($alvo, &$lote, &$valores, $tabela, $listaColunas): void {
        if (empty($lote)) {
            return;
        }

        $sql = "INSERT INTO `{$tabela}` ({$listaColunas}) VALUES " . implode(', ', $lote);
        $alvo->prepare($sql)->execute($valores);

        $lote = [];
        $valores = [];
    };

    echo " - {$tabela}: enviando " . number_format($total, 0, ',', '.') . " registro(s)...";

    while ($linha = $stmt->fetch()) {
        $lote[] = $placeholdersLinha;
        foreach ($linha as $valor) {
            $valores[] = $valor;
        }

        $enviados++;

        if (count($lote) >= LOTE) {
            $gravar();

            // Feedback de progresso para tabelas grandes
            if ($total > 5000 && $enviados % 10000 === 0) {
                echo "\n     " . number_format($enviados, 0, ',', '.') . " de " . number_format($total, 0, ',', '.') . "...";
            }
        }
    }

    $gravar();
    $alvo->commit();

    $conferencia = (int) $alvo->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();
    $resumo[$tabela] = $conferencia;

    echo ($conferencia === $total)
        ? " OK ({$conferencia})\n"
        : " DIVERGÊNCIA: origem {$total}, destino {$conferencia}\n";
}

$alvo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ---------------------------------------------------------------------
// Conferência final
// ---------------------------------------------------------------------
$segundos = round(microtime(true) - $inicio, 1);

echo "\n=== TRANSFERÊNCIA CONCLUÍDA em {$segundos}s ===\n\n";
printf("  %-20s %12s %12s\n", 'TABELA', 'ORIGEM', 'DESTINO');
echo '  ' . str_repeat('-', 46) . "\n";

$divergencias = 0;

foreach ($resumo as $tabela => $destinoTotal) {
    $origemTotal = (int) $origem->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();
    $esperado = in_array($tabela, TABELAS_SEM_DADOS, true) || $somenteEstrutura ? 0 : $origemTotal;

    if ($destinoTotal !== $esperado) {
        $divergencias++;
    }

    printf(
        "  %-20s %12s %12s %s\n",
        $tabela,
        number_format($origemTotal, 0, ',', '.'),
        number_format($destinoTotal, 0, ',', '.'),
        $destinoTotal === $esperado ? 'OK' : '<-- CONFERIR'
    );
}

echo "\n";

if ($divergencias > 0) {
    echo "Existem {$divergencias} divergência(s). Rode o script novamente.\n";
    exit(1);
}

echo "Todos os dados foram transferidos e conferidos.\n";
echo "Próximo passo: envie os arquivos e configure o .env no servidor.\n";
