<?php $title = 'Histórico de Pagamentos - StoqueOnline'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-receipt text-primary me-2"></i>Histórico de Pagamentos</h2>
            <p class="text-muted m-0 text-small">Acompanhe todos os seus pedidos de planos e status de transações.</p>
        </div>
        <div>
            <a href="<?= url('planos') ?>" class="btn btn-primary btn-sm fw-bold px-3">
                <i class="fa-solid fa-cart-plus me-1"></i> Contratar Novo Plano
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase fs-8 text-muted">
                    <tr>
                        <th class="ps-4">Data do Pedido</th>
                        <th>Referência</th>
                        <th>Plano Contratado</th>
                        <th>Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-receipt fs-1 text-muted mb-2 opacity-50"></i>
                                <div>Nenhum pedido realizado até o momento.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td class="fw-bold text-dark"><?= e($order['reference_id']) ?></td>
                                <td><?= e($order['plan_name']) ?></td>
                                <td class="fw-bold text-success">R$ <?= number_format($order['amount'], 2, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($order['status'] === 'paid' || $order['status'] === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success fw-bold">Pago / Aprovado</span>
                                    <?php elseif ($order['status'] === 'refunded' || $order['status'] === 'chargeback'): ?>
                                        <span class="badge bg-danger-subtle text-danger fw-bold">Estornado</span>
                                    <?php elseif ($order['status'] === 'cancelled'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary fw-bold">Cancelado</span>
                                    <?php elseif ($order['status'] === 'expired'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary fw-bold">Expirado</span>
                                    <?php elseif ($order['status'] === 'analysis'): ?>
                                        <span class="badge bg-info-subtle text-info fw-bold">Em análise</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning fw-bold">Aguardando Pagamento</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php $isPaid = in_array($order['status'], ['paid', 'completed'], true); ?>
                                    <?php $isClosed = in_array($order['status'], ['cancelled', 'expired', 'refunded', 'chargeback'], true); ?>

                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <?php if (!$isPaid && !$isClosed && !empty($order['payment_url'])): ?>
                                            <a href="<?= e(safe_url($order['payment_url'])) ?>" target="_blank" rel="noopener"
                                               class="btn btn-sm btn-outline-success fw-bold">
                                                <i class="fa-solid fa-qrcode me-1"></i> Pagar Agora
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$isPaid && !$isClosed): ?>
                                            <!-- Conciliação manual: independe da notificação do gateway -->
                                            <form action="<?= url('pedidos/verificar') ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="payment_id" value="<?= (int) $order['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary fw-bold" title="Consultar o status no meio de pagamento agora">
                                                    <i class="fa-solid fa-rotate me-1"></i> Verificar
                                                </button>
                                            </form>

                                            <form action="<?= url('pedidos/cancelar') ?>" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Deseja realmente cancelar esta cobrança?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="payment_id" value="<?= (int) $order['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold" title="Cancelar a cobrança">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($isPaid): ?>
                                            <span class="text-success fs-8 fw-bold">
                                                <i class="fa-solid fa-check me-1"></i>
                                                <?= !empty($order['license_id']) ? 'Licença liberada' : 'Liberando licença...' ?>
                                            </span>
                                        <?php elseif ($isClosed): ?>
                                            <span class="text-muted fs-8">Encerrado</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
