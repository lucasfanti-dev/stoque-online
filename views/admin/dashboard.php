<?php
$title = 'Painel Administrativo - StoqueOnline';
$adminAtivo = 'dashboard';

$n = static fn(string $chave): int => (int) ($numeros[$chave] ?? 0);
$fmt = static fn($v): string => number_format((float) $v, 0, ',', '.');
?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-1"><i class="fa-solid fa-gauge-high text-warning me-2"></i>Painel Administrativo</h2>
        <p class="text-muted m-0 text-small">Controle total do sistema: contas, estoques, planos e pagamentos.</p>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <!-- Alertas que pedem ação -->
    <?php if ($n('pagamentos_sem_licenca') > 0): ?>
        <div class="alert alert-danger d-flex align-items-center shadow-sm">
            <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
            <div class="flex-grow-1">
                <strong><?= $n('pagamentos_sem_licenca') ?> pagamento(s) confirmado(s) sem licença liberada.</strong>
                O cliente pagou e não recebeu o acesso.
            </div>
            <a href="<?= url('admin/pagamentos') ?>" class="btn btn-danger btn-sm fw-bold">Resolver</a>
        </div>
    <?php endif; ?>

    <?php if ($n('nao_ativados') > 0): ?>
        <div class="alert alert-warning d-flex align-items-center shadow-sm">
            <i class="fa-solid fa-envelope fs-4 me-3"></i>
            <div class="flex-grow-1">
                <?= $n('nao_ativados') ?> conta(s) cadastrada(s) que nunca confirmaram o e-mail.
            </div>
            <a href="<?= url('admin/usuarios') ?>" class="btn btn-warning btn-sm fw-bold">Ver</a>
        </div>
    <?php endif; ?>

    <!-- Números -->
    <div class="row g-3 mb-4">
        <?php
        $cartoes = [
            ['Usuários',            $fmt($n('usuarios')),            'fa-users',          'primary',  $n('bloqueados') . ' bloqueado(s)'],
            ['Empresas',            $fmt($n('empresas')),            'fa-building',       'primary',  $n('fornecedores_ativos') . ' com estoque no ar'],
            ['Itens em estoque',    $fmt($n('itens')),               'fa-boxes-stacked',  'success',  $fmt($n('pecas')) . ' peças somadas'],
            ['Licenças ativas',     $fmt($n('licencas_ativas')),     'fa-id-card',        'success',  $n('licencas_vencendo') . ' vencem em 30 dias'],
            ['Faturamento',         'R$ ' . number_format((float) ($numeros['faturamento_total'] ?? 0), 2, ',', '.'), 'fa-sack-dollar', 'success', 'total recebido'],
            ['Cobranças pendentes', $fmt($n('pagamentos_pendentes')), 'fa-hourglass-half', 'warning', 'aguardando pagamento'],
        ];
        ?>
        <?php foreach ($cartoes as [$rotulo, $valor, $icone, $cor, $rodape]): ?>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.65rem;"><?= $rotulo ?></div>
                        <i class="fa-solid <?= $icone ?> text-<?= $cor ?> opacity-50"></i>
                    </div>
                    <div class="fs-4 fw-bold mt-1"><?= $valor ?></div>
                    <div class="text-muted" style="font-size:.7rem;"><?= $rodape ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Licenças vencendo -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-hourglass-end text-warning me-2"></i>Licenças vencendo em 30 dias</span>
                    <span class="badge bg-warning-subtle text-warning"><?= count($vencendo) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle m-0">
                        <tbody>
                            <?php if (empty($vencendo)): ?>
                                <tr><td class="text-center text-muted py-4">Nenhuma licença vence nos próximos 30 dias.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($vencendo as $v): ?>
                                <?php $dias = (int) floor((strtotime((string) $v['end_date']) - time()) / 86400); ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-small"><?= e($v['name']) ?></div>
                                        <small class="text-muted"><?= e($v['email']) ?></small>
                                    </td>
                                    <td class="text-small"><?= e($v['plano']) ?></td>
                                    <td class="text-end pe-3">
                                        <span class="badge <?= $dias <= 7 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' ?>">
                                            <?= $dias <= 0 ? 'vence hoje' : "em {$dias} dia(s)" ?>
                                        </span>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            <?= date('d/m/Y', strtotime((string) $v['end_date'])) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Maiores estoques -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fa-solid fa-ranking-star text-primary me-2"></i>Maiores estoques
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle m-0">
                        <tbody>
                            <?php foreach ($maioresEstoques as $m): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-small">
                                            <?= e(($m['trading_name'] ?? '') ?: $m['name']) ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-success-subtle text-success"><?= $fmt($m['itens']) ?> itens</span>
                                    </td>
                                    <td class="text-end pe-3" style="width: 90px;">
                                        <a href="<?= url('admin/estoque/' . (int) $m['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            Gerenciar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($maioresEstoques)): ?>
                                <tr><td class="text-center text-muted py-4">Nenhum estoque publicado ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Últimas atividades -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>Últimas atividades</span>
                    <a href="<?= url('admin/logs') ?>" class="btn btn-sm btn-outline-secondary">Ver tudo</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle m-0">
                        <tbody>
                            <?php foreach ($ultimosAcessos as $log): ?>
                                <?php $falha = str_contains((string) $log['action'], 'failed') || str_contains((string) $log['action'], 'blocked'); ?>
                                <tr>
                                    <td class="ps-3 text-muted" style="width: 150px; font-size:.75rem;">
                                        <?= date('d/m/Y H:i', strtotime((string) $log['created_at'])) ?>
                                    </td>
                                    <td class="text-small"><?= e($log['user_name'] ?: ($log['email'] ?: 'Desconhecido')) ?></td>
                                    <td>
                                        <span class="badge <?= $falha ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info' ?>">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted pe-3" style="font-size:.75rem;"><?= e($log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
