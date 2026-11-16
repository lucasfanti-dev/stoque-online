<?php $title = 'Entrar na Conta - StoqueOnline'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-lock fs-4"></i>
                    </div>
                    <h3 class="fw-bold">Acesse sua Conta</h3>
                    <p class="text-muted text-small">Informe seu e-mail e senha cadastrados.</p>
                </div>

                <form action="<?= url('login') ?>" method="POST" data-aguarde="Entrando...">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label text-small fw-bold">E-mail</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" placeholder="seu@email.com" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <label class="form-label text-small fw-bold">Senha</label>
                            <a href="<?= url('esqueci-senha') ?>" class="text-small text-decoration-none">Esqueceu a senha?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-key"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" data-aguarde-rotulo="Entrando..." class="btn btn-primary btn-lg w-100 fw-bold shadow-sm mt-3">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> ENTRAR
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top text-muted text-small">
                    Ainda não tem conta? <a href="<?= url('cadastro') ?>" class="fw-bold text-primary text-decoration-none">Cadastre-se grátis</a>
                </div>
            </div>
        </div>
    </div>
</div>
