<?php
$title = 'Pagamento - StoqueOnline';
$isPaid = $payment && in_array($payment['status'], ['paid', 'completed'], true);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 text-center">

                <?php if ($isPaid || $hasLicense): ?>
                    <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3 mx-auto" style="width: 76px; height: 76px;">
                        <i class="fa-solid fa-circle-check fs-1"></i>
                    </div>
                    <h2 class="fw-bold mb-2">Pagamento confirmado!</h2>
                    <p class="text-muted mb-4">
                        Seu plano já está ativo e você pode carregar seu estoque agora mesmo.
                    </p>

                    <?php if ($payment): ?>
                        <div class="bg-light rounded-4 p-3 text-start mb-4">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted text-small">Plano</span>
                                <span class="fw-bold"><?= e($payment['plan_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted text-small">Valor</span>
                                <span class="fw-bold">R$ <?= number_format((float) $payment['amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted text-small">Pedido</span>
                                <span class="fw-bold text-small"><?= e($payment['reference_id']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="<?= url('estoque/upload') ?>" class="btn btn-primary btn-lg px-4 fw-bold">
                            <i class="fa-solid fa-cloud-arrow-up me-2"></i> Carregar Estoque
                        </a>
                        <a href="<?= url('pedidos') ?>" class="btn btn-outline-secondary btn-lg px-4 fw-bold">
                            <i class="fa-solid fa-receipt me-2"></i> Meus Pedidos
                        </a>
                    </div>

                <?php else: ?>
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle mb-3 mx-auto" style="width: 76px; height: 76px;">
                        <i class="fa-solid fa-hourglass-half fs-1"></i>
                    </div>
                    <h2 class="fw-bold mb-2">Estamos confirmando seu pagamento</h2>
                    <p class="text-muted mb-4">
                        O PicPay ainda não confirmou a compensação. Isso costuma levar poucos instantes
                        no Pix e até 2 dias úteis em outros meios. Assim que o valor for confirmado,
                        sua licença é liberada automaticamente e você recebe um e-mail.
                    </p>

                    <?php if ($payment): ?>
                        <div class="bg-light rounded-4 p-3 text-start mb-4">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted text-small">Pedido</span>
                                <span class="fw-bold text-small"><?= e($payment['reference_id']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted text-small">Situação atual</span>
                                <span class="badge bg-warning-subtle text-warning fw-bold"><?= e($payment['status']) ?></span>
                            </div>
                        </div>

                        <form action="<?= url('pedidos/verificar') ?>" method="POST" class="mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold w-100">
                                <i class="fa-solid fa-rotate me-2"></i> Verificar pagamento agora
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="<?= url('pedidos') ?>" class="btn btn-outline-secondary fw-bold">
                        <i class="fa-solid fa-receipt me-2"></i> Ver histórico de pedidos
                    </a>
                <?php endif; ?>

                <p class="text-muted text-small mt-4 mb-0">
                    Dúvidas? Fale com a gente pelo
                    <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" class="text-success fw-bold text-decoration-none">WhatsApp</a>.
                </p>
            </div>
        </div>
    </div>
</div>
