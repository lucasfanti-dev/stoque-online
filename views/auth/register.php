<?php $title = 'Criar Nova Conta - StoqueOnline'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-user-plus fs-4"></i>
                    </div>
                    <h3 class="fw-bold">Criar Conta Gratuita</h3>
                    <p class="text-muted text-small">Participe da rede e ganhe 45 dias de teste grátis.</p>
                </div>

                <form action="<?= url('cadastro') ?>" method="POST" data-aguarde="Criando sua conta e enviando o código de confirmação...">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label text-small fw-bold">Nome Completo ou Nome da Empresa <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-building"></i></span>
                            <input type="text" name="name" class="form-control" value="<?= e(old('name')) ?>" placeholder="Ex: Distribuidora Paulista" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-small fw-bold">E-mail Corporativo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" placeholder="contato@empresa.com.br" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-small fw-bold">Telefone / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" name="phone" class="form-control" value="<?= e(old('phone')) ?>" data-mask="phone" placeholder="(11) 99999-9999">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Senha <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Mín. 8 caracteres" minlength="8" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Confirmar Senha <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Repita a senha" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm mt-3">
                        <i class="fa-solid fa-check me-2"></i> CRIAR CONTA E INICIAR TESTE
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top text-muted text-small">
                    Já possui cadastro? <a href="<?= url('login') ?>" class="fw-bold text-primary text-decoration-none">Faça login</a>
                </div>
            </div>
        </div>
    </div>
</div>
