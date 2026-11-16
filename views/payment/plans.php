<?php $title = 'Planos e Assinaturas - StoqueOnline'; ?>

<div class="container py-5">
    <div class="text-center mb-5">
        <span class="badge bg-primary-subtle text-primary px-3 py-2 text-uppercase fw-bold mb-2">Preços Transparentes</span>
        <h2 class="fw-extrabold display-5">Escolha o Plano Ideal Para Sua Empresa</h2>
        <p class="text-muted lead">Tenha acesso a estoques em todo o Brasil e divulgue suas peças para milhares de compradores.</p>

        <?php if ($canHaveFreeTrial): ?>
            <!-- Banner de Degustação Gratuita de 45 Dias -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg bg-gradient p-4 text-white text-center rounded-4" style="background: linear-gradient(135deg, var(--accent) 0%, var(--cyan) 100%);">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                            <div class="text-md-start">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-1 mb-2">SEU PRIMEIRO ACESSO</span>
                                <h4 class="fw-bold m-0 text-white">45 Dias de Teste 100% Gratuito!</h4>
                                <p class="text-white-50 m-0 text-small">Experimente todas as funcionalidades da plataforma sem cartão de crédito.</p>
                            </div>
                            <form action="<?= url('planos/ativar-teste-gratis') ?>" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-warning btn-lg px-4 fw-extrabold shadow text-nowrap">
                                    <i class="fa-solid fa-gift me-2"></i> ATIVAR TESTE GRÁTIS
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabela de Planos Pagos -->
    <div class="row g-4 justify-content-center mt-2">
        <?php foreach ($plans as $plan): ?>
            <?php if (!empty($plan['is_free_trial'])) continue; // O teste grátis tem o banner destacado acima ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center d-flex flex-column position-relative">
                    <?php if ($plan['name'] === 'Plano Trimestral' || $plan['name'] === 'Plano Anual'): ?>
                        <span class="badge bg-success position-absolute top-0 start-50 translate-middle px-3 py-1 rounded-pill">RECOMENDADO</span>
                    <?php endif; ?>

                    <h5 class="fw-bold mt-2"><?= e($plan['name']) ?></h5>
                    <div class="my-3">
                        <span class="fs-6 text-muted">R$</span>
                        <span class="display-6 fw-extrabold text-dark"><?= number_format($plan['price'], 2, ',', '.') ?></span>
                        <div class="text-muted text-small"><?= e($plan['period_name']) ?></div>
                    </div>

                    <ul class="list-unstyled text-small text-muted my-3 text-start space-y-2">
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Até <strong><?= $plan['max_users'] ?></strong> usuário(s) simultâneos</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Upload ilimitado de planilhas de estoque</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Botão direto de WhatsApp para os vendedores</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> Consulta ilimitada a todos os distribuidores</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> <?= e($plan['description']) ?></li>
                    </ul>

                    <div class="mt-auto pt-3">
                        <a href="<?= url("checkout/{$plan['id']}") ?>" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-cart-shopping me-1"></i> Contratar <?= e($plan['name']) ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
