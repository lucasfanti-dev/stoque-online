<?php
$title = 'Conciliação de Pagamentos - Admin';

$statusBadge = static function (string $status): array {
    return match ($status) {
        'paid', 'completed' => ['bg-success-subtle text-success', 'Pago'],
        'pending'           => ['bg-warning-subtle text-warning', 'Aguardando'],
        'expired'           => ['bg-secondary-subtle text-secondary', 'Expirado'],
        'cancelled'         => ['bg-secondary-subtle text-secondary', 'Cancelado'],
        'refunded'          => ['bg-danger-subtle text-danger', 'Estornado'],
        'chargeback'        => ['bg-danger-subtle text-danger', 'Chargeback'],
        'analysis'          => ['bg-info-subtle text-info', 'Em análise'],
        default             => ['bg-light text-dark', $status],
    };
};
?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-file-invoice-dollar text-warning me-2"></i>Conciliação de Pagamentos</h2>
        <p class="text-muted m-0 text-small">Todos os pedidos gerados na plataforma e o vínculo com as licenças.</p>
    </div>

    <?php $adminAtivo = 'pagamentos'; require __DIR__ . '/_nav.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Total recebido</div>
                <div class="fs-4 fw-bold text-success">R$ <?= number_format((float) ($summary['total_paid'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Pedidos pagos</div>
                <div class="fs-4 fw-bold"><?= (int) ($summary['count_paid'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Recebido no mês</div>
                <div class="fs-4 fw-bold text-success">R$ <?= number_format((float) ($summary['total_mes'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Ticket médio</div>
                <div class="fs-4 fw-bold">R$ <?= number_format((float) ($summary['ticket_medio'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Aguardando</div>
                <div class="fs-4 fw-bold text-warning">
                    <?= (int) ($summary['count_pending'] ?? 0) ?>
                    <span class="fs-6 text-muted fw-normal">
                        &middot; R$ <?= number_format((float) ($summary['total_em_aberto'] ?? 0), 2, ',', '.') ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 <?= (int) ($summary['count_unlinked'] ?? 0) > 0 ? 'border-start border-4 border-danger' : '' ?>">
                <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Pagos sem licença</div>
                <div class="fs-4 fw-bold <?= (int) ($summary['count_unlinked'] ?? 0) > 0 ? 'text-danger' : '' ?>">
                    <?= (int) ($summary['count_unlinked'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ((int) ($summary['count_unlinked'] ?? 0) > 0): ?>
        <div class="alert alert-danger d-flex align-items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
            <div>
                <strong>Atenção:</strong> existem pagamentos confirmados sem licença vinculada.
                Abra o pedido do cliente e use "Verificar pagamento" para reprocessar a liberação.
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($porGateway) && count($porGateway) > 1): ?>
        <div class="row g-3 mb-4">
            <?php foreach ($porGateway as $linha): ?>
                <div class="col-md-4 col-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">
                            <i class="fa-solid fa-credit-card me-1"></i>
                            <?= e($nomesGateway[$linha['gateway']] ?? $linha['gateway']) ?>
                        </div>
                        <div class="fs-5 fw-bold text-success">R$ <?= number_format((float) $linha['total'], 2, ',', '.') ?></div>
                        <div class="text-muted text-small"><?= (int) $linha['pagos'] ?> cobrança(s) paga(s)</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="<?= url('admin/pagamentos') ?>" class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-small fw-bold" for="filtroGateway">Meio de pagamento</label>
                <select class="form-select" id="filtroGateway" name="gateway">
                    <option value="">Todos</option>
                    <?php foreach ($nomesGateway as $slug => $nome): ?>
                        <option value="<?= e($slug) ?>" <?= $gatewayFiltro === $slug ? 'selected' : '' ?>>
                            <?= e($nome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-small fw-bold" for="filtroSituacao">Situação</label>
                <select class="form-select" id="filtroSituacao" name="situacao">
                    <option value="">Todas</option>
                    <option value="pagos" <?= $situacao === 'pagos' ? 'selected' : '' ?>>Pagas</option>
                    <option value="pendentes" <?= $situacao === 'pendentes' ? 'selected' : '' ?>>Aguardando</option>
                    <option value="problema" <?= $situacao === 'problema' ? 'selected' : '' ?>>Pagas sem licença</option>
                </select>
            </div>
            <div class="col-md-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                </button>
                <?php if ($gatewayFiltro !== '' || $situacao !== ''): ?>
                    <a href="<?= url('admin/pagamentos') ?>" class="btn btn-outline-secondary">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Cliente</th>
                        <th>Plano</th>
                        <th>Meio</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Licença</th>
                        <th>Referência</th>
                        <th>Criado em</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                Nenhum pedido registrado até o momento.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payments as $payment): ?>
                        <?php [$badgeClass, $badgeLabel] = $statusBadge((string) $payment['status']); ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= (int) $payment['id'] ?></td>
                            <td>
                                <div class="fw-bold"><?= e($payment['user_name']) ?></div>
                                <small class="text-muted"><?= e($payment['user_email']) ?></small>
                            </td>
                            <td><?= e($payment['plan_name']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark">
                                    <?= e($nomesGateway[$payment['gateway'] ?? ''] ?? ($payment['gateway'] ?? '—')) ?>
                                </span>
                                <?php if (!empty($payment['payment_method'])): ?>
                                    <div class="text-muted" style="font-size:.68rem;"><?= e(strtoupper($payment['payment_method'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold">R$ <?= number_format((float) $payment['amount'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass ?> fw-bold"><?= e($badgeLabel) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($payment['license_id'])): ?>
                                    <span class="badge bg-success-subtle text-success">#<?= (int) $payment['license_id'] ?></span>
                                <?php elseif (in_array($payment['status'], ['paid', 'completed'], true)): ?>
                                    <span class="badge bg-danger-subtle text-danger">Pendente</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e($payment['reference_id']) ?></small></td>
                            <td>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime((string) $payment['created_at'])) ?></small>
                                <?php if (!empty($payment['paid_at'])): ?>
                                    <div class="text-success" style="font-size:.7rem;">
                                        Pago em <?= date('d/m/Y H:i', strtotime((string) $payment['paid_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php
                                $pago = in_array($payment['status'], ['paid', 'completed'], true);
                                $temLicenca = !empty($payment['license_id']);
                                ?>
                                <div class="d-inline-flex gap-1">
                                    <!-- Reconsulta o status oficial no PicPay -->
                                    <form action="<?= url('admin/pagamento/verificar') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Consultar status no PicPay">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </form>

                                    <?php if (!$temLicenca): ?>
                                        <!-- Libera a licença sem depender do gateway -->
                                        <form action="<?= url('admin/pagamento/liberar') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('<?= $pago
                                                  ? 'Liberar a licença deste pagamento confirmado?'
                                                  : 'ATENÇÃO: este pedido NÃO consta como pago. Marcar como pago e liberar a licença mesmo assim?' ?>');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $pago ? 'btn-danger' : 'btn-outline-secondary' ?>"
                                                    title="Liberar licença manualmente">
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-4 d-flex justify-content-center">
            <ul class="pagination pagination-sm">
                <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('admin/pagamentos?pagina=' . $p) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
