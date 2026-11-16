<?php $title = 'Carregar Estoque - StoqueOnline'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Carregar Estoque em Excel</h2>
            <p class="text-muted m-0 text-small">Envie sua planilha com o estoque atualizado para ficar visível na rede de distribuidores.</p>
        </div>
        <div>
            <a href="<?= url('estoque/modelo-download') ?>" class="btn btn-outline-success btn-sm fw-bold px-3">
                <i class="fa-solid fa-file-arrow-down me-1"></i> Baixar Planilha Modelo
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Coluna de Upload -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-excel text-success me-2"></i>Enviar Nova Planilha</h5>
                
                <form action="<?= url('estoque/upload') ?>" method="POST" enctype="multipart/form-data" id="formUploadEstoque">
                    <?= csrf_field() ?>

                    <div class="upload-dropzone p-5 border border-2 border-dashed rounded-4 text-center bg-light position-relative mb-3">
                        <i class="fa-solid fa-file-arrow-up display-4 text-muted mb-3 opacity-50"></i>
                        <h6 class="fw-bold">Arraste e solte seu arquivo aqui</h6>
                        <p class="text-muted text-small mb-3">Formatos permitidos: <strong>.xlsx</strong> ou <strong>.csv</strong></p>
                        <input type="file" name="file" id="inputArquivoEstoque" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".xlsx, .csv, .xls" required>
                        <button type="button" class="btn btn-primary px-4 fw-bold">Selecionar Arquivo</button>
                    </div>

                    <div id="nomeArquivoSelecionado" class="d-none alert alert-info py-2 px-3 small d-flex align-items-center mb-3">
                        <i class="fa-solid fa-check text-success me-2"></i> Arquivo selecionado: <strong class="ms-1" id="labelNomeArquivo"></strong>
                    </div>

                    <div class="alert alert-warning py-2 text-small d-flex align-items-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                        <div>
                            Ao carregar uma nova planilha, o estoque da carga anterior será <strong>automaticamente substituído</strong> para garantir que seu estoque esteja 100% atualizado.
                        </div>
                    </div>

                    <button type="submit" id="btnEnviarEstoque" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                        <i class="fa-solid fa-upload me-2"></i> IMPORTAR E ATUALIZAR ESTOQUE
                    </button>
                </form>
            </div>

            <!-- Instruções da Planilha -->
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h6 class="fw-bold text-uppercase mb-3"><i class="fa-solid fa-circle-info text-primary me-2"></i>Estrutura Obrigatória da Planilha</h6>
                <p class="text-muted text-small">Sua planilha deve conter <strong>3 colunas</strong> na primeira linha, exatamente nesta ordem:</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center text-small">
                        <thead class="table-light">
                            <tr>
                                <th>Coluna 1 (Marca)</th>
                                <th>Coluna 2 (Produto)</th>
                                <th>Coluna 3 (Quantidade)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-muted">SKF</td>
                                <td class="fw-bold">6205-2RSH/C3</td>
                                <td class="text-success fw-bold">25</td>
                            </tr>
                            <tr>
                                <td class="text-muted">NSK</td>
                                <td class="fw-bold">6004 DDU</td>
                                <td class="text-success fw-bold">10</td>
                            </tr>
                            <tr>
                                <td class="text-muted">TIMKEN</td>
                                <td class="fw-bold">32210</td>
                                <td class="text-success fw-bold">8</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Coluna de Status e Histórico -->
        <div class="col-lg-5">
            <!-- Card de Resumo -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 text-small text-uppercase fw-bold">Seu Estoque Ativo</div>
                        <h2 class="fw-extrabold m-0 text-white"><?= number_format($totalItems, 0, ',', '.') ?></h2>
                        <div class="text-white-50 text-small mt-1">peças cadastradas na rede</div>
                    </div>
                    <div class="p-3 bg-white bg-opacity-25 rounded-circle">
                        <i class="fa-solid fa-boxes-stacked fs-2"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-top border-white border-opacity-25">
                    <a href="<?= url('estoque/meu-estoque') ?>" class="btn btn-light btn-sm w-100 fw-bold text-primary">
                        <i class="fa-solid fa-list-check me-1"></i> Visualizar Meus Itens em Estoque
                    </a>
                </div>
            </div>

            <!-- Histórico de Cargas -->
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-uppercase m-0">
                        <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>Histórico de Cargas
                    </h6>
                    <?php if ($totalBatches > 0): ?>
                        <span class="badge bg-light text-muted border"><?= $totalBatches ?> envio(s)</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($batches)): ?>
                    <p class="text-muted text-small m-0">Nenhuma planilha foi importada até o momento.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush text-small">
                        <?php foreach ($batches as $batch): ?>
                            <?php $noAr = (int) $batch['itens_vinculados'] > 0; ?>
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center gap-2">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-bold text-truncate" style="max-width: 200px;"><?= e($batch['filename']) ?></div>
                                    <?php
                                    // Cargas migradas da v1 não trazem o total gravado;
                                    // para a que está no ar vale a contagem real.
                                    $itens = $noAr
                                        ? (int) $batch['itens_vinculados']
                                        : (int) $batch['total_items'];
                                    ?>
                                    <div class="text-muted fs-8">
                                        <?= date('d/m/Y \à\s H:i', strtotime((string) $batch['created_at'])) ?>
                                        <?php if ($itens > 0): ?>
                                            &bull; <?= number_format($itens, 0, ',', '.') ?> itens
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <?php if ($noAr): ?>
                                        <span class="badge bg-success-subtle text-success" title="Estoque publicado neste momento">
                                            <i class="fa-solid fa-circle-check me-1"></i>No ar
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-muted" title="Carga substituída por um envio mais novo">
                                            Substituída
                                        </span>

                                        <form action="<?= url('estoque/lote/excluir') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('Remover este envio do histórico?\n\nO estoque que está publicado não será alterado.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 px-2"
                                                    title="Remover do histórico">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($totalBatches > count($batches)): ?>
                        <p class="text-muted fs-8 mt-2 mb-0">
                            Mostrando os <?= count($batches) ?> envios mais recentes de <?= $totalBatches ?>.
                        </p>
                    <?php endif; ?>

                    <?php if ($removableBatches > 0): ?>
                        <form action="<?= url('estoque/historico/limpar') ?>" method="POST" class="mt-3 pt-3 border-top"
                              onsubmit="return confirm('Remover <?= $removableBatches ?> envio(s) antigo(s) do histórico?\n\nO estoque publicado hoje continua intacto.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100 fw-bold">
                                <i class="fa-solid fa-broom me-1"></i>
                                Limpar histórico (<?= $removableBatches ?> antigo<?= $removableBatches > 1 ? 's' : '' ?>)
                            </button>
                        </form>
                    <?php endif; ?>

                    <p class="text-muted fs-8 mt-3 mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Cada envio novo substitui o estoque anterior. As cargas marcadas como
                        <strong>Substituída</strong> são apenas registro e podem ser apagadas.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
