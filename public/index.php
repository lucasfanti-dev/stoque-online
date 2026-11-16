<?php

declare(strict_types=1);

/**
 * =====================================================================
 * StoqueOnline v2 - Front Controller
 * =====================================================================
 */

$baseDir = dirname(__DIR__);

// 1. Registrar Autoloader PSR-4 Nativo
require_once $baseDir . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($baseDir . '/app');

// 2. Carregar Configurações de Ambiente e Helpers Globais
require_once $baseDir . '/config/app.php';

// 2.1 Banco em dia: aplica sozinho as migrações que subiram junto com os
// arquivos. Nas visitas seguintes é só uma comparação de lista.
// O method_exists cobre o meio de um envio pelo FTP, quando este arquivo já
// chegou e o Migrator novo ainda não.
if (method_exists(\App\Core\Migrator::class, 'aplicarPendentesSozinho')) {
    \App\Core\Migrator::aplicarPendentesSozinho();
}

// 3. Inicializar Sessão Segura
\App\Core\Session::start();

// 4. Instanciar Roteador
$router = new \App\Core\Router();

// ---------------------------------------------------------------------
// Rotas Públicas / Institucionais
// ---------------------------------------------------------------------
$router->get('/', [\App\Controllers\HomeController::class, 'index']);
$router->get('/sobre-nos', [\App\Controllers\PageController::class, 'about']);
$router->get('/perguntas-frequentes', [\App\Controllers\PageController::class, 'faq']);
$router->get('/contato', [\App\Controllers\PageController::class, 'contact']);
$router->post('/contato', [\App\Controllers\PageController::class, 'sendContact']);
$router->get('/sitemap.xml', [\App\Controllers\PageController::class, 'sitemap']);

// ---------------------------------------------------------------------
// Rotas de Busca de Rolamentos
// ---------------------------------------------------------------------
$router->get('/pesquisa', [\App\Controllers\SearchController::class, 'index']);
$router->post('/api/pesquisa/buscar', [\App\Controllers\SearchController::class, 'query']);
$router->post('/api/pesquisa/modelos', [\App\Controllers\SearchController::class, 'models']);
$router->get('/api/pesquisa/fornecedor', [\App\Controllers\SearchController::class, 'supplier']);
$router->get('/api/pesquisa/fotos', [\App\Controllers\SearchController::class, 'photos']);
$router->get('/pesquisa/exportar', [\App\Controllers\SearchController::class, 'export'], ['auth']);

// ---------------------------------------------------------------------
// Rotas de Autenticação
// ---------------------------------------------------------------------
$router->get('/login', [\App\Controllers\AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [\App\Controllers\AuthController::class, 'login'], ['guest']);

$router->get('/cadastro', [\App\Controllers\AuthController::class, 'showRegister'], ['guest']);
$router->post('/cadastro', [\App\Controllers\AuthController::class, 'register'], ['guest']);

$router->get('/ativar', [\App\Controllers\AuthController::class, 'activate']);

// Confirmação de cadastro por código de 6 dígitos
$router->get('/confirmar-email', [\App\Controllers\AuthController::class, 'showConfirm'], ['guest']);
$router->post('/confirmar-email', [\App\Controllers\AuthController::class, 'confirm'], ['guest']);
$router->post('/confirmar-email/reenviar', [\App\Controllers\AuthController::class, 'resendConfirm'], ['guest']);

$router->get('/esqueci-senha', [\App\Controllers\AuthController::class, 'showForgot'], ['guest']);
$router->post('/esqueci-senha', [\App\Controllers\AuthController::class, 'forgot'], ['guest']);

$router->get('/redefinir-senha', [\App\Controllers\AuthController::class, 'showReset']);
$router->post('/redefinir-senha', [\App\Controllers\AuthController::class, 'reset']);
$router->post('/redefinir-senha/reenviar', [\App\Controllers\AuthController::class, 'resendReset']);

$router->get('/logout', [\App\Controllers\AuthController::class, 'logout'], ['auth']);

// ---------------------------------------------------------------------
// Rotas de Estoque (Upload e Gestão)
// ---------------------------------------------------------------------
$router->get('/estoque/upload', [\App\Controllers\InventoryController::class, 'showUpload'], ['license']);
$router->post('/estoque/upload', [\App\Controllers\InventoryController::class, 'upload'], ['license']);
$router->post('/estoque/lote/excluir', [\App\Controllers\InventoryController::class, 'deleteBatch'], ['auth']);
$router->post('/estoque/historico/limpar', [\App\Controllers\InventoryController::class, 'clearBatchHistory'], ['auth']);
$router->get('/estoque/meu-estoque', [\App\Controllers\InventoryController::class, 'myStock'], ['auth']);
$router->post('/estoque/item/adicionar', [\App\Controllers\InventoryController::class, 'addItem'], ['license']);
$router->post('/estoque/item/quantidade', [\App\Controllers\InventoryController::class, 'updateItemQuantity'], ['license']);
$router->post('/estoque/item/excluir', [\App\Controllers\InventoryController::class, 'deleteItem'], ['license']);
$router->post('/estoque/foto/enviar', [\App\Controllers\InventoryController::class, 'uploadPhoto'], ['license']);
$router->post('/estoque/foto/excluir', [\App\Controllers\InventoryController::class, 'deletePhoto'], ['license']);
$router->post('/estoque/foto/principal', [\App\Controllers\InventoryController::class, 'setMainPhoto'], ['license']);
$router->get('/estoque/exportar', [\App\Controllers\InventoryController::class, 'exportMyStock'], ['auth']);
$router->get('/estoque/modelo-download', [\App\Controllers\InventoryController::class, 'downloadTemplate']);

// ---------------------------------------------------------------------
// Rotas da Empresa e Colaboradores
// ---------------------------------------------------------------------
$router->get('/empresa', [\App\Controllers\CompanyController::class, 'profile'], ['auth']);
$router->post('/empresa/salvar', [\App\Controllers\CompanyController::class, 'updateProfile'], ['auth']);
$router->post('/empresa/colaborador/adicionar', [\App\Controllers\CompanyController::class, 'addContact'], ['auth']);
$router->post('/empresa/colaborador/excluir', [\App\Controllers\CompanyController::class, 'deleteContact'], ['auth']);
$router->post('/empresa/colaborador/licenca', [\App\Controllers\CompanyController::class, 'toggleLicense'], ['auth']);

// ---------------------------------------------------------------------
// Rotas de Planos, Pagamentos e PicPay
// ---------------------------------------------------------------------
$router->get('/planos', [\App\Controllers\PaymentController::class, 'plans']);
$router->post('/planos/ativar-teste-gratis', [\App\Controllers\PaymentController::class, 'claimFreeTrial'], ['auth']);
$router->get('/checkout/{id}', [\App\Controllers\PaymentController::class, 'checkout'], ['auth']);
$router->post('/checkout/processar', [\App\Controllers\PaymentController::class, 'processCheckout'], ['auth']);
$router->post('/pagamento/callback', [\App\Controllers\PaymentController::class, 'webhook']);
$router->post('/pagamento/webhook/{slug}', [\App\Controllers\PaymentController::class, 'webhook']);
$router->get('/pagamento/sucesso', [\App\Controllers\PaymentController::class, 'success']);
$router->get('/pedidos', [\App\Controllers\PaymentController::class, 'orders'], ['auth']);
$router->post('/pedidos/verificar', [\App\Controllers\PaymentController::class, 'checkOrder'], ['auth']);
$router->post('/pedidos/cancelar', [\App\Controllers\PaymentController::class, 'cancelOrder'], ['auth']);

// ---------------------------------------------------------------------
// Rotas Administrativas
// ---------------------------------------------------------------------
$router->get('/admin', [\App\Controllers\AdminController::class, 'dashboard'], ['admin']);

// Usuários
$router->get('/admin/usuarios', [\App\Controllers\AdminController::class, 'users'], ['admin']);
$router->post('/admin/usuario/editar', [\App\Controllers\AdminController::class, 'editUser'], ['admin']);
$router->post('/admin/usuario/salvar', [\App\Controllers\AdminController::class, 'saveUser'], ['admin']);
$router->post('/admin/usuario/excluir', [\App\Controllers\AdminController::class, 'deleteUser'], ['admin']);

// Estoque de qualquer fornecedor
$router->get('/admin/estoque', [\App\Controllers\AdminInventoryController::class, 'index'], ['admin']);
$router->get('/admin/estoque/{id}', [\App\Controllers\AdminInventoryController::class, 'show'], ['admin']);
$router->get('/admin/estoque/{id}/exportar', [\App\Controllers\AdminInventoryController::class, 'export'], ['admin']);
$router->post('/admin/estoque/item/excluir', [\App\Controllers\AdminInventoryController::class, 'deleteItem'], ['admin']);
$router->post('/admin/estoque/item/quantidade', [\App\Controllers\AdminInventoryController::class, 'updateItemQuantity'], ['admin']);
$router->post('/admin/estoque/item/adicionar', [\App\Controllers\AdminInventoryController::class, 'addItem'], ['admin']);
$router->post('/admin/estoque/limpar', [\App\Controllers\AdminInventoryController::class, 'clearStock'], ['admin']);
$router->post('/admin/estoque/lote/excluir', [\App\Controllers\AdminInventoryController::class, 'deleteBatch'], ['admin']);
$router->post('/admin/estoque/historico/limpar', [\App\Controllers\AdminInventoryController::class, 'clearHistory'], ['admin']);
$router->post('/admin/estoque/enviar', [\App\Controllers\AdminInventoryController::class, 'upload'], ['admin']);

// Tela técnica do sistema (.env, servidor, banco, logs)
$router->get('/admin/sistema', [\App\Controllers\AdminSystemController::class, 'index'], ['admin']);
$router->post('/admin/sistema/liberar', [\App\Controllers\AdminSystemController::class, 'unlock'], ['admin']);
$router->post('/admin/sistema/esconder', [\App\Controllers\AdminSystemController::class, 'lock'], ['admin']);
$router->post('/admin/sistema/env', [\App\Controllers\AdminSystemController::class, 'saveEnv'], ['admin']);

// Meios de pagamento
$router->get('/admin/meios-pagamento', [\App\Controllers\AdminGatewayController::class, 'index'], ['admin']);
$router->post('/admin/meios-pagamento/salvar', [\App\Controllers\AdminGatewayController::class, 'save'], ['admin']);

// Configurações do site
$router->get('/admin/configuracoes', [\App\Controllers\AdminSettingsController::class, 'index'], ['admin']);
$router->post('/admin/configuracoes/salvar', [\App\Controllers\AdminSettingsController::class, 'save'], ['admin']);

// Planos
$router->get('/admin/planos', [\App\Controllers\AdminController::class, 'plans'], ['admin']);
$router->post('/admin/plano/salvar', [\App\Controllers\AdminController::class, 'savePlan'], ['admin']);
$router->post('/admin/plano/status', [\App\Controllers\AdminController::class, 'togglePlan'], ['admin']);
$router->post('/admin/plano/excluir', [\App\Controllers\AdminController::class, 'deletePlan'], ['admin']);

// Pagamentos
$router->get('/admin/pagamentos', [\App\Controllers\AdminController::class, 'payments'], ['admin']);
$router->post('/admin/pagamento/verificar', [\App\Controllers\AdminController::class, 'verifyPayment'], ['admin']);
$router->post('/admin/pagamento/liberar', [\App\Controllers\AdminController::class, 'releasePayment'], ['admin']);

// Auditoria
$router->get('/admin/logs', [\App\Controllers\AdminController::class, 'logs'], ['admin']);

// Despachar a requisição
$router->dispatch();
