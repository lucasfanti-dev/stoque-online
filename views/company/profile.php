<?php $title = 'Minha Empresa & Colaboradores - StoqueOnline'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-building text-primary me-2"></i>Minha Empresa</h2>
            <p class="text-muted m-0 text-small">Configure os dados de contato que os compradores verão e gerencie a equipe de vendedores.</p>
        </div>
        <div>
            <?php if ($activeLicense): ?>
                <span class="badge bg-success p-2 fs-7">
                    <i class="fa-solid fa-shield-check me-1"></i> Plano <?= e($activeLicense['plan_name']) ?> Ativo
                </span>
            <?php else: ?>
                <a href="<?= url('planos') ?>" class="btn btn-warning btn-sm fw-bold px-3">
                    <i class="fa-solid fa-crown me-1"></i> Contratar Plano
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status da Licença Atual -->
    <?php if ($activeLicense): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: var(--green-soft); border-left: 4px solid var(--green) !important;">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="text-success text-small text-uppercase fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Licença Ativa</div>
                    <h5 class="fw-bold text-dark m-0"><?= e($activeLicense['plan_name']) ?></h5>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-small text-uppercase fw-bold">Período de Vigência</div>
                    <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($activeLicense['start_date'])) ?> até <?= date('d/m/Y', strtotime($activeLicense['end_date'])) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-small text-uppercase fw-bold">Acessos da Equipe</div>
                    <div class="fw-bold text-dark"><?= $activeLicense['max_users'] ?> usuário(s) permitidos</div>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <a href="<?= url('planos') ?>" class="btn btn-success btn-sm fw-bold px-3">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Gerenciar Licença
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: var(--accent-soft); border-left: 4px solid var(--accent) !important;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary text-white rounded-circle">
                        <i class="fa-solid fa-gift fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Você ainda não possui uma licença ativa</h5>
                        <p class="text-muted text-small m-0">Ative seu período de degustação de 45 dias grátis ou assine um plano para liberar todas as funções de consulta e upload.</p>
                    </div>
                </div>
                <a href="<?= url('planos') ?>" class="btn btn-primary btn-sm px-4 py-2 fw-bold text-nowrap">
                    <i class="fa-solid fa-bolt me-1"></i> Ativar 45 Dias Grátis
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Dados Comerciais da Empresa -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-id-card text-primary me-2"></i>Dados Cadastrais da Empresa</h5>
                <form action="<?= url('empresa/salvar') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Nome Fantasia</label>
                            <input type="text" name="trading_name" class="form-control" placeholder="Ex: Rolamentos Paulista" value="<?= e($company['trading_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Razão Social</label>
                            <input type="text" name="company_name" class="form-control" placeholder="Ex: Paulista Comércio de Peças Ltda" value="<?= e($company['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">CNPJ</label>
                            <input type="text" name="cnpj" class="form-control" data-mask="cnpj" placeholder="00.000.000/0000-00" value="<?= e($company['cnpj'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">WhatsApp Comercial (com DDD) <span class="text-success">*</span></label>
                            <input type="text" name="whatsapp" class="form-control" data-mask="phone" placeholder="Ex: 11995270932" value="<?= e($company['whatsapp'] ?? '') ?>" required>
                            <div class="form-text text-small text-muted">Usado para os clientes chamarem direto da busca.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-small fw-bold">CEP</label>
                            <input type="text" name="zip_code" class="form-control" data-mask="cep" placeholder="00000-000" value="<?= e($company['zip_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Endereço / Logradouro</label>
                            <input type="text" name="address" class="form-control" placeholder="Rua, Avenida..." value="<?= e($company['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-small fw-bold">Número</label>
                            <input type="text" name="number" class="form-control" placeholder="Nº" value="<?= e($company['number'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-small fw-bold">Bairro</label>
                            <input type="text" name="district" class="form-control" value="<?= e($company['district'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-small fw-bold">Cidade</label>
                            <input type="text" name="city" class="form-control" value="<?= e($company['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-small fw-bold">UF (Estado)</label>
                            <input type="text" name="state" class="form-control text-uppercase" maxlength="2" placeholder="SP" value="<?= e($company['state'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">Telefone Fixo</label>
                            <input type="text" name="phone" class="form-control" data-mask="phone" placeholder="(11) 3333-3333" value="<?= e($company['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-small fw-bold">E-mail Comercial</label>
                            <input type="email" name="contact_email" class="form-control" placeholder="vendas@empresa.com.br" value="<?= e($company['contact_email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-small fw-bold">Website da Empresa</label>
                            <input type="url" name="website" class="form-control" placeholder="https://www.suaempresa.com.br" value="<?= e($company['website'] ?? '') ?>">
                        </div>

                        <!-- Redes sociais (campos que existiam na versão anterior) -->
                        <div class="col-12 mt-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <h6 class="fw-bold m-0"><i class="fa-solid fa-share-nodes text-primary me-2"></i>Redes Sociais</h6>
                                <small class="text-muted">(opcional)</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-facebook text-primary me-1"></i> Facebook</label>
                                    <input type="url" name="facebook" class="form-control" placeholder="https://facebook.com/suaempresa" value="<?= e($company['facebook'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-instagram text-danger me-1"></i> Instagram</label>
                                    <input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/suaempresa" value="<?= e($company['instagram'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-linkedin text-primary me-1"></i> LinkedIn</label>
                                    <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/company/suaempresa" value="<?= e($company['linkedin'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-youtube text-danger me-1"></i> YouTube</label>
                                    <input type="url" name="youtube" class="form-control" placeholder="https://youtube.com/@suaempresa" value="<?= e($company['youtube'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-x-twitter me-1"></i> X / Twitter</label>
                                    <input type="url" name="twitter" class="form-control" placeholder="https://x.com/suaempresa" value="<?= e($company['twitter'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-small fw-bold"><i class="fa-brands fa-skype text-info me-1"></i> Skype</label>
                                    <input type="text" name="skype" class="form-control" placeholder="usuario.skype" value="<?= e($company['skype'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Salvar Dados da Empresa
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gestão da Equipe de Vendas / Colaboradores -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-users text-primary me-2"></i>Equipe de Vendas</h5>
                    <button class="btn btn-outline-primary btn-sm fw-bold" data-bs-toggle="collapse" data-bs-target="#formNovoColaborador">
                        <i class="fa-solid fa-plus me-1"></i> Adicionar
                    </button>
                </div>

                <!-- Formulário Colapsável para Novo Vendedor -->
                <div class="collapse mb-4" id="formNovoColaborador">
                    <div class="p-3 border rounded-3 bg-light">
                        <h6 class="fw-bold mb-3">Cadastrar Novo Colaborador</h6>
                        <form action="<?= url('empresa/colaborador/adicionar') ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label class="form-label text-small fw-bold">Nome Completo</label>
                                <input type="text" name="name" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label text-small fw-bold">Cargo</label>
                                <input type="text" name="occupation" class="form-control form-control-sm" placeholder="Ex: Vendedor Técnico">
                            </div>
                            <div class="mb-2">
                                <label class="form-label text-small fw-bold">E-mail</label>
                                <input type="email" name="email" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-small fw-bold">Telefone / WhatsApp</label>
                                <input type="text" name="phone" class="form-control form-control-sm" data-mask="phone">
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success btn-sm px-3 fw-bold">Salvar Colaborador</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Colaboradores -->
                <?php if (empty($contacts)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-user-group fs-2 mb-2 opacity-50"></i>
                        <p class="text-small m-0">Nenhum vendedor cadastrado ainda.<br>Clique em "Adicionar" para cadastrar sua equipe.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($contacts as $c): 
                            $initials = strtoupper(substr($c['name'], 0, 2));
                        ?>
                            <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 0.85rem;">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($c['name']) ?></div>
                                        <div class="text-muted text-small"><?= e($c['occupation'] ?: 'Vendedor') ?> &bull; <?= e($c['email']) ?></div>
                                        <?php if (!empty($c['phone'])): ?>
                                            <div class="text-muted fs-8"><i class="fa-brands fa-whatsapp text-success me-1"></i><?= e($c['phone']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <!-- Toggle Licença -->
                                    <form action="<?= url('empresa/colaborador/licenca') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="include" value="<?= empty($c['include_in_license']) ? 1 : 0 ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($c['include_in_license']) ? 'btn-success' : 'btn-outline-secondary' ?> py-1 px-2 fs-8 mb-1" title="Vincular à licença da empresa">
                                            <i class="fa-solid fa-key me-1"></i> <?= !empty($c['include_in_license']) ? 'Ativo' : 'Inativo' ?>
                                        </button>
                                    </form>

                                    <!-- Excluir -->
                                    <form action="<?= url('empresa/colaborador/excluir') ?>" method="POST" class="d-inline" onsubmit="return confirm('Deseja realmente remover este colaborador?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-2" title="Excluir">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
