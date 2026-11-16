<?php $title = 'Administração de Usuários - StoqueOnline'; ?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-users-gear text-warning me-2"></i>Gerenciamento de Usuários</h2>
            <p class="text-muted m-0 text-small">Painel restrito para controle de acessos, empresas e concessão de licenças manuais.</p>
        </div>
    </div>

    <?php $adminAtivo = 'usuarios'; require __DIR__ . '/_nav.php'; ?>

    <form action="<?= url('admin/usuarios') ?>" method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" name="busca" value="<?= e($search) ?>" class="form-control"
                   placeholder="Buscar por nome, e-mail, empresa ou CNPJ..." style="max-width: 420px;">
            <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= url('admin/usuarios') ?>" class="btn btn-outline-secondary">Limpar</a>
            <?php endif; ?>
            <span class="ms-auto align-self-center text-muted text-small">
                <?= count($users) ?> usuário(s) listado(s)
            </span>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase fs-8 text-muted">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nome / Empresa</th>
                        <th>E-mail</th>
                        <th>Plano Atual</th>
                        <th class="text-center">Estoque</th>
                        <th class="text-center">Acesso</th>
                        <th class="text-end pe-4">Ações Rápidas</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4 text-muted fw-bold">#<?= $u['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($u['name']) ?></div>
                                <div class="text-muted text-small"><?= e($u['trading_name'] ?: $u['company_name'] ?: 'Sem empresa vinculada') ?></div>
                            </td>
                            <td>
                                <?= e($u['email']) ?>
                                <?php if (!empty($u['is_admin'])): ?>
                                    <span class="badge bg-warning text-dark fs-8 ms-1">ADMIN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($u['active_plan'])): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold"><?= e($u['active_plan']) ?></span>
                                    <?php if (!empty($u['license_end_date'])): ?>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            até <?= date('d/m/Y', strtotime((string) $u['license_end_date'])) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-muted">Sem plano</span>
                                <?php endif; ?>
                                <?php if (!empty($u['first_access_free'])): ?>
                                    <div class="text-primary" style="font-size:.7rem;">teste grátis disponível</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= number_format($u['total_stock_items'], 0, ',', '.') ?> itens</span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($u['allowed_access'])): ?>
                                    <span class="badge bg-success-subtle text-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Bloqueado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                    <!-- Editar dados cadastrais -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                            data-bs-target="#modalDados<?= $u['id'] ?>" title="Editar nome, e-mail e telefone">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <!-- Abrir o estoque deste fornecedor -->
                                    <?php if ((int) $u['total_stock_items'] > 0): ?>
                                        <a href="<?= url('admin/estoque/' . (int) $u['id']) ?>" class="btn btn-sm btn-outline-success"
                                           title="Gerenciar o estoque deste fornecedor">
                                            <i class="fa-solid fa-boxes-stacked"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Botão Conceder Licença -->
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalLicenca<?= $u['id'] ?>" title="Conceder Licença">
                                        <i class="fa-solid fa-key"></i>
                                    </button>

                                    <!-- Botão Alternar Acesso -->
                                    <form action="<?= url('admin/usuario/editar') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_access">
                                        <button type="submit" class="btn btn-sm <?= !empty($u['allowed_access']) ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= !empty($u['allowed_access']) ? 'Bloquear Acesso' : 'Desbloquear Acesso' ?>">
                                            <i class="fa-solid <?= !empty($u['allowed_access']) ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Ativação manual se não confirmou e-mail -->
                                    <?php if (empty($u['confirmed_activation'])): ?>
                                        <form action="<?= url('admin/usuario/editar') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="confirm_activation">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Confirmar e-mail manualmente">
                                                <i class="fa-solid fa-envelope-circle-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Encerrar licença vigente -->
                                    <?php if (!empty($u['active_plan'])): ?>
                                        <form action="<?= url('admin/usuario/editar') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('Encerrar a licença vigente deste usuário?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="revoke_license">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Encerrar licença">
                                                <i class="fa-solid fa-key"></i><i class="fa-solid fa-slash position-absolute" style="font-size:.6rem;"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Liberar novamente o teste gratuito -->
                                    <?php if (empty($u['first_access_free'])): ?>
                                        <form action="<?= url('admin/usuario/editar') ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('Liberar novamente o teste gratuito para este usuário?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="reset_free_trial">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Liberar teste gratuito novamente">
                                                <i class="fa-solid fa-gift"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Tornar/remover administrador -->
                                    <form action="<?= url('admin/usuario/editar') ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('Alterar as permissões de administrador deste usuário?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <button type="submit" class="btn btn-sm <?= !empty($u['is_admin']) ? 'btn-warning' : 'btn-outline-secondary' ?>"
                                                title="<?= !empty($u['is_admin']) ? 'Remover administrador' : 'Tornar administrador' ?>">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Modal: editar dados cadastrais e excluir a conta -->
                                <div class="modal fade text-start" id="modalDados<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header border-0 bg-light">
                                                <h5 class="modal-title fw-bold">Conta #<?= $u['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <form action="<?= url('admin/usuario/salvar') ?>" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label text-small fw-bold">Nome</label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($u['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-small fw-bold">E-mail (usado para entrar)</label>
                                                        <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>" required>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label class="form-label text-small fw-bold">Telefone</label>
                                                        <input type="text" name="phone" class="form-control" data-mask="phone" value="<?= e($u['phone'] ?? '') ?>">
                                                    </div>
                                                </div>

                                                <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Salvar dados</button>
                                                </div>
                                            </form>

                                            <div class="px-4 pb-4">
                                                <div class="border border-danger border-2 rounded-3 p-3">
                                                    <div class="fw-bold text-danger text-small mb-1">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Excluir esta conta
                                                    </div>
                                                    <p class="text-muted mb-2" style="font-size:.75rem;">
                                                        Apaga em definitivo a conta, a empresa, os colaboradores, o estoque
                                                        e as licenças. Contas com pagamento registrado não podem ser
                                                        excluídas — nesse caso, bloqueie o acesso.
                                                    </p>
                                                    <form action="<?= url('admin/usuario/excluir') ?>" method="POST"
                                                          onsubmit="return confirm('Excluir a conta de <?= e($u['name']) ?> e todos os dados dela?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" name="confirmacao" class="form-control"
                                                                   placeholder="Digite EXCLUIR para confirmar" autocomplete="off">
                                                            <button type="submit" class="btn btn-danger fw-bold">Excluir</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal de Concessão de Licença -->
                                <div class="modal fade text-start" id="modalLicenca<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <form action="<?= url('admin/usuario/editar') ?>" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="grant_license">

                                                <div class="modal-header border-0 bg-light">
                                                    <h5 class="modal-title fw-bold">Conceder Licença para <?= e($u['name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label text-small fw-bold">Selecione o Plano</label>
                                                        <select name="plan_id" class="form-select" required>
                                                            <?php foreach ($plans as $p): ?>
                                                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['period_name']) ?> - <?= $p['max_users'] ?> usuários)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-small fw-bold">Duração (dias)</label>
                                                        <input type="number" name="days" class="form-control" value="0" min="0" max="3650">
                                                        <div class="form-text text-small">Deixe em <strong>0</strong> para usar a duração padrão do plano selecionado.</div>
                                                    </div>
                                                    <?php if (empty($u['first_access_free'])): ?>
                                                        <div class="alert alert-light border text-small mb-0">
                                                            <i class="fa-solid fa-circle-info me-1"></i>
                                                            Este usuário já utilizou o teste gratuito. Para liberá-lo novamente,
                                                            feche esta janela e use o botão <i class="fa-solid fa-gift"></i> na lista.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Ativar Licença</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
