<?php
$title = 'Confirmar E-mail - StoqueOnline';
$emailPreenchido = $email ?? '';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-envelope-open-text fs-4"></i>
                    </div>
                    <h3 class="fw-bold">Confirme seu e-mail</h3>
                    <p class="text-muted text-small m-0">
                        <?php if ($emailPreenchido !== ''): ?>
                            Enviamos um código de 6 dígitos para <strong class="text-break"><?= e($emailPreenchido) ?></strong>.
                        <?php else: ?>
                            Enviamos um código de 6 dígitos para o seu e-mail.
                        <?php endif; ?>
                        Digite-o abaixo para liberar o seu acesso.
                    </p>
                </div>

                <form action="<?= url('confirmar-email') ?>" method="POST" id="formConfirmar" data-aguarde="Conferindo o seu código...">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="campoEmail" class="form-label text-small fw-bold">E-mail do cadastro</label>
                        <input type="email" name="email" id="campoEmail" class="form-control"
                               value="<?= e(old('email', $emailPreenchido)) ?>"
                               placeholder="seu@email.com" required>
                    </div>

                    <div class="mb-2">
                        <label for="campoCodigo" class="form-label text-small fw-bold">Código recebido</label>
                        <input type="text" name="codigo" id="campoCodigo" class="form-control campo-codigo"
                               inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                               maxlength="6" placeholder="000000" required autofocus>
                        <div class="form-text text-small">
                            O código vale por <?= (int) \App\Services\VerificationCode::VALIDADE_MINUTOS ?> minutos.
                            Não esqueça de olhar a caixa de spam.
                        </div>
                    </div>

                    <button type="submit" data-aguarde-rotulo="Conferindo..." class="btn btn-primary btn-lg w-100 fw-bold shadow-sm mt-3">
                        <i class="fa-solid fa-circle-check me-2"></i> CONFIRMAR E ENTRAR
                    </button>
                </form>

                <form action="<?= url('confirmar-email/reenviar') ?>" method="POST" class="mt-3" id="formReenviar" data-aguarde="Enviando um código novo para o seu e-mail...">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" id="campoEmailReenvio" value="<?= e($emailPreenchido) ?>">
                    <button type="submit" class="btn btn-outline-secondary w-100 fw-medium">
                        <i class="fa-solid fa-rotate-right me-1"></i> Reenviar código
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="<?= url('login') ?>" class="text-decoration-none text-small fw-bold">
                        <i class="fa-solid fa-arrow-left me-1"></i> Voltar para o login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /* O reenvio usa o e-mail que estiver escrito no formulário de cima:
       quem corrigiu o endereço ali espera que o código novo vá para o
       endereço corrigido, não para o que veio da sessão. */
    (function () {
        var email = document.getElementById('campoEmail');
        var espelho = document.getElementById('campoEmailReenvio');
        if (!email || !espelho) return;

        document.getElementById('formReenviar').addEventListener('submit', function () {
            espelho.value = email.value;
        });
    })();
</script>
