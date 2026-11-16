<?php
$title = 'Meu Estoque Ativo - StoqueOnline';
$retornoBusca = $search;
$retornoPagina = $page;

/**
 * Painéis de foto de cada peça.
 *
 * São montados dentro do laço da tabela, mas impressos depois dela: um
 * elemento de modal colocado dentro de <tr> ou <td> herda o display da
 * linha, e o Bootstrap só consegue escurecer o fundo — o conteúdo nunca
 * chega a aparecer.
 */
$modaisDeFoto = [];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Meu Estoque Ativo</h2>
            <p class="text-muted m-0 text-small">
                Ajuste quantidades, inclua ou remova peças sem precisar reenviar a planilha.
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($totalGeral > 0): ?>
                <a href="<?= url('estoque/exportar') ?>" class="btn btn-outline-success btn-sm fw-bold px-3">
                    <i class="fa-solid fa-file-csv me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
            <a href="<?= url('estoque/upload') ?>" class="btn btn-primary btn-sm fw-bold px-3">
                <i class="fa-solid fa-cloud-arrow-up me-1"></i> Enviar Planilha
            </a>
        </div>
    </div>

    <?php if (!$podeEditar): ?>
        <div class="alert alert-warning d-flex align-items-center shadow-sm">
            <i class="fa-solid fa-lock fs-4 me-3"></i>
            <div class="flex-grow-1">
                Seu plano não está ativo. Você consegue consultar o estoque, mas para alterá-lo
                é preciso ter uma assinatura vigente.
            </div>
            <a href="<?= url('planos') ?>" class="btn btn-warning btn-sm fw-bold">Ver planos</a>
        </div>
    <?php endif; ?>

    <?php if ($esgotados > 0): ?>
        <div class="alert alert-secondary d-flex align-items-center shadow-sm text-small">
            <i class="fa-solid fa-circle-info fs-5 me-3"></i>
            <div>
                <strong><?= number_format($esgotados, 0, ',', '.') ?> peça(s) com quantidade zero.</strong>
                Elas continuam no seu catálogo, mas não aparecem para quem pesquisa.
                Basta lançar a quantidade de volta para elas voltarem ao ar.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($podeEditar): ?>
        <!-- Inclusão de peça avulsa -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-uppercase mb-3">
                    <i class="fa-solid fa-plus text-success me-2"></i>Incluir uma peça
                </h6>
                <form action="<?= url('estoque/item/adicionar') ?>" method="POST" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                    <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">

                    <div class="col-md-3">
                        <label class="form-label text-small fw-bold">Marca</label>
                        <input type="text" name="brand" class="form-control" placeholder="Ex: SKF" maxlength="100">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label text-small fw-bold">Código da peça <span class="text-danger">*</span></label>
                        <input type="text" name="product" class="form-control" placeholder="Ex: 6205-2RSH/C3" maxlength="255" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-small fw-bold">Quantidade</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="0" max="99999999">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100 fw-bold">
                            <i class="fa-solid fa-plus me-1"></i> Incluir
                        </button>
                    </div>
                </form>
                <div class="form-text text-small mt-2">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                    Ao enviar uma planilha nova, todo o estoque é substituído — inclusive as peças
                    incluídas aqui.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
            <span class="fw-bold text-dark fs-6">
                <?= number_format($total, 0, ',', '.') ?> item(ns)
                <?= $search !== '' ? 'encontrado(s) para "' . e($search) . '"' : 'cadastrado(s)' ?>
            </span>

            <form action="<?= url('estoque/meu-estoque') ?>" method="GET" class="d-flex gap-2" style="max-width: 360px;">
                <input type="text" name="busca" value="<?= e($search) ?>" class="form-control form-control-sm"
                       placeholder="Buscar código no meu estoque...">
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= url('estoque/meu-estoque') ?>" class="btn btn-sm btn-outline-secondary" title="Limpar busca">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabela-cartoes">
                <thead class="table-light text-uppercase fs-8 text-muted">
                    <tr>
                        <th class="ps-4" style="width: 90px;">Foto</th>
                        <th>Marca</th>
                        <th>Código do Rolamento</th>
                        <th class="text-center" style="width: 190px;">Quantidade</th>
                        <th>Situação</th>
                        <?php if ($podeEditar): ?>
                            <th class="text-end pe-4" style="width: 80px;">Excluir</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="<?= $podeEditar ? 6 : 5 ?>" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open fs-1 text-muted mb-2 opacity-50 d-block"></i>
                                <?php if ($search !== ''): ?>
                                    Nenhuma peça encontrada para "<?= e($search) ?>".
                                <?php else: ?>
                                    <div>Nenhum item em estoque no momento.</div>
                                    <a href="<?= url('estoque/upload') ?>" class="btn btn-sm btn-outline-primary mt-2">
                                        Enviar planilha
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                        $esgotado = (int) $item['quantity'] === 0;
                        $chaveFoto = \App\Models\ProductPhoto::chave(
                            (int) $item['user_id'],
                            (string) $item['product_search'],
                            (string) $item['brand']
                        );
                        $fotosDoItem = $fotos[$chaveFoto] ?? [];
                        $principal = $fotosDoItem[0] ?? null;
                        ?>
                        <tr class="<?= $esgotado ? 'table-light' : '' ?>">
                            <td class="ps-4" data-rotulo="">
                                <?php if ($principal): ?>
                                    <button type="button" class="btn p-0 border-0 bg-transparent position-relative"
                                            data-bs-toggle="modal" data-bs-target="#modalFotos<?= (int) $item['id'] ?>"
                                            title="Ver e gerenciar as fotos">
                                        <img src="<?= e(\App\Services\ImageService::url((int) $item['user_id'], (string) $principal['thumbname'])) ?>"
                                             alt="<?= e($item['brand'] . ' ' . $item['product']) ?>"
                                             class="rounded-3 border object-fit-cover"
                                             style="width: 56px; height: 56px;" loading="lazy">
                                        <?php if (count($fotosDoItem) > 1): ?>
                                            <span class="badge bg-dark position-absolute top-0 start-100 translate-middle"
                                                  style="font-size:.6rem;"><?= count($fotosDoItem) ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php elseif ($podeEditar): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary border-dashed"
                                            data-bs-toggle="modal" data-bs-target="#modalFotos<?= (int) $item['id'] ?>"
                                            title="Adicionar foto" style="width: 56px; height: 56px;">
                                        <i class="fa-solid fa-camera text-muted"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted"><i class="fa-solid fa-image opacity-25 fs-4"></i></span>
                                <?php endif; ?>
                            </td>
                            <td data-rotulo="Marca">
                                <span class="badge bg-secondary-subtle text-dark"><?= e($item['brand']) ?></span>
                            </td>
                            <td class="fw-bold <?= $esgotado ? 'text-muted' : 'text-primary' ?>" data-rotulo="Código">
                                <?= e($item['product']) ?>
                            </td>

                            <td class="text-center" data-rotulo="Quantidade">
                                <?php if ($podeEditar): ?>
                                    <!-- Ajuste direto da quantidade -->
                                    <form action="<?= url('estoque/item/quantidade') ?>" method="POST"
                                          class="d-flex gap-1 justify-content-center form-quantidade">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                                        <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">

                                        <input type="number" name="quantity" class="form-control form-control-sm text-center"
                                               value="<?= (int) $item['quantity'] ?>" min="0" max="99999999"
                                               style="width: 100px;"
                                               data-valor-original="<?= (int) $item['quantity'] ?>">

                                        <!-- Sem JavaScript o botão continua funcionando normalmente;
                                             o script apenas o desativa enquanto nada mudou. -->
                                        <button type="submit" class="btn btn-sm btn-outline-success btn-salvar-quantidade"
                                                title="Salvar a nova quantidade">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success fs-7 fw-bold">
                                        <?= number_format((int) $item['quantity'], 0, ',', '.') ?> un
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td data-rotulo="Situação">
                                <?php if ($esgotado): ?>
                                    <span class="badge bg-warning-subtle text-warning">
                                        <i class="fa-solid fa-eye-slash me-1"></i>Esgotada
                                    </span>
                                    <div class="text-muted" style="font-size:.7rem;">fora da busca</div>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="fa-solid fa-circle-check me-1"></i>No ar
                                    </span>
                                <?php endif; ?>
                            </td>

                            <?php if ($podeEditar): ?>
                                <td class="text-end pe-4" data-rotulo="">
                                    <form action="<?= url('estoque/item/excluir') ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('Remover <?= e($item['brand']) ?> <?= e($item['product']) ?> do seu estoque?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                                        <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0"
                                                title="Excluir esta peça">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>

                        <?php
                        // O painel de fotos é montado aqui, mas guardado para
                        // ser impresso depois do fim da tabela: um modal dentro
                        // de <tr> herda o display da linha e nunca aparece.
                        if ($podeEditar || $principal):
                            ob_start();
                        ?>
                                <div class="modal fade" id="modalFotos<?= (int) $item['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-light border-0">
                                                <div>
                                                    <h5 class="modal-title fw-bold">
                                                        <?= e($item['brand']) ?> <?= e($item['product']) ?>
                                                    </h5>
                                                    <small class="text-muted">
                                                        <?= count($fotosDoItem) ?> de <?= $maxFotos ?> fotos
                                                    </small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body p-4">
                                                <?php if (empty($fotosDoItem)): ?>
                                                    <p class="text-muted text-center py-3 m-0">
                                                        <i class="fa-solid fa-camera fs-1 d-block mb-2 opacity-25"></i>
                                                        Nenhuma foto cadastrada para esta peça.
                                                    </p>
                                                <?php else: ?>
                                                    <div class="row g-3">
                                                        <?php foreach ($fotosDoItem as $indice => $foto): ?>
                                                            <?php $urlGrande = \App\Services\ImageService::url((int) $foto['user_id'], (string) $foto['filename']); ?>
                                                            <div class="col-4">
                                                                <div class="border rounded-3 overflow-hidden position-relative">
                                                                    <img src="<?= e(\App\Services\ImageService::url((int) $foto['user_id'], (string) $foto['thumbname'])) ?>"
                                                                         alt="Foto <?= $indice + 1 ?>"
                                                                         class="w-100 object-fit-cover zoom-abrir"
                                                                         style="height: 150px; cursor: zoom-in;"
                                                                         data-zoom-src="<?= e($urlGrande) ?>"
                                                                         data-zoom-titulo="<?= e($item['brand'] . ' ' . $item['product']) ?>">

                                                                    <?php if ($indice === 0): ?>
                                                                        <span class="badge bg-primary position-absolute top-0 start-0 m-1">principal</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if ($podeEditar): ?>
                                                                    <div class="d-flex gap-1 mt-1">
                                                                        <?php if ($indice !== 0): ?>
                                                                            <form action="<?= url('estoque/foto/principal') ?>" method="POST" class="flex-grow-1">
                                                                                <?= csrf_field() ?>
                                                                                <input type="hidden" name="photo_id" value="<?= (int) $foto['id'] ?>">
                                                                                <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                                                                                <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">
                                                                                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Usar na busca">
                                                                                    <i class="fa-solid fa-star"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php endif; ?>

                                                                        <form action="<?= url('estoque/foto/excluir') ?>" method="POST"
                                                                              class="<?= $indice === 0 ? 'flex-grow-1' : '' ?>"
                                                                              onsubmit="return confirm('Excluir esta foto?');">
                                                                            <?= csrf_field() ?>
                                                                            <input type="hidden" name="photo_id" value="<?= (int) $foto['id'] ?>">
                                                                            <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                                                                            <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="Excluir foto">
                                                                                <i class="fa-solid fa-trash-can"></i>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($podeEditar && count($fotosDoItem) < $maxFotos): ?>
                                                    <form action="<?= url('estoque/foto/enviar') ?>" method="POST"
                                                          enctype="multipart/form-data" class="mt-4 pt-3 border-top">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                        <input type="hidden" name="retorno_busca" value="<?= e($retornoBusca) ?>">
                                                        <input type="hidden" name="retorno_pagina" value="<?= (int) $retornoPagina ?>">

                                                        <label class="form-label text-small fw-bold">Enviar nova foto</label>
                                                        <div class="input-group">
                                                            <input type="file" name="foto" class="form-control"
                                                                   accept="image/jpeg,image/png,image/webp" required capture="environment">
                                                            <button type="submit" class="btn btn-success fw-bold">
                                                                <i class="fa-solid fa-upload me-1"></i> Enviar
                                                            </button>
                                                        </div>
                                                        <div class="form-text text-small">
                                                            JPG, PNG ou WebP até 12 MB. A imagem é reduzida e comprimida
                                                            automaticamente. Fotos tiradas de lado são corrigidas sozinhas.
                                                        </div>
                                                    </form>
                                                <?php elseif ($podeEditar): ?>
                                                    <div class="alert alert-secondary text-small mt-4 mb-0">
                                                        Limite de <?= $maxFotos ?> fotos por peça atingido.
                                                        Exclua uma para enviar outra.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            $modaisDeFoto[] = ob_get_clean();
                        endif;
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
                <small class="text-muted">Página <?= $page ?> de <?= $pages ?></small>
                <nav>
                    <ul class="pagination pagination-sm m-0">
                        <?php
                        $queryBase = $search !== '' ? 'busca=' . urlencode($search) . '&' : '';
                        $firstPage = max(1, $page - 4);
                        $lastPage = min($pages, $firstPage + 9);
                        ?>
                        <?php for ($p = $firstPage; $p <= $lastPage; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url("estoque/meu-estoque?{$queryBase}pagina={$p}") ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Painéis de foto, fora da tabela para que o Bootstrap consiga exibi-los
foreach ($modaisDeFoto as $modal) {
    echo $modal;
}
?>
