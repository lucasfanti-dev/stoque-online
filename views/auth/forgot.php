<?php $title = 'Recuperar Senha - StoqueOnline'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-key fs-4"></i>
                    </div>
                    <h3 class="fw-bold">Esqueceu a Senha?</h3>
                    <p class="text-muted text-small">Informe seu e-mail para receber um código de 6 dígitos.</p>
                </div>

                <form action="<?= url('esqueci-senha') ?>" method="POST" data-aguarde="Enviando o código para o seu e-mail...">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label text-small fw-bold">E-mail Cadastrado</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" placeholder="seu@email.com" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm mt-3">
                        ENVIAR CÓDIGO
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top text-muted text-small">
                    Lembrou da senha? <a href="<?= url('login') ?>" class="fw-bold text-primary text-decoration-none">Voltar ao login</a>
                </div>
            </div>
        </div>
    </div>
</div>
