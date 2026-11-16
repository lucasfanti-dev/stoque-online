<?php
/**
 * Navegação compartilhada do painel administrativo.
 * Espera a variável $adminAtivo com a chave da aba atual.
 */
$abas = [
    'dashboard'  => ['admin',            'fa-gauge-high',          'Visão Geral'],
    'usuarios'   => ['admin/usuarios',   'fa-users',               'Usuários'],
    'estoque'    => ['admin/estoque',    'fa-boxes-stacked',       'Estoques'],
    'planos'     => ['admin/planos',     'fa-tags',                'Planos'],
    'pagamentos' => ['admin/pagamentos', 'fa-file-invoice-dollar', 'Pagamentos'],
    'gateways'   => ['admin/meios-pagamento', 'fa-credit-card', 'Meios de Pagamento'],
    'sistema'    => ['admin/sistema',        'fa-screwdriver-wrench', 'Sistema'],
    'logs'       => ['admin/logs',       'fa-clock-rotate-left',   'Auditoria'],
    'configuracoes' => ['admin/configuracoes', 'fa-sliders',      'Configurações'],
];
$adminAtivo = $adminAtivo ?? '';
?>
<div class="bg-dark text-white rounded-4 p-2 mb-4 shadow-sm">
    <ul class="nav nav-pills flex-nowrap overflow-auto">
        <?php foreach ($abas as $chave => [$rota, $icone, $rotulo]): ?>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $adminAtivo === $chave ? 'active' : 'text-white-50' ?>"
                   href="<?= url($rota) ?>">
                    <i class="fa-solid <?= $icone ?> me-1"></i> <?= $rotulo ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
