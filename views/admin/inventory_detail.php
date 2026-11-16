<?php
$title = 'Estoque de ' . $owner['name'] . ' - Admin';
$adminAtivo = 'estoque';
$nomeExibicao = ($company['trading_name'] ?? '') ?: $owner['name'];
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <a href="<?= url('admin/estoque') ?>" class="text-decoration-none text-muted text-small">
                <i class="fa-solid fa-arrow-left me-1"></i> Voltar para a lista
            </a>
            <h2 class="fw-bold mb-1 mt-1">
                <i class="fa-solid fa-boxes-stacked text-warning me-2"></i><?= e($nomeExibicao) ?>
            </h2>
            <p class="text-muted m-0 text-small">
                <?= e($owner['email']) ?>
                <?php if (!empty($company['city'])): ?>
                    &bull; <?= e($company['city']) ?>/<?= e($company['state'] ?? '') ?>
                <?php endif; ?>
                &bull; conta #<?= (int) $owner['id'] ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <?php if ($totalGeral > 0): ?>
                <a href="<?= url('admin/estoque/' . (int) $owner['id'] . '/exportar') ?>"
                   class="btn btn-outline-success btn-sm fw-bold">
                    <i class="fa-solid fa-file-csv me-1"></i> Exportar
                </a>
            <?php endif; ?>
            <a href="<?= url('admin/usuarios?busca=' . urlencode((string) $owner['email'])) ?>"
               class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="fa-solid fa-user-gear me-1"></i> Conta
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="alert alert-warning d-flex align-items-center shadow-sm">
        <i class="fa-solid fa-user-shield fs-4 me-3"></i>
        <div class="text-small">
            Você está mexendo no estoque de <strong>outra empresa</strong>.
            Toda alteração feita aqui fica registrada na auditoria com o seu e-mail.
        </div>
    </div>

    <div class="row g-4">
        <!-- Itens -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-bold">
                        <?= number_format($total, 0, ',', '.') ?> item(ns)
                        <?= $search !== '' ? 'para "' . e($search) . '"' : 'no estoque' ?>
                    </span>

                    <form action="<?= url('admin/estoque/' . (int) $owner['id']) ?>" method="GET" class="d-flex gap-2" style="max-width: 340px;">
                        <input type="text" name="busca" value="<?= e($search) ?>" class="form-control form-control-sm"
                               placeholder="Buscar código...">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="<?= url('admin/estoque/' . (int) $owner['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light text-uppercase" style="font-size:.7rem;">
                            <tr>
                                <th class="ps-4">Marca</th>
                                <th>Código</th>
                                <th class="text-center">Qtde</th>
                                <th>Cadastrado</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-box-open fs-1 opacity-50 d-block mb-2"></i>
                                        <?= $search !== '' ? 'Nenhum item encontrado nesta busca.' : 'Este fornecedor não tem estoque publicado.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge bg-secondary-subtle text-dark"><?= e($item['brand']) ?></span>
                                    </td>
                                    <td class="fw-bold text-primary"><?= e($item['product']) ?></td>
                                    <td class="text-center">
                                        <!-- Ajuste direto da quantidade -->
                                        <form action="<?= url('admin/estoque/item/quantidade') ?>" method="POST"
                                              class="d-flex gap-1 justify-content-center form-quantidade">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <input type="number" name="quantity" class="form-control form-control-sm text-center"
                                                   value="<?= (int) $item['quantity'] ?>" min="0" max="99999999"
                                                   style="width: 95px;" data-valor-original="<?= (int) $item['quantity'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success btn-salvar-quantidade"
                                                    title="Salvar a nova quantidade">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>
                                        <?php if ((int) $item['quantity'] === 0): ?>
                                            <div class="text-warning" style="font-size:.7rem;">
                                                <i class="fa-solid fa-eye-slash me-1"></i>fora da busca
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted" style="font-size:.75rem;">
                                        <?= date('d/m/Y H:i', strtotime((string) $item['created_at'])) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="<?= url('admin/estoque/item/excluir') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('Excluir <?= e($item['brand']) ?> <?= e($item['product']) ?> do estoque de <?= e($nomeExibicao) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Excluir esta peça">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted">Página <?= $page ?> de <?= $pages ?></small>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <?php
                                $q = $search !== '' ? 'busca=' . urlencode($search) . '&' : '';
                                $ini = max(1, $page - 4);
                                $fim = min($pages, $ini + 9);
                                ?>
                                <?php for ($p = $ini; $p <= $fim; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= url("admin/estoque/{$owner['id']}?{$q}pagina={$p}") ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coluna lateral -->
        <div class="col-lg-4">
            <!-- Incluir uma peça avulsa -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h6 class="fw-bold text-uppercase mb-3">
                    <i class="fa-solid fa-plus text-success me-2"></i>Incluir uma peça
                </h6>
                <form action="<?= url('admin/estoque/item/adicionar') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $owner['id'] ?>">

                    <div class="row g-2">
                        <div class="col-5">
                            <input type="text" name="brand" class="form-control form-control-sm" placeholder="Marca" maxlength="100">
                        </div>
                        <div class="col-7">
                            <input type="text" name="product" class="form-control form-control-sm" placeholder="Código *" maxlength="255" required>
                        </div>
                        <div class="col-5">
                            <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="0" placeholder="Qtde">
                        </div>
                        <div class="col-7">
                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">
                                <i class="fa-solid fa-plus me-1"></i> Incluir
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Enviar planilha em nome do fornecedor -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h6 class="fw-bold text-uppercase mb-3">
                    <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Enviar em nome dele
                </h6>
                <p class="text-muted text-small">
                    Sobe a planilha como se o próprio fornecedor tivesse enviado.
                    O estoque anterior é substituído.
                </p>
                <form action="<?= url('admin/estoque/enviar') ?>" method="POST" enctype="multipart/form-data"
                      onsubmit="return confirm('Substituir todo o estoque de <?= e($nomeExibicao) ?> por esta planilha?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $owner['id'] ?>">
                    <input type="file" name="file" class="form-control form-control-sm mb-2" accept=".xlsx,.csv" required>
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                        <i class="fa-solid fa-upload me-1"></i> Importar planilha
                    </button>
                </form>
            </div>

            <!-- Histórico de cargas -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-uppercase m-0">
                        <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>Cargas
                    </h6>
                    <span class="badge bg-light text-muted border"><?= count($batches) ?></span>
                </div>

                <?php if (empty($batches)): ?>
                    <p class="text-muted text-small m-0">Nenhum envio registrado.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush text-small">
                        <?php foreach ($batches as $b): ?>
                            <?php $noAr = (int) $b['itens_vinculados'] > 0; ?>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center gap-2">
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-bold text-truncate" style="max-width: 170px;"><?= e($b['filename']) ?></div>
                                    <div class="text-muted" style="font-size:.7rem;">
                                        <?= date('d/m/Y H:i', strtotime((string) $b['created_at'])) ?>
                                        <?php if ($noAr): ?>
                                            &bull; <?= number_format((int) $b['itens_vinculados'], 0, ',', '.') ?> itens
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <?php if ($noAr): ?>
                                        <span class="badge bg-success-subtle text-success">No ar</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-muted">Antiga</span>
                                    <?php endif; ?>
                                    <form action="<?= url('admin/estoque/lote/excluir') ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('<?= $noAr
                                              ? 'ATENÇÃO: esta carga está no ar. Excluir vai remover ' . (int) $b['itens_vinculados'] . ' peça(s) do estoque publicado. Continuar?'
                                              : 'Remover este registro do histórico?' ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 px-2" title="Excluir carga">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($removable > 0): ?>
                        <form action="<?= url('admin/estoque/historico/limpar') ?>" method="POST" class="mt-3 pt-3 border-top"
                              onsubmit="return confirm('Remover <?= $removable ?> carga(s) antiga(s) do histórico?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $owner['id'] ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100 fw-bold">
                                <i class="fa-solid fa-broom me-1"></i> Limpar histórico (<?= $removable ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Zona de risco -->
            <?php if ($totalGeral > 0): ?>
                <div class="card border-danger border-2 shadow-sm rounded-4 p-4">
                    <h6 class="fw-bold text-uppercase text-danger mb-2">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Tirar estoque do ar
                    </h6>
                    <p class="text-muted text-small">
                        Remove as <strong><?= number_format($totalGeral, 0, ',', '.') ?> peça(s)</strong> deste
                        fornecedor da busca pública. A conta e a licença continuam ativas —
                        ele pode subir uma planilha nova a qualquer momento.
                    </p>
                    <form action="<?= url('admin/estoque/limpar') ?>" method="POST"
                          onsubmit="return confirm('Remover TODO o estoque de <?= e($nomeExibicao) ?> (<?= number_format($totalGeral, 0, ',', '.') ?> peças) da busca pública?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $owner['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">
                            <i class="fa-solid fa-eraser me-1"></i> Esvaziar estoque
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
