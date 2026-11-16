<?php $title = 'Auditoria de Logs - StoqueOnline'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Auditoria de Logs de Acesso</h2>
            <p class="text-muted m-0 text-small">Histórico de acessos, logins e dispositivos que se conectaram à plataforma.</p>
        </div>
    </div>

    <?php $adminAtivo = 'logs'; require __DIR__ . '/_nav.php'; ?>

    <form action="<?= url('admin/logs') ?>" method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <label class="text-small fw-bold mb-0">Filtrar por ação:</label>
            <select name="acao" class="form-select" style="max-width: 320px;" onchange="this.form.submit()">
                <option value="">Todas as ações</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a['action']) ?>" <?= $selected === $a['action'] ? 'selected' : '' ?>>
                        <?= e($a['action']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selected !== ''): ?>
                <a href="<?= url('admin/logs') ?>" class="btn btn-outline-secondary btn-sm">Limpar filtro</a>
            <?php endif; ?>
            <span class="ms-auto text-muted text-small">
                <?= number_format($total, 0, ',', '.') ?> registro(s)
            </span>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase fs-8 text-muted">
                    <tr>
                        <th class="ps-4">Data e Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Endereço IP</th>
                        <th>Sistema Operacional</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Nenhum registro de log encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted fs-8"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($log['user_name'] ?: 'Desconhecido') ?></div>
                                    <div class="text-muted fs-8"><?= e($log['email']) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $action = (string) $log['action'];
                                    $isFailure = str_contains($action, 'failed') || str_contains($action, 'blocked');
                                    ?>
                                    <?php if ($action === 'login'): ?>
                                        <span class="badge bg-success-subtle text-success">Login efetuado</span>
                                    <?php elseif ($action === 'logout'): ?>
                                        <span class="badge bg-secondary-subtle text-muted">Logout</span>
                                    <?php elseif ($isFailure): ?>
                                        <span class="badge bg-danger-subtle text-danger" title="Tentativa malsucedida">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($action) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info"><?= e($action) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace text-muted fs-8"><?= e($log['ip_address']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($log['os'] ?: 'Outro') ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= e($log['browser'] ?: 'Outro') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
                <small class="text-muted">Total de <?= number_format($total, 0, ',', '.') ?> registros &bull; Página <?= $page ?> de <?= $pages ?></small>
                <nav>
                    <ul class="pagination pagination-sm m-0">
                        <?php
                        $queryBase = $selected !== '' ? 'acao=' . urlencode($selected) . '&' : '';
                        $firstPage = max(1, $page - 4);
                        $lastPage = min($pages, $firstPage + 9);
                        ?>
                        <?php for ($p = $firstPage; $p <= $lastPage; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url("admin/logs?{$queryBase}pagina={$p}") ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
