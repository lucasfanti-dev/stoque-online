<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Diagnóstico de publicação
 * =====================================================================
 *
 * Confere, direto do navegador, se o servidor está pronto:
 * versão do PHP, extensões, banco de dados, e-mail, permissões,
 * reescrita de URL e proteção dos arquivos sensíveis.
 *
 * Acesso: http://SEUDOMINIO/diagnostico.php?token=CHAVE
 * (a chave vem de DIAGNOSTIC_TOKEN no .env)
 *
 * APAGUE ESTE ARQUIVO depois de validar a publicação.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

require_once $baseDir . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($baseDir . '/app');
require_once $baseDir . '/config/app.php';

// ---------------------------------------------------------------------
// Controle de acesso
// ---------------------------------------------------------------------
$tokenEsperado = (string) env('DIAGNOSTIC_TOKEN', '');
$tokenRecebido = (string) ($_GET['token'] ?? '');

if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecebido)) {
    http_response_code(404);
    exit('Não encontrado.');
}

// ---------------------------------------------------------------------
// Coleta dos testes
// ---------------------------------------------------------------------
$itens = [];

function verificar(string $grupo, string $nome, bool $ok, string $detalhe = '', bool $aviso = false): void
{
    global $itens;
    $itens[$grupo][] = ['nome' => $nome, 'ok' => $ok, 'detalhe' => $detalhe, 'aviso' => $aviso];
}

// --- Ambiente PHP ---
$versaoOk = version_compare(PHP_VERSION, '8.1.0', '>=');
verificar('Ambiente', 'Versão do PHP', $versaoOk, PHP_VERSION . ($versaoOk ? '' : ' — mínimo exigido: 8.1'));

foreach (['pdo_mysql', 'mbstring', 'curl', 'zip', 'openssl', 'json'] as $ext) {
    verificar('Ambiente', "Extensão {$ext}", extension_loaded($ext));
}

verificar(
    'Ambiente',
    'Limite de upload',
    true,
    'upload_max_filesize=' . ini_get('upload_max_filesize') . ' | post_max_size=' . ini_get('post_max_size'),
    (int) ini_get('upload_max_filesize') < 8
);

verificar('Ambiente', 'Fuso horário', date_default_timezone_get() === 'America/Sao_Paulo', date_default_timezone_get());

// --- Configuração ---
$appUrl = (string) env('APP_URL', '');
$urlAtual = (Session_isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$appUrlOk = $appUrl !== '' && str_starts_with(rtrim($appUrl, '/'), $urlAtual);

verificar('Configuração', 'APP_URL confere com o domínio', $appUrlOk, "APP_URL={$appUrl} | acessado por {$urlAtual}");
verificar('Configuração', 'APP_DEBUG desligado', env('APP_DEBUG', false) === false, env('APP_DEBUG', false) ? 'está TRUE — erros internos aparecem para o visitante' : 'false');
verificar('Configuração', 'APP_ENV', (string) env('APP_ENV', '') === 'production', (string) env('APP_ENV', 'não definido'));
verificar('Configuração', 'HTTPS ativo', Session_isHttps(), Session_isHttps() ? 'sim' : 'ainda em HTTP — ative o SSL gratuito no hPanel', !Session_isHttps());

// --- Banco de dados ---
try {
    $pdo = \App\Core\Database::getConnection();
    verificar('Banco de dados', 'Conexão', true, (string) $pdo->query('SELECT VERSION()')->fetchColumn());

    $tabelasEsperadas = ['users', 'companies', 'contacts', 'plans', 'user_licenses', 'inventory_batches', 'inventory', 'payments', 'user_logs', 'login_attempts'];
    $existentes = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    $faltando = array_diff($tabelasEsperadas, $existentes);

    verificar('Banco de dados', 'Tabelas', empty($faltando), empty($faltando) ? count($existentes) . ' tabelas encontradas' : 'faltando: ' . implode(', ', $faltando));

    if (empty($faltando)) {
        $usuarios = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $estoque = (int) $pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();
        $planos = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
        $admins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        $legados = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE LENGTH(password) = 32')->fetchColumn();

        verificar('Banco de dados', 'Usuários cadastrados', $usuarios > 0, (string) $usuarios);
        verificar('Banco de dados', 'Itens em estoque', $estoque > 0, number_format($estoque, 0, ',', '.'));
        verificar('Banco de dados', 'Planos cadastrados', $planos > 0, (string) $planos);
        verificar('Banco de dados', 'Administrador existe', $admins > 0, (string) $admins);
        verificar('Banco de dados', 'Senhas no formato antigo (MD5)', true, $legados . ' — serão convertidas no primeiro login de cada um', $legados > 0);

        // Colunas criadas pelo upgrade.php
        $colunas = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(\PDO::FETCH_COLUMN);
        $novas = ['token_expires_at', 'password_reset_token', 'password_reset_expires'];
        $faltandoColunas = array_diff($novas, $colunas);
        verificar('Banco de dados', 'Estrutura atualizada', empty($faltandoColunas), empty($faltandoColunas) ? 'em dia' : 'rode: php database/upgrade.php — faltando ' . implode(', ', $faltandoColunas));
    }
} catch (\Throwable $e) {
    verificar('Banco de dados', 'Conexão', false, $e->getMessage());
}

// --- Permissões ---
$logDir = $baseDir . '/storage/logs';
$gravavel = is_dir($logDir) ? is_writable($logDir) : @mkdir($logDir, 0775, true);
verificar('Permissões', 'storage/logs gravável', (bool) $gravavel, $logDir);

// --- E-mail ---
$mailHost = (string) env('MAIL_HOST', '');
verificar('E-mail', 'SMTP configurado', $mailHost !== '', $mailHost !== '' ? "{$mailHost}:" . env('MAIL_PORT', '') : 'MAIL_HOST vazio — o site cairá na função mail(), que costuma falhar');

if ($mailHost !== '') {
    $porta = (int) env('MAIL_PORT', 587);
    $conexao = @fsockopen(
        (strtolower((string) env('MAIL_ENCRYPTION', 'tls')) === 'ssl' ? 'ssl://' : '') . $mailHost,
        $porta,
        $errno,
        $errstr,
        8
    );

    verificar('E-mail', 'Servidor SMTP alcançável', (bool) $conexao, $conexao ? 'porta ' . $porta . ' respondendo' : "não foi possível conectar: {$errstr}");

    if ($conexao) {
        fclose($conexao);
    }

    verificar('E-mail', 'Usuário e senha preenchidos', (string) env('MAIL_USER', '') !== '' && (string) env('MAIL_PASS', '') !== '');
}

// --- Pagamentos ---
$picpayToken = (string) env('PICPAY_TOKEN', '');
$sellerToken = (string) env('PICPAY_SELLER_TOKEN', '');
verificar('Pagamentos', 'PICPAY_TOKEN preenchido', $picpayToken !== '', $picpayToken !== '' ? 'sim' : 'checkout ficará indisponível', $picpayToken === '');
verificar('Pagamentos', 'PICPAY_SELLER_TOKEN preenchido', $sellerToken !== '', $sellerToken !== '' ? 'sim' : 'webhook desativado por segurança', $sellerToken === '');
verificar('Pagamentos', 'URL de callback', str_contains((string) env('PICPAY_CALLBACK_URL', ''), $_SERVER['HTTP_HOST'] ?? '#'), (string) env('PICPAY_CALLBACK_URL', 'não definida'));

// --- Segurança dos arquivos ---
$base = rtrim($appUrl !== '' ? $appUrl : $urlAtual, '/');
$alvos = ['/.env' => 'Arquivo .env', '/app/Core/Auth.php' => 'Código-fonte (app/)', '/database/schema.sql' => 'Scripts de banco'];

foreach ($alvos as $caminho => $rotulo) {
    $codigo = testarUrl($base . $caminho);
    $protegido = $codigo === null || $codigo >= 400;
    verificar(
        'Segurança',
        $rotulo . ' bloqueado',
        $protegido,
        $codigo === null ? 'não foi possível testar automaticamente' : 'HTTP ' . $codigo,
        $codigo === null
    );
}

$codigoRota = testarUrl($base . '/pesquisa');
verificar('Segurança', 'Reescrita de URL funcionando', $codigoRota === 200, $codigoRota === null ? 'não foi possível testar' : 'HTTP ' . $codigoRota, $codigoRota === null);

/**
 * Consulta uma URL do próprio site e devolve o código HTTP.
 */
function testarUrl(string $url): ?int
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $codigo > 0 ? $codigo : null;
    }

    return null;
}

function Session_isHttps(): bool
{
    return \App\Core\Session::isHttps();
}

// ---------------------------------------------------------------------
// Totais
// ---------------------------------------------------------------------
$falhas = 0;
$avisos = 0;

foreach ($itens as $lista) {
    foreach ($lista as $item) {
        if (!$item['ok']) {
            $falhas++;
        } elseif ($item['aviso']) {
            $avisos++;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Diagnóstico de Publicação - StoqueOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 900px;">

    <div class="text-center mb-4">
        <h1 class="fw-bold">Diagnóstico de Publicação</h1>
        <p class="text-muted mb-0">StoqueOnline v2 &bull; <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES) ?></p>
    </div>

    <?php if ($falhas === 0 && $avisos === 0): ?>
        <div class="alert alert-success shadow-sm d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-3 me-3"></i>
            <div><strong>Tudo certo!</strong> O sistema está pronto para uso. Apague este arquivo (<code>public/diagnostico.php</code>) e a linha <code>DIAGNOSTIC_TOKEN</code> do .env.</div>
        </div>
    <?php elseif ($falhas === 0): ?>
        <div class="alert alert-warning shadow-sm d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fs-3 me-3"></i>
            <div>Nenhuma falha crítica, mas há <strong><?= $avisos ?> ponto(s) de atenção</strong> destacados abaixo.</div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger shadow-sm d-flex align-items-center">
            <i class="fa-solid fa-circle-xmark fs-3 me-3"></i>
            <div><strong><?= $falhas ?> item(ns) com problema.</strong> Corrija antes de divulgar o site.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($itens as $grupo => $lista): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white fw-bold py-3"><?= htmlspecialchars($grupo, ENT_QUOTES) ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($lista as $item): ?>
                    <li class="list-group-item d-flex align-items-start py-3">
                        <div class="me-3">
                            <?php if (!$item['ok']): ?>
                                <i class="fa-solid fa-circle-xmark text-danger fs-5"></i>
                            <?php elseif ($item['aviso']): ?>
                                <i class="fa-solid fa-triangle-exclamation text-warning fs-5"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-check text-success fs-5"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars($item['nome'], ENT_QUOTES) ?></div>
                            <?php if ($item['detalhe'] !== ''): ?>
                                <div class="text-muted small"><?= htmlspecialchars($item['detalhe'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-dark text-white">
        <h6 class="fw-bold mb-2"><i class="fa-solid fa-lock me-2"></i>Antes de divulgar o site</h6>
        <ol class="mb-0 small text-white-50">
            <li>Entre com o administrador e <strong>troque a senha padrão</strong>.</li>
            <li>Apague <code>public/diagnostico.php</code> e a linha <code>DIAGNOSTIC_TOKEN</code> do <code>.env</code>.</li>
            <li>Ative o SSL gratuito no hPanel e troque <code>APP_URL</code> para <code>https://</code>.</li>
            <li>Cadastre a URL de callback no painel do PicPay.</li>
        </ol>
    </div>

</div>
</body>
</html>
