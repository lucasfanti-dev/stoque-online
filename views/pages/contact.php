<?php
$title = 'Fale Conosco - StoqueOnline';
$metaDescription = 'Entre em contato com a equipe do StoqueOnline: suporte, parcerias e dúvidas comerciais.';
?>

<section class="bg-dark text-white py-5">
    <div class="container py-3 text-center">
        <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold mb-3">Contato</span>
        <h1 class="fw-bold display-6 mb-2">Fale com a nossa equipe</h1>
        <p class="text-white-50 m-0">Suporte, parcerias ou dúvidas sobre os planos.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                <h4 class="fw-bold mb-4">Envie sua mensagem</h4>

                <form action="<?= url('contato') ?>" method="POST" data-aguarde="Enviando a sua mensagem...">
                    <?= csrf_field() ?>

                    <!-- Armadilha anti-robô: fica oculta para pessoas -->
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <label>Não preencha este campo
                            <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e(old('name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">E-mail <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Telefone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" data-mask="phone" value="<?= e(old('phone')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Assunto</label>
                            <select name="subject" class="form-select">
                                <option value="Dúvida comercial">Dúvida comercial</option>
                                <option value="Suporte técnico">Suporte técnico</option>
                                <option value="Problema com pagamento">Problema com pagamento</option>
                                <option value="Parceria">Parceria</option>
                                <option value="Outro assunto">Outro assunto</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-small fw-bold">Mensagem <span class="text-danger">*</span></label>
                            <textarea name="message" rows="6" class="form-control" required
                                      placeholder="Conte com detalhes como podemos ajudar."><?= e(old('message')) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold mt-4">
                        <i class="fa-solid fa-paper-plane me-2"></i> ENVIAR MENSAGEM
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold mb-3">Canais diretos</h5>

                <div class="d-flex align-items-start mb-3">
                    <div class="text-primary me-3 fs-5"><i class="fa-solid fa-phone"></i></div>
                    <div>
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Telefone</div>
                        <div class="fw-bold"><?= e(setting('contato_telefone')) ?></div>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3">
                    <div class="text-primary me-3 fs-5"><i class="fa-solid fa-envelope"></i></div>
                    <div>
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">E-mail</div>
                        <div class="fw-bold text-break"><?= e(setting('contato_email')) ?></div>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3">
                    <div class="text-primary me-3 fs-5"><i class="fa-solid fa-location-dot"></i></div>
                    <div>
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Endereço</div>
                        <div class="fw-bold"><?= e(setting('contato_endereco')) ?></div>
                    </div>
                </div>

                <div class="d-flex align-items-start">
                    <div class="text-primary me-3 fs-5"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Horário</div>
                        <div class="fw-bold"><?= e(setting('contato_horario')) ?></div>
                    </div>
                </div>
            </div>

            <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener"
               class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                <i class="fa-brands fa-whatsapp me-2"></i> Chamar no WhatsApp
            </a>

            <div class="card border-0 bg-primary-subtle rounded-4 p-4 mt-4">
                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-circle-question me-2"></i>Antes de escrever</h6>
                <p class="text-small text-dark m-0">
                    Muitas dúvidas já estão respondidas na página de
                    <a href="<?= url('perguntas-frequentes') ?>" class="fw-bold text-decoration-none">Perguntas Frequentes</a>.
                </p>
            </div>
        </div>
    </div>
</div>
