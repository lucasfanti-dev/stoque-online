<?php
$title = 'Estoques dos Fornecedores - Admin';
$adminAtivo = 'estoque';
?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-boxes-stacked text-warning me-2"></i>Estoques dos Fornecedores</h2>
        <p class="text-muted m-0 text-small">
            Aqui você abre, corrige e remove o estoque de qualquer empresa cadastrada.
        </p>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.65rem;">Fornecedores com estoque</div>
                <div class="fs-4 fw-bold"><?= number_format((int) $totals['total_suppliers'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.65rem;">Itens publicados</div>
                <div class="fs-4 fw-bold"><?= number_format((int) $totals['total_items'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.65rem;">Peças somadas</div>
                <div class="fs-4 fw-bold"><?= number_format((int) $totals['total_pieces'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.65rem;">Listados abaixo</div>
                <div class="fs-4 fw-bold"><?= count($suppliers) ?></div>
            </div>
        </div>
    </div>

    <form action="<?= url('admin/estoque') ?>" method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" name="busca" value="<?= e($search) ?>" class="form-control"
                   placeholder="Buscar fornecedor por nome, e-mail ou empresa..." style="max-width: 420px;">
            <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= url('admin/estoque') ?>" class="btn btn-outline-secondary">Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem;">
                    <tr>
                        <th class="ps-4">Fornecedor</th>
                        <th>Local</th>
                        <th>Plano</th>
                        <th class="text-end">Itens</th>
                        <th class="text-end">Peças</th>
                        <th class="text-center">Cargas</th>
                        <th>Último envio</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <?= $search !== '' ? 'Nenhum fornecedor encontrado para esta busca.' : 'Nenhum fornecedor com estoque ainda.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= e(($s['trading_name'] ?? '') ?: $s['name']) ?></div>
                                <small class="text-muted"><?= e($s['email']) ?></small>
                                <?php if (empty($s['allowed_access'])): ?>
                                    <span class="badge bg-danger-subtle text-danger ms-1">bloqueado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-small text-muted">
                                <?= e(trim(($s['city'] ?? '') . '/' . ($s['state'] ?? ''), '/')) ?: '—' ?>
                            </td>
                            <td>
                                <?php if (!empty($s['plano_ativo'])): ?>
                                    <span class="badge bg-success-subtle text-success"><?= e($s['plano_ativo']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-muted">sem plano</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold"><?= number_format((int) $s['total_itens'], 0, ',', '.') ?></td>
                            <td class="text-end text-muted"><?= number_format((int) $s['total_pecas'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= (int) $s['total_cargas'] ?></span>
                            </td>
                            <td class="text-muted" style="font-size:.75rem;">
                                <?= $s['ultima_carga'] ? date('d/m/Y H:i', strtotime((string) $s['ultima_carga'])) : '—' ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?= url('admin/estoque/' . (int) $s['id']) ?>" class="btn btn-sm btn-primary fw-bold">
                                    <i class="fa-solid fa-folder-open me-1"></i> Abrir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
