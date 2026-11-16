<?php $title = 'Checkout - StoqueOnline'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4 border-0">
                    <h4 class="fw-bold m-0"><i class="fa-solid fa-cart-shopping text-primary me-2"></i>Finalizar Assinatura</h4>
                </div>

                <div class="card-body p-4">
                    <!-- Resumo do Plano -->
                    <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-small text-uppercase fw-bold">Plano Selecionado</div>
                            <h5 class="fw-bold text-primary m-0"><?= e($plan['name']) ?> (<?= e($plan['period_name']) ?>)</h5>
                            <small class="text-muted"><?= $plan['max_users'] ?> usuário(s) &bull; <?= e($plan['description']) ?></small>
                        </div>
                        <div class="text-end">
                            <span class="fs-5 text-muted">Total:</span>
                            <h3 class="fw-extrabold text-success m-0">R$ <?= number_format($plan['price'], 2, ',', '.') ?></h3>
                        </div>
                    </div>

                    <!-- Formulário de Cobrança -->
                    <form action="<?= url('checkout/processar') ?>" method="POST" data-aguarde="Gerando a sua cobrança...">
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">

                        <?php if ($gatewayUnico !== null): ?>
                            <?php $g = $gateways[$gatewayUnico]; ?>
                            <input type="hidden" name="gateway" value="<?= e($gatewayUnico) ?>">

                            <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4"
                                 style="background: var(--bg-surface-2); border: 1px solid var(--border-subtle);">
                                <i class="<?= e($g->icone()) ?> fs-4 text-primary"></i>
                                <div>
                                    <div class="fw-bold">Pagamento por <?= e($g->nome()) ?></div>
                                    <div class="text-muted text-small"><?= e($g->formasDePagamento()) ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <h6 class="fw-bold mb-3 text-uppercase">
                                <i class="fa-solid fa-credit-card me-2 text-primary"></i>Como você prefere pagar?
                            </h6>

                            <div class="row g-3 mb-4">
                                <?php $primeiro = true; ?>
                                <?php foreach ($gateways as $slug => $g): ?>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="gateway"
                                               id="gw_<?= e($slug) ?>" value="<?= e($slug) ?>"
                                               <?= $primeiro ? 'checked' : '' ?> required>
                                        <label class="btn btn-outline-primary w-100 text-start p-3 h-100" for="gw_<?= e($slug) ?>">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="<?= e($g->icone()) ?> fs-4"></i>
                                                <div class="text-wrap">
                                                    <div class="fw-bold"><?= e($g->nome()) ?></div>
                                                    <div class="text-small opacity-75"><?= e($g->formasDePagamento()) ?></div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php $primeiro = false; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h6 class="fw-bold mb-3 text-uppercase"><i class="fa-solid fa-user me-2 text-primary"></i>Dados do Titular da Assinatura</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-small fw-bold">Primeiro Nome <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" value="<?= e(old('first_name', explode(' ', $user['name'] ?? '')[0] ?? '')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-small fw-bold">Sobrenome <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" value="<?= e(trim(strstr($user['name'] ?? '', ' ')) ?: 'Silva') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-small fw-bold">CPF / CNPJ do Titular <span class="text-danger">*</span></label>
                                <input type="text" name="document" class="form-control" value="<?= e(old('document')) ?>" placeholder="000.000.000-00" required>
                                <div class="form-text text-small">CPF do titular ou CNPJ da empresa.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-small fw-bold">Celular / WhatsApp <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" data-mask="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" placeholder="(11) 99999-9999" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-small fw-bold">E-mail para Recebimento <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <a href="<?= url('planos') ?>" class="btn btn-outline-secondary">Voltar aos Planos</a>
                            <button type="submit" data-aguarde-rotulo="Gerando..." class="btn btn-success btn-lg px-4 fw-bold shadow-sm">
                                <i class="fa-solid fa-qrcode me-2"></i> GERAR PAGAMENTO
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
