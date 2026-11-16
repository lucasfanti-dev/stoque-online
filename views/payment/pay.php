<?php
$title = 'Pagamento - StoqueOnline';
$nomeGateway = isset($gateway) ? $gateway->nome() : 'PicPay';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-success-subtle text-success px-3 py-2 fw-bold text-uppercase">Pedido Gerado com Sucesso</span>
                </div>
                <h4 class="fw-bold mb-1">Pague com PIX ou <?= e($nomeGateway) ?></h4>
                <p class="text-muted text-small">Escaneie o QR Code abaixo com o aplicativo do seu banco ou do <?= e($nomeGateway) ?> para ativar sua assinatura na hora.</p>

                <div class="p-3 bg-light rounded-4 my-3 d-inline-block mx-auto border">
                    <?php if (!empty($qrcodeBase64)): ?>
                        <img src="<?= $qrcodeBase64 ?>" alt="QR Code de Pagamento" class="img-fluid rounded" style="max-width: 250px;">
                    <?php else: ?>
                        <div class="py-4 px-3">
                            <i class="fa-solid fa-qrcode display-1 text-muted opacity-50"></i>
                            <div class="text-muted mt-2 small">Clique no botão abaixo para pagar no <?= e($nomeGateway) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <div class="text-muted text-small mb-1">Valor do Pedido:</div>
                    <h2 class="fw-extrabold text-success m-0">R$ <?= number_format($plan['price'], 2, ',', '.') ?></h2>
                    <small class="text-muted"><?= e($plan['name']) ?> (Ref: <?= e($referenceId) ?>)</small>
                </div>

                <?php if (!empty($qrcodeContent)): ?>
                    <div class="mb-3">
                        <label class="form-label text-small fw-bold text-muted">PIX Copia e Cola:</label>
                        <div class="input-group">
                            <input type="text" id="pixCopiaCola" class="form-control form-control-sm text-truncate bg-light" value="<?= e($qrcodeContent) ?>" readonly>
                            <button class="btn btn-primary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('pixCopiaCola').value); alert('Código PIX copiado!');">
                                <i class="fa-solid fa-copy me-1"></i> Copiar
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($paymentUrl)): ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= $paymentUrl ?>" target="_blank" class="btn btn-success btn-lg fw-bold shadow-sm">
                            <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> Pagar no <?= e($nomeGateway) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top text-muted text-small">
                    Assim que o pagamento for confirmado, seu plano será ativado automaticamente.
                    <br>
                    <a href="<?= url('pedidos') ?>" class="btn btn-link btn-sm text-decoration-none mt-2">Ver Meus Pedidos</a>
                </div>
            </div>
        </div>
    </div>
</div>
