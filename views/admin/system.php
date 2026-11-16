<?php
$title = 'Sistema - Admin';
$adminAtivo = 'sistema';

$porGrupo = [];
foreach ($conferencias as $item) {
    $porGrupo[$item['grupo']][] = $item;
}
$comProblema = array_filter($conferencias, fn($i) => !$i['ok']);
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-screwdriver-wrench text-primary me-2"></i>Sistema</h2>
            <p class="text-muted m-0 text-small">
                Tela técnica: configuração do servidor, estado do banco e o arquivo <code>.env</code>.
            </p>
        </div>
        <span class="badge bg-danger-subtle text-danger p-2">
            <i class="fa-solid fa-user-shield me-1"></i> Apenas administradores
        </span>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <!-- ============================ CONFERÊNCIAS ============================ -->
    <?php if ($comProblema !== []): ?>
        <div class="alert alert-warning shadow-sm">
            <div class="fw-bold mb-2">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?= count($comProblema) ?> ponto(s) para olhar
            </div>
            <ul class="m-0 ps-4 text-small">
                <?php foreach ($comProblema as $item): ?>
                    <li><strong><?= e($item['item']) ?>:</strong> <?= e($item['detalhe']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-success shadow-sm text-small">
            <i class="fa-solid fa-circle-check me-2"></i>
            Todas as <?= count($conferencias) ?> conferências passaram.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ============================ SERVIDOR ============================ -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-server text-primary me-2"></i>Servidor</h6>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle m-0 text-small">
                            <tbody>
                                <tr><td class="text-muted">PHP</td>
                                    <td class="text-end fw-bold <?= $php['versao_ok'] ? '' : 'text-danger' ?>">
                                        <?= e($php['versao']) ?> <span class="text-muted fw-normal">(<?= e($php['sapi']) ?>)</span>
                                    </td></tr>
                                <tr><td class="text-muted">Memória por requisição</td>
                                    <td class="text-end font-monospace"><?= e($php['memory_limit']) ?></td></tr>
                                <tr><td class="text-muted">Envio de arquivo</td>
                                    <td class="text-end font-monospace"><?= e($php['upload_max']) ?> / <?= e($php['post_max']) ?></td></tr>
                                <tr><td class="text-muted">Tempo máximo</td>
                                    <td class="text-end font-monospace"><?= e($php['max_execution']) ?>s</td></tr>
                                <tr><td class="text-muted">Fuso / hora</td>
                                    <td class="text-end"><?= e($php['fuso']) ?> &middot; <?= e($php['hora']) ?></td></tr>
                                <tr><td class="text-muted">OPcache</td>
                                    <td class="text-end"><?= $php['opcache'] ? 'ligado' : 'desligado' ?></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <div class="text-muted text-uppercase fw-bold mb-2" style="font-size:.68rem;">Extensões</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($php['extensoes'] as $nome => $ext): ?>
                                <span class="badge <?= $ext['carregada'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>"
                                      title="<?= e($ext['para']) ?>">
                                    <i class="fa-solid fa-<?= $ext['carregada'] ? 'check' : 'xmark' ?> me-1"></i><?= e($nome) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ BANCO ============================ -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-database text-primary me-2"></i>Banco de dados</h6>

                    <?php if (!$banco['conectado']): ?>
                        <div class="alert alert-danger text-small m-0">
                            <strong>Sem conexão.</strong> <?= e((string) $banco['erro']) ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold"><?= (int) $banco['qtd'] ?></div>
                                    <div class="text-muted" style="font-size:.66rem;">TABELAS</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold"><?= number_format((float) $banco['total_mb'], 1, ',', '.') ?> MB</div>
                                    <div class="text-muted" style="font-size:.66rem;">TAMANHO</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3" style="background: var(--bg-surface-2);">
                                    <div class="fw-bold text-success"><?= (int) $migracoes['aplicadas'] ?></div>
                                    <div class="text-muted" style="font-size:.66rem;">MIGRAÇÕES</div>
                                </div>
                            </div>
                        </div>

                        <div class="text-small text-muted mb-2">
                            <?= e((string) $banco['versao']) ?> &middot;
                            <span class="font-monospace"><?= e((string) $banco['nome']) ?></span> em
                            <span class="font-monospace"><?= e((string) $banco['host']) ?></span>
                        </div>

                        <?php if (!empty($migracoes['pendentes'])): ?>
                            <div class="alert alert-warning text-small py-2">
                                <strong><?= count($migracoes['pendentes']) ?> migração(ões) pendente(s).</strong>
                                Rode o atualizador do banco.
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive" style="max-height: 230px; overflow-y: auto;">
                            <table class="table table-sm align-middle m-0 text-small">
                                <thead class="table-light">
                                    <tr><th>Tabela</th><th class="text-end">Linhas</th><th class="text-end">MB</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($banco['tabelas'] as $t): ?>
                                        <tr>
                                            <td class="font-monospace"><?= e((string) $t['nome']) ?></td>
                                            <td class="text-end"><?= number_format((int) $t['linhas'], 0, ',', '.') ?></td>
                                            <td class="text-end"><?= number_format((float) $t['mb'], 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================ ARMAZENAMENTO ============================ -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-hard-drive text-primary me-2"></i>Armazenamento</h6>

                    <?php if ($disco['total'] !== null): ?>
                        <?php $pct = $disco['total'] > 0 ? round(($disco['usado'] / $disco['total']) * 100) : 0; ?>
                        <div class="d-flex justify-content-between text-small mb-1">
                            <span class="text-muted">Disco</span>
                            <span class="fw-bold">
                                <?= e(\App\Services\SystemInfo::formatarBytes($disco['livre'])) ?> livres
                                de <?= e(\App\Services\SystemInfo::formatarBytes($disco['total'])) ?>
                            </span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : ($pct > 75 ? 'bg-warning' : 'bg-success') ?>"
                                 style="width: <?= (int) $pct ?>%"></div>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                    <table class="table table-sm align-middle m-0 text-small">
                        <tbody>
                            <?php foreach ($pastas as $pasta): ?>
                                <tr>
                                    <td>
                                        <div class="font-monospace"><?= e($pasta['caminho']) ?></div>
                                        <div class="text-muted" style="font-size:.68rem;"><?= e($pasta['para']) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <?= e(\App\Services\SystemInfo::formatarBytes($pasta['tamanho'])) ?>
                                        <div class="text-muted" style="font-size:.68rem;"><?= (int) $pasta['arquivos'] ?> arquivo(s)</div>
                                    </td>
                                    <td class="text-end" style="width: 90px;">
                                        <?php if (!$pasta['existe']): ?>
                                            <span class="badge bg-secondary-subtle text-muted">não existe</span>
                                        <?php elseif ($pasta['gravavel']): ?>
                                            <span class="badge bg-success-subtle text-success">gravável</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">só leitura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ CONFERÊNCIAS DETALHADAS ============================ -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-list-check text-primary me-2"></i>Conferências</h6>

                    <?php foreach ($porGrupo as $grupo => $itens): ?>
                        <div class="text-muted text-uppercase fw-bold mb-2 mt-3" style="font-size:.68rem;"><?= e($grupo) ?></div>
                        <?php foreach ($itens as $item): ?>
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="fa-solid fa-<?= $item['ok'] ? 'circle-check text-success' : ($item['alerta'] ? 'circle-info text-info' : 'triangle-exclamation text-warning') ?> mt-1"></i>
                                <div class="text-small min-width-0">
                                    <div class="fw-bold"><?= e($item['item']) ?></div>
                                    <div class="text-muted text-break"><?= e($item['detalhe']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================ .ENV ============================ -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h6 class="fw-bold m-0"><i class="fa-solid fa-key text-warning me-2"></i>Arquivo <?= e(basename($envCaminho)) ?></h6>
                    <div class="text-muted text-small font-monospace text-break"><?= e($envCaminho) ?></div>
                    <?php if (basename($envCaminho) === '.env.producao'): ?>
                        <div class="text-muted text-small mt-1">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Ao enviar a pasta pelo FTP, este arquivo é substituído pelo <code>.env.producao</code> do seu computador.
                            Mudou algo aqui? Faça a mesma mudança no arquivo do computador.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($liberado): ?>
                    <form action="<?= url('admin/sistema/esconder') ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-eye-slash me-1"></i> Esconder valores
                            <span class="text-muted">(<?= (int) $expiraEm ?> min)</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$envExiste): ?>
                <div class="alert alert-danger text-small m-0">
                    O arquivo <code>.env</code> não foi encontrado. O sistema está rodando com os valores padrão.
                </div>

            <?php elseif (!$liberado): ?>
                <div class="alert alert-info d-flex align-items-start shadow-sm text-small">
                    <i class="fa-solid fa-lock fs-5 me-3 mt-1"></i>
                    <div>
                        Aqui dentro estão a senha do banco, a senha do e-mail e as chaves de pagamento.
                        Para ver ou alterar, confirme a sua senha — assim uma sessão esquecida aberta
                        não entrega tudo de uma vez.
                    </div>
                </div>

                <form action="<?= url('admin/sistema/liberar') ?>" method="POST"
                      class="row g-2 align-items-end" data-aguarde="Conferindo a sua senha...">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label text-small fw-bold" for="senhaAdmin">Sua senha</label>
                        <input type="password" class="form-control" id="senhaAdmin" name="senha"
                               autocomplete="current-password" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning fw-bold w-100">
                            <i class="fa-solid fa-unlock me-1"></i> Liberar por 10 minutos
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <div class="text-muted text-uppercase fw-bold mb-2" style="font-size:.68rem;">
                    Chaves presentes (<?= count($chaves) ?>)
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($chaves as $c): ?>
                        <span class="badge <?= $c['secreta'] ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-muted' ?> font-monospace">
                            <?php if ($c['secreta']): ?><i class="fa-solid fa-lock me-1"></i><?php endif; ?>
                            <?= e($c['chave']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <?php if (!$envGravavel): ?>
                    <div class="alert alert-warning text-small">
                        O arquivo existe mas está <strong>somente leitura</strong>. Dá para ver, não para alterar.
                        Ajuste a permissão para 644 no gerenciador de arquivos da hospedagem.
                    </div>
                <?php endif; ?>

                <form action="<?= url('admin/sistema/env') ?>" method="POST" data-aguarde="Gravando o .env e conferindo o banco...">
                    <?= csrf_field() ?>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle tabela-cartoes">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 34%;">Chave</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chaves as $c): ?>
                                    <tr>
                                        <td data-rotulo="Chave">
                                            <span class="font-monospace fw-bold text-small"><?= e($c['chave']) ?></span>
                                            <?php if ($c['secreta']): ?>
                                                <i class="fa-solid fa-lock text-warning ms-1" title="Valor sensível"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td data-rotulo="Valor">
                                            <input type="text"
                                                   class="form-control form-control-sm font-monospace"
                                                   name="env_<?= e($c['chave']) ?>"
                                                   value="<?= e((string) $c['valor']) ?>"
                                                   autocomplete="off"
                                                   <?= ($c['travada'] || !$envGravavel) ? 'readonly' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($envGravavel): ?>
                        <div class="row g-2 align-items-end mt-3 pt-3 border-top">
                            <div class="col-md-4">
                                <label class="form-label text-small fw-bold" for="novaChave">Acrescentar chave</label>
                                <input type="text" class="form-control form-control-sm font-monospace"
                                       id="novaChave" name="nova_chave" placeholder="NOVA_CHAVE">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-small fw-bold" for="novoValor">Valor</label>
                                <input type="text" class="form-control form-control-sm font-monospace"
                                       id="novoValor" name="novo_valor" placeholder="valor">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary fw-bold w-100">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Gravar .env
                                </button>
                            </div>
                        </div>

                        <div class="alert alert-secondary text-small mt-3 mb-0">
                            <i class="fa-solid fa-shield-halved me-2"></i>
                            Antes de gravar é feita uma cópia do arquivo. Se você mexer nos dados do banco,
                            eles são testados numa conexão nova <strong>antes</strong> de valer — dando errado,
                            nada é gravado. É o que impede um erro de digitação de derrubar o site.
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($copias !== []): ?>
                    <div class="mt-4">
                        <div class="text-muted text-uppercase fw-bold mb-2" style="font-size:.68rem;">
                            Cópias guardadas (storage/backups/env)
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($copias as $copia): ?>
                                <span class="badge bg-secondary-subtle text-muted font-monospace text-wrap text-break text-start">
                                    <?= e($copia['data']) ?> &middot; <?= e($copia['arquivo']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================ LOGS ============================ -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-file-lines text-primary me-2"></i>Últimos erros registrados</h6>

            <?php foreach ($logs as $arquivo => $log): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div class="fw-bold text-small">
                            <?= e($log['titulo']) ?>
                            <span class="text-muted font-monospace fw-normal">storage/logs/<?= e($arquivo) ?></span>
                        </div>
                        <?php if ($log['existe']): ?>
                            <span class="badge bg-secondary-subtle text-muted">
                                <?= e(\App\Services\SystemInfo::formatarBytes($log['tamanho'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$log['existe']): ?>
                        <div class="text-muted text-small">Nenhum registro — nada falhou até agora.</div>
                    <?php elseif ($log['linhas'] === []): ?>
                        <div class="text-muted text-small">Arquivo vazio.</div>
                    <?php else: ?>
                        <pre class="p-3 rounded-3 m-0 text-small"
                             style="background: var(--bg-sunken); max-height: 220px; overflow: auto; white-space: pre-wrap; word-break: break-word;"><?php
                            foreach ($log['linhas'] as $linha) {
                                echo e($linha) . "\n";
                            }
                        ?></pre>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
