<?php $title = 'Pesquisa de Estoque de Rolamentos - StoqueOnline'; ?>

<div class="container py-4">
    <!-- Cabeçalho da Página -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i>Consulta de Estoques</h2>
            <p class="text-muted m-0 text-small">Pesquise por código do rolamento, marca e localize fornecedores com pronta entrega.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <span class="badge bg-primary-subtle text-primary p-2 fs-7">
                <i class="fa-solid fa-bolt me-1"></i> Busca Rápida em Tempo Real
            </span>
        </div>
    </div>

    <!-- Formulário de Filtros -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form id="formSearch" class="row g-3">
                <div class="col-lg-6 col-md-12">
                    <label class="form-label fw-bold text-small">Código da Peça / Palavra-chave <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-barcode"></i></span>
                        <input type="text" id="palavraChave" class="form-control border-start-0" placeholder="Ex: 6205, 23048, 22218 CC" value="<?= e($initialKeyword) ?>" autocomplete="off" autofocus>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold text-small" for="marca">Marca</label>
                    <input type="text" id="marca" class="form-control form-control-lg" placeholder="Ex: SKF, NSK, TIMKEN..." list="listaMarcas" autocomplete="off">
                    <datalist id="listaMarcas">
                        <?php foreach ($marcas as $marcaSugerida): ?>
                            <option value="<?= e($marcaSugerida) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold text-small" for="quantidadeMinima">Quantidade Mínima</label>
                    <input type="number" id="quantidadeMinima" class="form-control form-control-lg" placeholder=">= 1" min="1">
                </div>

                <!-- Incluindo / Excluindo à vista, como na versão anterior -->
                <div class="col-md-6">
                    <label class="form-label fw-bold text-small" for="incluindo">Incluindo</label>
                    <input type="text" id="incluindo" class="form-control" placeholder="Ex: K, 2RS, C3, W33" autocomplete="off">
                    <div class="form-text">O código precisa ter também este texto. Ex.: código <strong>23048</strong> incluindo <strong>K</strong> acha 23048 CAK, 23048 KMB...</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-small" for="excluindo">Excluindo</label>
                    <input type="text" id="excluindo" class="form-control" placeholder="Ex: ZZ, NR" autocomplete="off">
                    <div class="form-text">Tira da lista os códigos que tenham este texto.</div>
                </div>

                <!-- Tipo de Busca por Código com Segmented Pills -->
                <div class="col-12">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-3 rounded-3" style="background: var(--bg-surface-2); border: 1px solid var(--border-subtle);">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold text-small text-dark me-2"><i class="fa-solid fa-filter me-1 text-primary"></i> Modo de Busca:</span>
                            <div class="btn-group modo-busca" role="group">
                                <input type="radio" class="btn-check" name="tipoBusca" id="buscaComeca" value="comeca" <?= $initialMode === 'comeca' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary btn-sm px-3 fw-bold" for="buscaComeca">
                                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Começa com
                                </label>

                                <input type="radio" class="btn-check" name="tipoBusca" id="buscaContem" value="contem" <?= $initialMode === 'contem' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary btn-sm px-3 fw-bold" for="buscaContem">
                                    <i class="fa-solid fa-asterisk me-1"></i> Contém
                                </label>

                                <input type="radio" class="btn-check" name="tipoBusca" id="buscaExata" value="exata" <?= $initialMode === 'exata' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary btn-sm px-3 fw-bold" for="buscaExata">
                                    <i class="fa-solid fa-bullseye me-1"></i> Busca Exata
                                </label>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 filtro-rapido">
                            <span class="text-small text-muted d-none d-lg-inline">Filtro rápido:</span>
                            <div class="d-flex gap-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-light border fw-medium quick-brand" data-brand="SKF">SKF</button>
                                <button type="button" class="btn btn-sm btn-light border fw-medium quick-brand" data-brand="NSK">NSK</button>
                                <button type="button" class="btn btn-sm btn-light border fw-medium quick-brand" data-brand="TIMKEN">TIMKEN</button>
                                <button type="button" class="btn btn-sm btn-light border fw-medium quick-brand" data-brand="FAG">FAG</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2" id="explicacaoModo" aria-live="polite"></div>
                </div>

                <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-2">
                    <button type="button" id="btnLimpar" class="btn btn-outline-secondary px-4 fw-medium">
                        <i class="fa-solid fa-rotate-left me-1"></i> Limpar
                    </button>
                    <button type="submit" id="btnPesquisar" class="btn btn-primary px-5 fw-bold shadow-sm">
                        <i class="fa-solid fa-magnifying-glass me-2"></i> PESQUISAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Seção de Modelos / Variações Encontradas -->
    <div id="divModelosEncontrados" class="card border-0 shadow-sm rounded-4 p-3 mb-4 d-none bg-primary-subtle border-start border-primary border-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-1 mb-2">
            <span class="fw-bold text-primary"><i class="fa-solid fa-tags me-1"></i> Modelos Encontrados nesta busca:</span>
            <small class="text-muted">Clique em um modelo para filtrar rapidamente</small>
        </div>
        <div id="listaChipsModelos" class="d-flex flex-wrap gap-2"></div>
    </div>

    <!-- Indicador de Carregamento -->
    <div id="searchLoading" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Buscando...</span>
        </div>
        <div class="text-muted mt-2">Buscando estoques nos distribuidores parceiros...</div>
    </div>

    <!-- Tabela de Resultados -->
    <div id="divResultados" class="card border-0 shadow-sm rounded-4 overflow-hidden d-none">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
            <span class="fw-bold text-dark fs-6" id="totalResultadosTexto">Resultados Encontrados</span>
            <div class="d-flex align-items-center gap-2">
                <?php if (\App\Core\Auth::check()): ?>
                    <a href="#" id="btnExportar" class="btn btn-outline-success btn-sm fw-bold d-none">
                        <i class="fa-solid fa-file-csv me-1"></i> Exportar resultados
                    </a>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="btn btn-outline-secondary btn-sm fw-bold">
                        <i class="fa-solid fa-file-csv me-1"></i> Entre para exportar
                    </a>
                <?php endif; ?>
                <div id="paginacaoTopo" class="d-flex align-items-center gap-2"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabela-cartoes" id="tabelaEstoque">
                <thead class="table-light text-uppercase fs-8 text-muted">
                    <tr>
                        <th class="ps-4" style="width: 80px;">Foto</th>
                        <th>Marca</th>
                        <th>Código do Rolamento</th>
                        <th class="text-center">Quantidade</th>
                        <th>Distribuidor / Local</th>
                        <th class="text-end pe-4">Ação / Contato</th>
                    </tr>
                </thead>
                <tbody id="tabelaEstoqueBody" class="fs-7">
                    <!-- Linhas preenchidas via JavaScript -->
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top">
            <small class="text-muted" id="infoPaginacao"></small>
            <nav id="paginacaoControles"></nav>
        </div>
    </div>

    <!-- Estado Inicial / Sem Resultados -->
    <div id="divSemResultados" class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted <?= empty($initialKeyword) ? '' : 'd-none' ?>">
        <i class="fa-solid fa-magnifying-glass fs-1 text-muted mb-3 opacity-50"></i>
        <h5 class="fw-bold text-dark">Nenhuma busca realizada</h5>
        <p class="m-0">Digite um código de rolamento no campo acima para pesquisar entre centenas de milhares de peças.</p>
    </div>
</div>

<!-- Modal com Dados do Fornecedor -->
<div class="modal fade" id="modalFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="modalFornecedorTitulo">
                    <i class="fa-solid fa-building me-2 text-primary"></i> Detalhes do Distribuidor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalFornecedorCorpo">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Fechar</button>
                <a href="#" target="_blank" id="modalFornecedorBtnWhatsapp" class="btn btn-success btn-sm px-4 fw-bold">
                    <i class="fa-brands fa-whatsapp me-1"></i> Chamar no WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>
