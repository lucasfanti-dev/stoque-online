<?php
$title = 'Configurações do Site - Admin';
$adminAtivo = 'configuracoes';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-primary me-2"></i>Configurações do Site</h2>
            <p class="text-muted m-0 text-small">
                Os dados de contato que aparecem para quem visita o site. O que for salvo aqui vale na hora.
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="alert alert-info d-flex align-items-start shadow-sm text-small">
        <i class="fa-solid fa-circle-info fs-5 me-3 mt-1"></i>
        <div>
            Estes valores aparecem na <strong>barra de topo</strong>, no <strong>rodapé</strong>,
            na página <strong>Fale Conosco</strong>, nas <strong>Perguntas Frequentes</strong> e na
            tela de erro. Não é preciso subir arquivo nenhum depois de salvar.
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="<?= url('admin/configuracoes/salvar') ?>" method="POST">
                        <?= csrf_field() ?>

                        <?php foreach ($campos as $chave => [$rotulo, $dica, $tipo]): ?>
                            <div class="mb-4">
                                <label for="campo_<?= e($chave) ?>" class="form-label fw-bold text-small">
                                    <?= e($rotulo) ?>
                                </label>
                                <input type="<?= e($tipo) ?>"
                                       class="form-control"
                                       id="campo_<?= e($chave) ?>"
                                       name="<?= e($chave) ?>"
                                       value="<?= e(old($chave, $valores[$chave] ?? '')) ?>"
                                       <?= $tipo === 'url' ? 'placeholder="https://"' : '' ?>>
                                <div class="form-text text-small"><?= e($dica) ?></div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                            <a href="<?= url('admin') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Salvar configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-eye text-primary me-2"></i>Como está hoje</h6>

                    <div class="mb-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.68rem;">Barra de topo</div>
                        <div class="text-small mt-1">
                            <i class="fa-solid fa-phone me-1 text-primary"></i> <?= e($valores['contato_telefone'] ?? '') ?><br>
                            <i class="fa-solid fa-envelope me-1 text-primary"></i> <?= e($valores['contato_email'] ?? '') ?><br>
                            <i class="fa-solid fa-clock me-1 text-primary"></i> <?= e($valores['contato_horario'] ?? '') ?>
                        </div>
                    </div>

                    <div>
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.68rem;">Link do WhatsApp</div>
                        <div class="text-small mt-1 font-monospace text-break">
                            <?= e(whatsapp_link() ?: 'sem número configurado') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-warning-subtle">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-warning mb-2">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Uma coisa que fica de fora
                    </h6>
                    <p class="text-small m-0">
                        O e-mail <strong>remetente</strong> das mensagens automáticas (ativação de conta,
                        recuperação de senha) continua no arquivo <code>.env</code> do servidor, porque
                        precisa bater com a conta autenticada no provedor de envio. O e-mail acima é o de
                        <strong>contato</strong>: é o que aparece no site e o que recebe o formulário.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
