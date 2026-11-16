<?php
$title = 'Meios de Pagamento - Admin';
$adminAtivo = 'gateways';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-credit-card text-primary me-2"></i>Meios de Pagamento</h2>
            <p class="text-muted m-0 text-small">
                Ligue, desligue e guarde as credenciais. O que for salvo aqui vale na hora, sem subir arquivo.
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <?php if ($disponiveis === 0): ?>
        <div class="alert alert-warning d-flex align-items-start shadow-sm text-small">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-3 mt-1"></i>
            <div>
                <strong>Nenhum meio de pagamento disponível.</strong>
                A página de planos continua no ar, mas quem clicar em contratar recebe o aviso de
                que o pagamento está indisponível. Ligue ao menos um abaixo.
            </div>
        </div>
    <?php elseif ($disponiveis === 1): ?>
        <div class="alert alert-info d-flex align-items-start shadow-sm text-small">
            <i class="fa-solid fa-circle-info fs-5 me-3 mt-1"></i>
            <div>
                Com <strong>um</strong> meio ligado, o cliente vai direto para ele — não aparece tela de escolha.
                Ligando um segundo, o checkout passa a mostrar os dois lado a lado.
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success d-flex align-items-start shadow-sm text-small">
            <i class="fa-solid fa-circle-check fs-5 me-3 mt-1"></i>
            <div>
                <strong><?= (int) $disponiveis ?> meios ligados.</strong>
                O cliente escolhe por qual pagar na tela de checkout.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($gateways as $g): ?>
            <?php $totais = $porGateway[$g['slug']] ?? ['pagos' => 0, 'total' => 0.0, 'pendentes' => 0]; ?>
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3"
                                     style="width: 48px; height: 48px;">
                                    <i class="<?= e($g['icone']) ?> fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold m-0"><?= e($g['nome']) ?></h5>
                                    <div class="text-muted text-small"><?= e($g['formas']) ?></div>
                                </div>
                            </div>

                            <?php if ($g['ativo'] && $g['configurado']): ?>
                                <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-circle-check me-1"></i> No ar</span>
                            <?php elseif ($g['ativo']): ?>
                                <span class="badge bg-warning-subtle text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i> Falta credencial</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-muted"><i class="fa-solid fa-circle-pause me-1"></i> Desligado</span>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2 text-center mb-4">
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold text-success">R$ <?= number_format($totais['total'], 2, ',', '.') ?></div>
                                    <div class="text-muted" style="font-size:.66rem;">RECEBIDO</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold"><?= (int) $totais['pagos'] ?></div>
                                    <div class="text-muted" style="font-size:.66rem;">PAGAS</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold text-warning"><?= (int) $totais['pendentes'] ?></div>
                                    <div class="text-muted" style="font-size:.66rem;">PENDENTES</div>
                                </div>
                            </div>
                        </div>

                        <form action="<?= url('admin/meios-pagamento/salvar') ?>" method="POST"
                              data-aguarde="Salvando o meio de pagamento...">
                            <?= csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= e($g['slug']) ?>">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="ativo_<?= e($g['slug']) ?>" name="ativo" value="1"
                                       <?= $g['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-small" for="ativo_<?= e($g['slug']) ?>">
                                    Aceitar pagamento por <?= e($g['nome']) ?>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-small fw-bold" for="modo_<?= e($g['slug']) ?>">Ambiente</label>
                                <select class="form-select" id="modo_<?= e($g['slug']) ?>" name="modo">
                                    <option value="producao" <?= $g['modo'] === 'producao' ? 'selected' : '' ?>>
                                        Produção — cobrança de verdade
                                    </option>
                                    <option value="sandbox" <?= $g['modo'] === 'sandbox' ? 'selected' : '' ?>>
                                        Teste (sandbox) — nenhum dinheiro é movimentado
                                    </option>
                                </select>
                                <div class="form-text text-small">
                                    Em teste, use as credenciais de teste do provedor. Elas são diferentes das de produção.
                                </div>
                            </div>

                            <?php foreach ($g['campos'] as $chave => $campo): ?>
                                <div class="mb-3">
                                    <label class="form-label text-small fw-bold" for="c_<?= e($g['slug'] . '_' . $chave) ?>">
                                        <?= e($campo['rotulo']) ?>
                                        <?php if (empty($campo['obrigatorio'])): ?>
                                            <span class="text-muted fw-normal">(opcional)</span>
                                        <?php endif; ?>
                                    </label>

                                    <input type="text"
                                           class="form-control font-monospace"
                                           id="c_<?= e($g['slug'] . '_' . $chave) ?>"
                                           name="cred_<?= e($chave) ?>"
                                           autocomplete="off"
                                           placeholder="<?= $campo['preenchido'] ? e($campo['mascara']) : 'não preenchido' ?>">

                                    <div class="form-text text-small"><?= e($campo['dica']) ?></div>

                                    <?php if ($campo['preenchido']): ?>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="l_<?= e($g['slug'] . '_' . $chave) ?>" name="limpar_<?= e($chave) ?>">
                                            <label class="form-check-label text-small text-danger" for="l_<?= e($g['slug'] . '_' . $chave) ?>">
                                                Apagar o valor guardado
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="p-3 rounded-3 mb-3" style="background: var(--bg-surface-2);">
                                <div class="text-muted text-uppercase fw-bold mb-1" style="font-size:.66rem;">
                                    URL de notificação (webhook)
                                </div>
                                <div class="font-monospace text-small text-break"><?= e($g['urlWebhook']) ?></div>
                                <div class="form-text text-small m-0">
                                    Cole este endereço no painel do <?= e($g['nome']) ?>. É por ele que a licença
                                    do cliente é liberada sozinha assim que o pagamento cai.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Salvar <?= e($g['nome']) ?>
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-2"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Onde as credenciais ficam</h6>
            <p class="text-small text-muted m-0">
                Gravadas no banco de dados e nunca devolvidas inteiras para esta tela — o campo mostra
                só os últimos caracteres, o bastante para você conferir se é a credencial certa. Deixar
                um campo em branco significa "não mexi nele": o valor guardado continua valendo.
            </p>
        </div>
    </div>
</div>
