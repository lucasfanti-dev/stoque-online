<?php
$title = 'Planos e Preços - Admin';
$adminAtivo = 'planos';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-tags text-warning me-2"></i>Planos e Preços</h2>
            <p class="text-muted m-0 text-small">
                O que for alterado aqui aparece na hora na página pública de planos.
            </p>
        </div>
        <button class="btn btn-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#modalPlano0">
            <i class="fa-solid fa-plus me-1"></i> Novo plano
        </button>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="alert alert-info d-flex align-items-center shadow-sm text-small">
        <i class="fa-solid fa-circle-info fs-5 me-3"></i>
        <div>
            Alterar o preço <strong>não mexe</strong> em quem já assinou — cada licença guarda o valor
            e a duração contratados na época. Desativar um plano apenas impede novas contratações.
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem;">
                    <tr>
                        <th class="ps-4">Plano</th>
                        <th>Período</th>
                        <th class="text-end">Preço</th>
                        <th class="text-center">Duração</th>
                        <th class="text-center">Usuários</th>
                        <th class="text-center">Licenças</th>
                        <th class="text-end">Faturamento</th>
                        <th class="text-center">Situação</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= e($p['name']) ?></div>
                                <?php if (!empty($p['is_free_trial'])): ?>
                                    <span class="badge bg-info-subtle text-info">teste grátis</span>
                                <?php endif; ?>
                                <?php if (!empty($p['description'])): ?>
                                    <div class="text-muted text-truncate" style="max-width: 280px; font-size:.7rem;">
                                        <?= e($p['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small"><?= e($p['period_name']) ?></td>
                            <td class="text-end fw-bold">
                                <?= (float) $p['price'] > 0 ? 'R$ ' . number_format((float) $p['price'], 2, ',', '.') : 'grátis' ?>
                            </td>
                            <td class="text-center text-muted"><?= (int) $p['duration_days'] ?> dias</td>
                            <td class="text-center text-muted"><?= (int) $p['max_users'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success" title="Licenças ativas agora">
                                    <?= (int) $p['licencas_ativas'] ?>
                                </span>
                                <div class="text-muted" style="font-size:.7rem;"><?= (int) $p['licencas_total'] ?> no total</div>
                            </td>
                            <td class="text-end text-success fw-bold">
                                R$ <?= number_format((float) $p['faturamento'], 2, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($p['status'])): ?>
                                    <span class="badge bg-success-subtle text-success">à venda</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-muted">desativado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#modalPlano<?= (int) $p['id'] ?>" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <form action="<?= url('admin/plano/status') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= !empty($p['status']) ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                title="<?= !empty($p['status']) ? 'Desativar' : 'Reativar' ?>">
                                            <i class="fa-solid <?= !empty($p['status']) ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                        </button>
                                    </form>

                                    <?php if ((int) $p['licencas_total'] === 0): ?>
                                        <form action="<?= url('admin/plano/excluir') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('Excluir o plano <?= e($p['name']) ?> em definitivo?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modais de criação e edição -->
<?php
$formularios = $plans;
array_unshift($formularios, [
    'id' => 0, 'name' => '', 'period_name' => '', 'duration_days' => 30,
    'price' => '', 'max_users' => 1, 'description' => '', 'is_free_trial' => 0, 'status' => 1,
]);
?>
<?php foreach ($formularios as $p): ?>
    <?php $novo = (int) $p['id'] === 0; ?>
    <div class="modal fade" id="modalPlano<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form action="<?= url('admin/plano/salvar') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">

                    <div class="modal-header bg-light border-0">
                        <h5 class="modal-title fw-bold">
                            <?= $novo ? 'Novo plano' : 'Editar: ' . e($p['name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label text-small fw-bold">Nome do plano <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= e($p['name']) ?>"
                                       placeholder="Ex: Plano Mensal" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-small fw-bold">Período (texto) <span class="text-danger">*</span></label>
                                <input type="text" name="period_name" class="form-control" value="<?= e($p['period_name']) ?>"
                                       placeholder="Ex: Mensal" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-small fw-bold">Preço (R$)</label>
                                <input type="text" name="price" class="form-control"
                                       value="<?= $p['price'] !== '' ? number_format((float) $p['price'], 2, ',', '') : '' ?>"
                                       placeholder="149,00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-small fw-bold">Duração (dias)</label>
                                <input type="number" name="duration_days" class="form-control"
                                       value="<?= (int) $p['duration_days'] ?>" min="1" max="3650" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-small fw-bold">Usuários</label>
                                <input type="number" name="max_users" class="form-control"
                                       value="<?= (int) $p['max_users'] ?>" min="1" max="999" required>
                                <div class="form-text" style="font-size:.7rem;">inclui o titular</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-small fw-bold">Descrição</label>
                                <textarea name="description" class="form-control" rows="2"
                                          placeholder="Texto que aparece no cartão do plano"><?= e($p['description']) ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="status" value="1"
                                           id="status<?= (int) $p['id'] ?>" <?= !empty($p['status']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-small fw-bold" for="status<?= (int) $p['id'] ?>">
                                        Disponível para contratação
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_free_trial" value="1"
                                           id="trial<?= (int) $p['id'] ?>" <?= !empty($p['is_free_trial']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-small fw-bold" for="trial<?= (int) $p['id'] ?>">
                                        É o teste gratuito
                                    </label>
                                    <div class="form-text" style="font-size:.7rem;">só um plano pode ser</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                            <?= $novo ? 'Criar plano' : 'Salvar alterações' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
