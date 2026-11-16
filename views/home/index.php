<?php $title = 'StoqueOnline - Encontre e Divulgue Estoques de Rolamentos no Brasil'; ?>

<!-- Hero Section -->
<section class="hero-section py-5 text-white position-relative">
    <div class="container py-lg-5 position-relative" style="z-index: 2;">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 mb-4 rounded-pill border border-primary border-opacity-25" style="background: var(--accent-soft);">
                    <span class="badge bg-primary rounded-pill px-2 py-1 fs-8 text-uppercase">Rede B2B</span>
                    <span class="text-white-50 text-small fw-medium">Conectando distribuidores e indústrias em todo o Brasil</span>
                </div>

                <h1 class="display-4 fw-extrabold text-white mb-3" style="letter-spacing: -0.03em; line-height: 1.15;">
                    Encontre o rolamento certo <span style="background: linear-gradient(135deg, var(--accent-hover) 0%, var(--cyan) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">em segundos.</span>
                </h1>
                
                <p class="lead text-white-50 mb-4 fs-6" style="max-width: 580px; line-height: 1.6;">
                    Consulte estoques em tempo real de SKF, NSK, TIMKEN, FAG, NTN e líderes mundiais. Reduza o estoque parado da sua empresa e contate fornecedores diretamente via WhatsApp.
                </p>

                <!-- Caixa de Pesquisa Rápida Elevada -->
                <div class="hero-search-box p-3 shadow-lg mb-4">
                    <form action="<?= url('pesquisa') ?>" method="GET" class="d-flex flex-column flex-sm-row gap-2">
                        <div class="input-group input-group-lg border-0">
                            <span class="input-group-text bg-transparent border-0 text-primary ps-3">
                                <i class="fa-solid fa-magnifying-glass fs-5"></i>
                            </span>
                            <input type="text" name="q" class="form-control border-0 bg-transparent fs-6" placeholder="Digite o código da peça (ex: 6205, 32210, 22218, HK2016...)" required autofocus style="box-shadow: none;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold text-nowrap rounded-3">
                            <i class="fa-solid fa-magnifying-glass me-2"></i> BUSCAR ESTOQUE
                        </button>
                    </form>
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-2 border-top text-muted small ps-2">
                        <span class="fw-bold text-dark fs-8 text-uppercase"><i class="fa-solid fa-fire text-warning me-1"></i> Mais buscados:</span>
                        <a href="<?= url('pesquisa?q=6205') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">6205</a>
                        <a href="<?= url('pesquisa?q=6204') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">6204</a>
                        <a href="<?= url('pesquisa?q=6004') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">6004</a>
                        <a href="<?= url('pesquisa?q=32210') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">32210</a>
                        <a href="<?= url('pesquisa?q=22218') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">22218</a>
                        <a href="<?= url('pesquisa?q=22220') ?>" class="badge bg-light text-secondary border text-decoration-none px-2 py-1 rounded-pill">22220</a>
                    </div>
                </div>

                <!-- Marcas em Destaque no Hero -->
                <div class="d-flex align-items-center gap-3 text-white-50 fs-8">
                    <span class="text-uppercase fw-bold text-secondary">Principais Marcas:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">SKF</span>
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">NSK</span>
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">TIMKEN</span>
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">FAG</span>
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">NTN</span>
                        <span class="badge border border-secondary text-light bg-dark bg-opacity-50 px-2 py-1">INA</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="p-4 p-md-5 rounded-4 shadow-2xl" style="background: var(--bg-surface); backdrop-filter: blur(20px); border: 1px solid var(--border-card);">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-25">
                        <h5 class="fw-bold text-white m-0">
                            <i class="fa-solid fa-chart-simple text-primary me-2"></i> Rede StoqueOnline
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2 py-1 fs-8">
                            <i class="fa-solid fa-circle text-success fs-9 me-1"></i> Ao Vivo
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3 stat-card-dark text-start">
                                <div class="text-primary mb-2"><i class="fa-solid fa-boxes-stacked fs-4"></i></div>
                                <h3 class="fw-extrabold text-white m-0"><?= number_format($stats['total_items'], 0, ',', '.') ?></h3>
                                <div class="text-white-50 fs-8 mt-1">Linhas no Catálogo</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 stat-card-dark text-start">
                                <div class="text-info mb-2"><i class="fa-solid fa-cubes-stacked fs-4"></i></div>
                                <h3 class="fw-extrabold text-white m-0"><?= number_format($stats['total_pieces'], 0, ',', '.') ?></h3>
                                <div class="text-white-50 fs-8 mt-1">Peças em Estoque</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 stat-card-dark text-start">
                                <div class="text-success mb-2"><i class="fa-solid fa-building-circle-check fs-4"></i></div>
                                <h3 class="fw-extrabold text-white m-0"><?= number_format($stats['total_suppliers'], 0, ',', '.') ?></h3>
                                <div class="text-white-50 fs-8 mt-1">Distribuidores Ativos</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 stat-card-dark text-start">
                                <div class="text-warning mb-2"><i class="fa-solid fa-bolt fs-4"></i></div>
                                <h3 class="fw-extrabold text-white m-0">100%</h3>
                                <div class="text-white-50 fs-8 mt-1">Negociação Direta</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2">
                        <a href="<?= url('cadastro') ?>" class="btn btn-outline-light w-100 fw-bold py-2 rounded-3 border-secondary hover-white">
                            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Divulgar o Estoque da Minha Empresa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Como Funciona -->
<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase">Passo a Passo</span>
            <h2 class="fw-bold">Como Funciona o StoqueOnline?</h2>
            <p class="text-muted">Simples, rápido e sem burocracia para quem compra e quem vende.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="step-icon mb-3 mx-auto bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-file-excel fs-3"></i>
                    </div>
                    <h5 class="fw-bold">1. Suba seu Estoque em Excel</h5>
                    <p class="text-muted text-small">
                        Exporte seu estoque do seu ERP em formato Excel ou CSV (Marca, Produto, Quantidade) e faça o upload em segundos.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="step-icon mb-3 mx-auto bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-magnifying-glass-location fs-3"></i>
                    </div>
                    <h5 class="fw-bold">2. Compradores Buscam a Peça</h5>
                    <p class="text-muted text-small">
                        Indústrias, revendas e oficinas pesquisam o código e veem que a sua empresa possui as peças disponíveis imediatamente.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="step-icon mb-3 mx-auto bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                        <i class="fa-brands fa-whatsapp fs-3"></i>
                    </div>
                    <h5 class="fw-bold">3. Negociação Direta via WhatsApp</h5>
                    <p class="text-muted text-small">
                        O cliente clica no botão do WhatsApp e inicia a conversa com a sua equipe de vendas com os dados da peça já preenchidos.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Planos em Destaque -->
<section class="py-5 bg-light border-top">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase">Planos Flexíveis</span>
            <h2 class="fw-bold">Planos Que Cabem na Sua Operação</h2>
            <p class="text-muted">Comece com 45 dias de teste grátis sem compromisso.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($plans as $plan): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-4 <?= !empty($plan['is_free_trial']) ? 'border-primary border-2 shadow' : '' ?>">
                        <?php if (!empty($plan['is_free_trial'])): ?>
                            <span class="badge bg-primary position-absolute top-0 start-50 translate-middle px-3 py-2 rounded-pill">MAIS POPULAR</span>
                        <?php endif; ?>
                        <h5 class="fw-bold mt-2"><?= e($plan['name']) ?></h5>
                        <div class="my-3">
                            <span class="fs-6 text-muted">R$</span>
                            <span class="display-6 fw-bold"><?= number_format($plan['price'], 2, ',', '.') ?></span>
                            <div class="text-muted text-small"><?= e($plan['period_name']) ?></div>
                        </div>
                        <ul class="list-unstyled text-small text-muted my-3 space-y-2">
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Até <strong><?= $plan['max_users'] ?></strong> usuário(s)</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Upload ilimitado de estoque</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Contato direto via WhatsApp</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> <?= e($plan['description']) ?></li>
                        </ul>
                        <div class="mt-auto pt-3">
                            <a href="<?= url('planos') ?>" class="btn <?= !empty($plan['is_free_trial']) ? 'btn-primary' : 'btn-outline-primary' ?> w-100 fw-bold">
                                <?= !empty($plan['is_free_trial']) ? 'Testar 45 Dias Grátis' : 'Contratar Plano' ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Perguntas Frequentes (FAQ) -->
<section class="py-5 bg-white border-top">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase">Tire Suas Dúvidas</span>
            <h2 class="fw-bold">Perguntas Frequentes</h2>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion shadow-sm rounded-4 overflow-hidden border-0" id="accordionFaq">
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Posso apenas consultar os estoques sem vender?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
                            <div class="accordion-body text-muted">
                                Sim! Durante o período de teste de 45 dias ou com qualquer licença ativa, você terá acesso total à base de distribuidores cadastrados em nossa plataforma para consultar e contatar fornecedores.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Sou obrigado a divulgar o estoque da minha empresa?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
                            <div class="accordion-body text-muted">
                                Não é obrigatório. O objetivo do StoqueOnline é dar visibilidade a peças que você talvez tenha paradas em prateleiras para gerar novas vendas, mas você pode optar apenas por consultar se desejar.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Como funciona o teste gratuito de 45 dias?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
                            <div class="accordion-body text-muted">
                                Ao criar a sua conta pela primeira vez, você tem direito a 45 dias de degustação completa com todas as funcionalidades liberadas (consulta e upload de estoque), sem cobrança prévia ou cartão de crédito.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
