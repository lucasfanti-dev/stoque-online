<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Atualizador do banco de dados
 * =====================================================================
 *
 * Aplica as migrações pendentes e mostra o que foi feito.
 *
 * Como usar, sempre que subir arquivos novos:
 *     https://SEUDOMINIO/atualizar.php?token=SUA_CHAVE
 *
 * A chave vem de UPDATE_TOKEN no arquivo .env. Sem ela, a página responde
 * 404 e não executa nada.
 *
 * Pode ser aberta quantas vezes quiser: o sistema guarda o que já rodou e
 * aplica apenas o que falta.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

require_once $baseDir . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($baseDir . '/app');
require_once $baseDir . '/config/app.php';

// ---------------------------------------------------------------------
// Controle de acesso
// ---------------------------------------------------------------------
$tokenEsperado = trim((string) env('UPDATE_TOKEN', ''));
$tokenRecebido = (string) ($_GET['token'] ?? '');

if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecebido)) {
    http_response_code(404);
    exit('Não encontrado.');
}

set_time_limit(300);

$migrator = null;
$erroFatal = null;
$resultado = ['aplicadas' => [], 'erro' => null];
$pendentesAntes = [];
$historico = [];
$resumo = [];

try {
    $migrator = new \App\Core\Migrator();
    $pendentesAntes = $migrator->pendentes();

    // Só executa quando o botão for acionado, para que abrir a página
    // seja sempre uma consulta segura.
    if (($_GET['executar'] ?? '') === 'sim') {
        $resultado = $migrator->executar();
    }

    $historico = $migrator->historico(30);
    $resumo = $migrator->resumoDoBanco();
} catch (\Throwable $e) {
    $erroFatal = $e->getMessage();
}

$pendentesAgora = $migrator && !$erroFatal ? $migrator->pendentes() : $pendentesAntes;
$executou = ($_GET['executar'] ?? '') === 'sim';
$urlBase = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$urlExecutar = $urlBase . '?token=' . rawurlencode($tokenRecebido) . '&executar=sim';
$urlRecarregar = $urlBase . '?token=' . rawurlencode($tokenRecebido);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Atualizar Banco de Dados - StoqueOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 900px;">

    <div class="text-center mb-4">
        <h1 class="fw-bold"><i class="fa-solid fa-database text-primary me-2"></i>Atualizar Banco</h1>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES) ?>
            <?php if ($migrator): ?>
                &bull; banco <code><?= htmlspecialchars($migrator->banco(), ENT_QUOTES) ?></code>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($erroFatal): ?>
        <div class="alert alert-danger shadow-sm">
            <h5 class="fw-bold"><i class="fa-solid fa-circle-xmark me-2"></i>Não foi possível conectar ao banco</h5>
            <p class="mb-2"><?= htmlspecialchars($erroFatal, ENT_QUOTES) ?></p>
            <hr>
            <p class="mb-0 small">
                Confira <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> e
                <code>DB_PASS</code> no arquivo <code>.env</code> do servidor.
            </p>
        </div>

    <?php else: ?>

        <?php if ($resultado['erro']): ?>
            <div class="alert alert-danger shadow-sm">
                <h5 class="fw-bold"><i class="fa-solid fa-circle-xmark me-2"></i>A atualização parou em uma migração</h5>
                <p class="mb-0"><?= htmlspecialchars($resultado['erro'], ENT_QUOTES) ?></p>
                <hr>
                <p class="mb-0 small">
                    O que já tinha sido aplicado foi mantido. Corrija o problema e abra esta
                    página de novo: o sistema continua exatamente de onde parou.
                </p>
            </div>
        <?php elseif ($executou && !empty($resultado['aplicadas'])): ?>
            <div class="alert alert-success shadow-sm">
                <h5 class="fw-bold"><i class="fa-solid fa-circle-check me-2"></i>Banco atualizado</h5>
                <p class="mb-0"><?= count($resultado['aplicadas']) ?> migração(ões) aplicada(s) com sucesso.</p>
            </div>
        <?php elseif ($executou): ?>
            <div class="alert alert-info shadow-sm">
                <i class="fa-solid fa-circle-info me-2"></i>
                Não havia nada pendente — o banco já estava atualizado.
            </div>
        <?php endif; ?>

        <!-- Situação -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <?php if (empty($pendentesAgora)): ?>
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-circle-check text-success fs-1 me-3"></i>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1">Banco em dia</h5>
                            <p class="text-muted mb-0">
                                Todas as <?= count($migrator->disponiveis()) ?> migrações do sistema já foram aplicadas.
                                Não há nada a fazer agora.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-start mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-warning fs-1 me-3"></i>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1"><?= count($pendentesAgora) ?> atualização(ões) pendente(s)</h5>
                            <p class="text-muted mb-0">
                                Clique no botão para aplicar. A operação é segura e pode ser repetida:
                                o que já foi feito não é refeito.
                            </p>
                        </div>
                    </div>

                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($pendentesAgora as $arquivo): ?>
                            <li class="list-group-item px-0 py-2">
                                <i class="fa-regular fa-clock text-warning me-2"></i>
                                <code class="text-dark"><?= htmlspecialchars($arquivo, ENT_QUOTES) ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <a href="<?= htmlspecialchars($urlExecutar, ENT_QUOTES) ?>"
                       class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="fa-solid fa-play me-2"></i> Aplicar atualizações agora
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- O que acabou de ser feito -->
        <?php if (!empty($resultado['aplicadas'])): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fa-solid fa-list-check text-success me-2"></i>O que foi aplicado agora
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($resultado['aplicadas'] as $m): ?>
                        <li class="list-group-item py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($m['descricao'], ENT_QUOTES) ?></div>
                                    <code class="small text-muted"><?= htmlspecialchars($m['arquivo'], ENT_QUOTES) ?></code>
                                </div>
                                <span class="badge bg-light text-muted"><?= $m['duracao_ms'] ?> ms</span>
                            </div>

                            <?php if (!empty($m['alteracoes'])): ?>
                                <ul class="small text-muted mt-2 mb-0">
                                    <?php foreach ($m['alteracoes'] as $alteracao): ?>
                                        <li><?= htmlspecialchars($alteracao, ENT_QUOTES) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="small text-muted mt-2">
                                    Nada a alterar — já estava aplicado no banco.
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Conferência -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-bold py-3">
                        <i class="fa-solid fa-table text-primary me-2"></i>Tabelas do banco
                    </div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($resumo as $tabela => $linhas): ?>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <code class="text-dark"><?= htmlspecialchars((string) $tabela, ENT_QUOTES) ?></code>
                                <span class="text-muted"><?= number_format($linhas, 0, ',', '.') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-bold py-3">
                        <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>Histórico
                    </div>
                    <ul class="list-group list-group-flush small">
                        <?php if (empty($historico)): ?>
                            <li class="list-group-item text-muted py-3">Nenhuma migração registrada ainda.</li>
                        <?php endif; ?>

                        <?php foreach ($historico as $h): ?>
                            <li class="list-group-item py-2">
                                <div class="fw-semibold"><?= htmlspecialchars((string) $h['descricao'], ENT_QUOTES) ?></div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    <?= date('d/m/Y H:i', strtotime((string) $h['executada_em'])) ?>
                                    &bull; <?= (int) $h['duracao_ms'] ?> ms
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?= htmlspecialchars($urlRecarregar, ENT_QUOTES) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-rotate me-1"></i> Conferir novamente
            </a>
        </div>

    <?php endif; ?>

    <div class="card border-0 bg-dark text-white rounded-4 p-4 mt-4">
        <h6 class="fw-bold mb-2"><i class="fa-solid fa-lightbulb me-2"></i>Como usar daqui em diante</h6>
        <ol class="mb-0 small text-white-50">
            <li>Sempre que subir arquivos novos, abra esta página.</li>
            <li>Se aparecer algo pendente, clique em <strong>Aplicar atualizações</strong>.</li>
            <li>Se disser "Banco em dia", não precisa fazer nada.</li>
        </ol>
        <p class="small text-white-50 mt-3 mb-0">
            Esta página é protegida pela chave <code class="text-warning">UPDATE_TOKEN</code> do
            arquivo <code class="text-warning">.env</code>. Sem a chave certa na URL, ela responde
            como página inexistente.
        </p>
    </div>

</div>
</body>
</html>
