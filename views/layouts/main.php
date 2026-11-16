<?php
use App\Core\Auth;
$currentUser = Auth::user();
$isLoggedIn = Auth::check();
$isAdmin = Auth::isAdmin();
$hasLicense = Auth::hasActiveLicense();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        /* Aplica o tema salvo antes de a página pintar, para não haver
           o clarão branco de um quadro antes do tema escuro entrar. */
        (function () {
            try {
                var salvo = localStorage.getItem('stoque_tema');
                var tema = salvo === 'light' || salvo === 'dark'
                    ? salvo
                    : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', tema);
                document.documentElement.setAttribute('data-bs-theme', tema);
            } catch (e) {}
        })();
    </script>
    <title><?= e($title ?? 'StoqueOnline - Rede de Estoques de Rolamentos Industriais') ?></title>
    <meta name="description" content="<?= e($metaDescription ?? 'Encontre rolamentos industriais nos distribuidores mais próximos em todo o Brasil. SKF, NSK, TIMKEN, FAG e mais.') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- Tipografia: texto, títulos e dados técnicos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Sistema visual do StoqueOnline -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100" data-base-uri="<?= e(base_uri()) ?>">

    <!-- Top Bar de Informações -->
    <div class="topbar py-1 text-small">
        <div class="container d-flex justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="<?= e(\App\Models\Setting::telefoneLink()) ?>" class="topbar-link">
                    <i class="fa-solid fa-phone me-1 text-primary"></i> <?= e(setting('contato_telefone')) ?>
                </a>
                <a href="mailto:<?= e(setting('contato_email')) ?>" class="topbar-link text-truncate">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i> <?= e(setting('contato_email')) ?>
                </a>
            </div>
            <div class="d-none d-md-block text-nowrap">
                <i class="fa-solid fa-clock me-1 text-primary"></i> <?= e(setting('contato_horario')) ?>
            </div>
        </div>
    </div>

    <!-- Barra de Navegação Principal -->
    <nav class="navbar navbar-expand-xl navbar-dark bg-dark sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= url() ?>">
                <div class="logo-badge me-2">
                    <i class="fa-solid fa-circle-notch fa-spin text-primary" style="--fa-animation-duration: 15s;"></i>
                </div>
                <span class="fw-bold tracking-tight">STOQUE<span class="text-primary">ONLINE</span></span>
                <span class="badge bg-primary-subtle text-primary ms-2 fs-8 text-uppercase">v2.0</span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'pesquisa') ? 'active' : '' ?>" href="<?= url('pesquisa') ?>">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Pesquisar Estoque
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'planos') ? 'active' : '' ?>" href="<?= url('planos') ?>">
                            <i class="fa-solid fa-tags me-1"></i> Planos & Preços
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'estoque/upload') ? 'active' : '' ?>" href="<?= url('estoque/upload') ?>">
                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> Carregar Estoque
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-circle-info me-1"></i> Institucional
                        </a>
                        <ul class="dropdown-menu shadow border-0">
                            <li><a class="dropdown-item" href="<?= url('sobre-nos') ?>"><i class="fa-solid fa-users me-2 text-muted"></i> Sobre Nós</a></li>
                            <li><a class="dropdown-item" href="<?= url('perguntas-frequentes') ?>"><i class="fa-solid fa-circle-question me-2 text-muted"></i> Perguntas Frequentes</a></li>
                            <li><a class="dropdown-item" href="<?= url('contato') ?>"><i class="fa-solid fa-envelope me-2 text-muted"></i> Fale Conosco</a></li>
                        </ul>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item me-2 my-2 my-lg-0">
                        <button type="button" class="theme-toggle" id="botaoTema"
                                aria-label="Alternar entre tema claro e escuro">
                            <i class="fa-solid fa-sun" id="iconeTema"></i>
                            <span id="textoTema">Tema claro</span>
                        </button>
                    </li>

                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle conta-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-circle-user me-1 text-primary"></i><span class="conta-nome"><?= e($currentUser['name'] ?? 'Minha Conta') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="small text-muted">Logado como:</div>
                                    <div class="fw-bold text-truncate" style="max-width: 200px;"><?= e($currentUser['email']) ?></div>
                                    <?php if ($hasLicense): ?>
                                        <span class="badge bg-success-subtle text-success mt-1"><i class="fa-solid fa-check me-1"></i> Licença Ativa</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger mt-1"><i class="fa-solid fa-xmark me-1"></i> Sem Licença</span>
                                    <?php endif; ?>
                                </li>
                                <li><a class="dropdown-item py-2" href="<?= url('empresa') ?>"><i class="fa-solid fa-building me-2 text-muted"></i> Minha Empresa</a></li>
                                <li><a class="dropdown-item py-2" href="<?= url('estoque/meu-estoque') ?>"><i class="fa-solid fa-boxes-stacked me-2 text-muted"></i> Meu Estoque Ativo</a></li>
                                <li><a class="dropdown-item py-2" href="<?= url('pedidos') ?>"><i class="fa-solid fa-receipt me-2 text-muted"></i> Histórico de Pagamentos</a></li>

                                <?php if ($isAdmin): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li class="dropdown-header dropdown-header-admin">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Administração
                                    </li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin') ?>"><i class="fa-solid fa-gauge-high me-2 text-muted"></i> Visão Geral</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/usuarios') ?>"><i class="fa-solid fa-users me-2 text-muted"></i> Usuários &amp; Licenças</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/estoque') ?>"><i class="fa-solid fa-boxes-stacked me-2 text-muted"></i> Estoques dos Fornecedores</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/planos') ?>"><i class="fa-solid fa-tags me-2 text-muted"></i> Planos &amp; Preços</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/pagamentos') ?>"><i class="fa-solid fa-file-invoice-dollar me-2 text-muted"></i> Conciliação de Pagamentos</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/meios-pagamento') ?>"><i class="fa-solid fa-credit-card me-2 text-muted"></i> Meios de Pagamento</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/logs') ?>"><i class="fa-solid fa-clock-rotate-left me-2 text-muted"></i> Auditoria de Logs</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/configuracoes') ?>"><i class="fa-solid fa-sliders me-2 text-muted"></i> Configurações do Site</a></li>
                                    <li><a class="dropdown-item py-2" href="<?= url('admin/sistema') ?>"><i class="fa-solid fa-screwdriver-wrench me-2 text-muted"></i> Sistema</a></li>
                                <?php endif; ?>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?= url('logout') ?>"><i class="fa-solid fa-right-from-bracket me-2"></i> Sair</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a class="btn btn-outline-light btn-sm px-3" href="<?= url('login') ?>">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Entrar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm px-3" href="<?= url('cadastro') ?>">
                                <i class="fa-solid fa-user-plus me-1"></i> Criar Conta
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Alertas Flash -->
    <div class="container mt-3">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-check fs-5 me-2"></i>
                <div><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i>
                <div><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = flash('warning')): ?>
            <div class="alert alert-warning alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-5 me-2"></i>
                <div><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = flash('info')): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-info fs-5 me-2"></i>
                <div><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Conteúdo Principal -->
    <main class="flex-grow-1">
        <?= $content ?>
    </main>

    <!-- Rodapé -->
    <footer class="bg-dark text-white pt-5 pb-4 mt-5 border-top border-secondary">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fa-solid fa-circle-notch text-primary fa-spin me-2 fs-4" style="--fa-animation-duration: 15s;"></i>
                        <h5 class="fw-bold m-0">STOQUE<span class="text-primary">ONLINE</span></h5>
                    </div>
                    <p class="text-white-50 text-small">
                        A plataforma que conecta distribuidores de rolamentos e suprimentos de manutenção industrial a compradores em todo o território nacional.
                    </p>
                    <p class="text-white-50 text-small">
                        <i class="fa-solid fa-location-dot me-2 text-primary"></i> <?= e(setting('contato_endereco')) ?>
                    </p>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase fw-bold text-white mb-3">Navegação</h6>
                    <ul class="list-unstyled text-small">
                        <li class="mb-2"><a href="<?= url('pesquisa') ?>" class="text-white-50 text-decoration-none hover-white">Pesquisar Peças</a></li>
                        <li class="mb-2"><a href="<?= url('planos') ?>" class="text-white-50 text-decoration-none hover-white">Planos de Assinatura</a></li>
                        <li class="mb-2"><a href="<?= url('estoque/upload') ?>" class="text-white-50 text-decoration-none hover-white">Upload de Estoque</a></li>
                        <li class="mb-2"><a href="<?= url('cadastro') ?>" class="text-white-50 text-decoration-none hover-white">Cadastre sua Empresa</a></li>
                        <li class="mb-2"><a href="<?= url('sobre-nos') ?>" class="text-white-50 text-decoration-none hover-white">Sobre Nós</a></li>
                        <li class="mb-2"><a href="<?= url('perguntas-frequentes') ?>" class="text-white-50 text-decoration-none hover-white">Perguntas Frequentes</a></li>
                        <li class="mb-2"><a href="<?= url('contato') ?>" class="text-white-50 text-decoration-none hover-white">Fale Conosco</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="text-uppercase fw-bold text-white mb-3">Marcas em Destaque</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-secondary text-white-50">SKF</span>
                        <span class="badge bg-secondary text-white-50">NSK</span>
                        <span class="badge bg-secondary text-white-50">TIMKEN</span>
                        <span class="badge bg-secondary text-white-50">FAG</span>
                        <span class="badge bg-secondary text-white-50">NTN</span>
                        <span class="badge bg-secondary text-white-50">INA</span>
                        <span class="badge bg-secondary text-white-50">KOYO</span>
                        <span class="badge bg-secondary text-white-50">SNR</span>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="text-uppercase fw-bold text-white mb-3">Atendimento</h6>
                    <p class="text-white-50 text-small mb-2">Dúvidas, parcerias ou suporte comercial:</p>
                    <div class="d-flex align-items-center mb-2">
                        <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm w-100 fw-bold">
                            <i class="fa-brands fa-whatsapp me-2"></i> WhatsApp Direto
                        </a>
                    </div>
                    <div class="text-white-50 text-small">
                        <i class="fa-solid fa-envelope me-1 text-primary"></i> <?= e(setting('contato_email')) ?>
                    </div>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start text-white-50 text-small">
                    &copy; <?= date('Y') ?> StoqueOnline v2. Todos os direitos reservados.
                </div>
                <div class="col-md-6 text-center text-md-end text-white-50 text-small mt-2 mt-md-0">
                    Desenvolvido por
                    <a href="https://lfdev.tech/" target="_blank" rel="noopener" class="text-decoration-none fw-bold">lfdev.tech</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- App Script -->
    <script src="<?= asset('js/app.js') ?>"></script>

    <script>
        /* Alternância de tema. A preferência fica no navegador de quem usa,
           então cada pessoa escolhe a sua sem afetar as demais. */
        (function () {
            var raiz = document.documentElement;
            var botao = document.getElementById('botaoTema');
            var icone = document.getElementById('iconeTema');
            var texto = document.getElementById('textoTema');

            function pintar(tema) {
                raiz.setAttribute('data-theme', tema);
                raiz.setAttribute('data-bs-theme', tema);

                var escuro = tema === 'dark';
                icone.className = escuro ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
                texto.textContent = escuro ? 'Tema claro' : 'Tema escuro';
                botao.title = escuro ? 'Mudar para o tema claro' : 'Mudar para o tema escuro';
            }

            pintar(raiz.getAttribute('data-theme') || 'dark');

            botao.addEventListener('click', function () {
                var novo = raiz.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

                try {
                    localStorage.setItem('stoque_tema', novo);
                } catch (e) {}

                pintar(novo);
            });
        })();
    </script>
</body>
</html>
