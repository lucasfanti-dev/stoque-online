<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\Product;
use App\Models\License;
use App\Services\Gateways\Gateways;
use App\Services\LicenseService;

class AdminController extends Controller
{
    /**
     * Visão geral do sistema.
     */
    public function dashboard(): void
    {
        $numeros = Database::fetch(
            "SELECT
                (SELECT COUNT(*) FROM users) AS usuarios,
                (SELECT COUNT(*) FROM users WHERE allowed_access = 0) AS bloqueados,
                (SELECT COUNT(*) FROM users WHERE confirmed_activation = 0) AS nao_ativados,
                (SELECT COUNT(*) FROM users WHERE is_admin = 1) AS administradores,
                (SELECT COUNT(*) FROM companies) AS empresas,
                (SELECT COUNT(*) FROM inventory) AS itens,
                (SELECT COALESCE(SUM(quantity), 0) FROM inventory) AS pecas,
                (SELECT COUNT(DISTINCT user_id) FROM inventory) AS fornecedores_ativos,
                (SELECT COUNT(*) FROM user_licenses WHERE status = 1 AND is_closed = 0 AND end_date >= CURDATE()) AS licencas_ativas,
                (SELECT COUNT(*) FROM user_licenses WHERE is_closed = 0 AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS licencas_vencendo,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status IN ('paid','completed')) AS faturamento_total,
                (SELECT COUNT(*) FROM payments WHERE status = 'pending') AS pagamentos_pendentes,
                (SELECT COUNT(*) FROM payments WHERE status IN ('paid','completed') AND license_id IS NULL) AS pagamentos_sem_licenca"
        );

        $vencendo = Database::fetchAll(
            "SELECT u.id, u.name, u.email, ul.end_date, p.name AS plano
               FROM user_licenses ul
               JOIN users u ON u.id = ul.user_id
               JOIN plans p ON p.id = ul.plan_id
              WHERE ul.is_closed = 0
                AND ul.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              ORDER BY ul.end_date ASC
              LIMIT 10"
        );

        $ultimosAcessos = Database::fetchAll(
            "SELECT l.*, u.name AS user_name
               FROM user_logs l
               LEFT JOIN users u ON u.id = l.user_id
              ORDER BY l.id DESC
              LIMIT 8"
        );

        $maioresEstoques = Database::fetchAll(
            "SELECT u.id, u.name, c.trading_name, COUNT(i.id) AS itens
               FROM inventory i
               JOIN users u ON u.id = i.user_id
               LEFT JOIN companies c ON c.user_id = u.id
              GROUP BY u.id, u.name, c.trading_name
              ORDER BY itens DESC
              LIMIT 8"
        );

        $this->render('admin/dashboard', [
            'numeros'         => $numeros ?? [],
            'vencendo'        => $vencendo,
            'ultimosAcessos'  => $ultimosAcessos,
            'maioresEstoques' => $maioresEstoques,
        ]);
    }

    public function users(): void
    {
        $search = $this->text('busca', '', 100);

        $this->render('admin/users', [
            'users'  => User::getAll($search),
            'plans'  => Product::getAllActive(true),
            'search' => $search,
        ]);
    }

    public function editUser(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $action = (string) $this->input('action', '');
        $adminId = (int) Auth::id();

        $user = User::findById($userId);
        if (!$user) {
            $this->redirect('admin/usuarios', ['error' => 'Usuário não encontrado.']);
        }

        switch ($action) {
            case 'toggle_access':
                if ($userId === $adminId) {
                    $this->redirect('admin/usuarios', ['error' => 'Você não pode bloquear a sua própria conta.']);
                }

                $newStatus = empty($user['allowed_access']) ? 1 : 0;

                // Não deixar o sistema sem nenhum administrador ativo
                if ($newStatus === 0 && !empty($user['is_admin']) && User::countOtherAdmins($userId) === 0) {
                    $this->redirect('admin/usuarios', [
                        'error' => 'Este é o último administrador ativo. Promova outro usuário antes de bloqueá-lo.',
                    ]);
                }

                User::update($userId, ['allowed_access' => $newStatus]);
                Auth::logAudit($adminId, $user['email'], $newStatus ? 'admin_unblocked_user' : 'admin_blocked_user');

                $this->redirect('admin/usuarios', [
                    'success' => $newStatus ? 'Acesso liberado para o usuário.' : 'Acesso bloqueado para o usuário.',
                ]);
                break;

            case 'toggle_admin':
                if ($userId === $adminId) {
                    $this->redirect('admin/usuarios', ['error' => 'Você não pode alterar as suas próprias permissões.']);
                }

                $newAdmin = empty($user['is_admin']) ? 1 : 0;

                if ($newAdmin === 0 && User::countOtherAdmins($userId) === 0) {
                    $this->redirect('admin/usuarios', [
                        'error' => 'Não é possível remover o último administrador do sistema.',
                    ]);
                }

                User::update($userId, ['is_admin' => $newAdmin]);
                Auth::logAudit($adminId, $user['email'], $newAdmin ? 'admin_granted' : 'admin_revoked');

                $this->redirect('admin/usuarios', ['success' => 'Permissões de administrador atualizadas.']);
                break;

            case 'confirm_activation':
                User::update($userId, [
                    'confirmed_activation' => 1,
                    'token'                => null,
                    'token_expires_at'     => null,
                ]);
                Auth::logAudit($adminId, $user['email'], 'admin_activated_account');

                $this->redirect('admin/usuarios', ['success' => 'Conta ativada manualmente com sucesso.']);
                break;

            case 'grant_license':
                $planId = (int) $this->input('plan_id', 0);
                $plan = Product::findById($planId, true);

                if (!$plan) {
                    $this->redirect('admin/usuarios', ['error' => 'Plano selecionado é inválido.']);
                }

                // Sem dias informados, vale a duração própria do plano
                $days = (int) $this->input('days', 0);
                $days = $days > 0 ? min($days, 3650) : (int) $plan['duration_days'];

                $startDate = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime("+{$days} days"));

                License::create([
                    'user_id'    => $userId,
                    'plan_id'    => (int) $plan['id'],
                    'price'      => 0.00,
                    'max_users'  => (int) $plan['max_users'],
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'status'     => 1,
                    'is_closed'  => 0,
                    'origin'     => 'manual',
                ]);

                Auth::logAudit($adminId, $user['email'], 'admin_granted_license');

                $this->redirect('admin/usuarios', [
                    'success' => "Licença do plano '{$plan['name']}' concedida até " . date('d/m/Y', strtotime($endDate)),
                ]);
                break;

            case 'revoke_license':
                $licenseId = (int) $this->input('license_id', 0);

                if ($licenseId > 0) {
                    License::cancel($licenseId, $userId);
                } else {
                    // Encerra todas as licenças vigentes do usuário
                    Database::query(
                        "UPDATE user_licenses SET status = 0, is_closed = 1
                          WHERE user_id = :user_id AND is_closed = 0",
                        ['user_id' => $userId]
                    );
                }

                Auth::logAudit($adminId, $user['email'], 'admin_revoked_license');
                $this->redirect('admin/usuarios', ['success' => 'Licença encerrada com sucesso.']);
                break;

            case 'reset_free_trial':
                User::update($userId, ['first_access_free' => 1]);
                Auth::logAudit($adminId, $user['email'], 'admin_reset_free_trial');

                $this->redirect('admin/usuarios', ['success' => 'Direito ao teste gratuito liberado novamente.']);
                break;
        }

        $this->redirect('admin/usuarios', ['warning' => 'Ação não reconhecida.']);
    }

    /**
     * Edita os dados cadastrais de qualquer usuário.
     */
    public function saveUser(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/usuarios', ['error' => 'Usuário não encontrado.']);
        }

        $nome = $this->text('name', '', 150);
        $email = mb_strtolower($this->text('email', '', 255));
        $telefone = $this->text('phone', '', 50);

        if ($nome === '' || $email === '') {
            $this->redirect('admin/usuarios', ['error' => 'Nome e e-mail são obrigatórios.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('admin/usuarios', ['error' => 'E-mail inválido.']);
        }

        // O e-mail é a chave de login: não pode colidir com outra conta
        $emQueOutro = Database::fetchColumn(
            "SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1",
            ['email' => $email, 'id' => $userId]
        );

        if ($emQueOutro) {
            $this->redirect('admin/usuarios', ['error' => 'Já existe outra conta com este e-mail.']);
        }

        User::update($userId, ['name' => $nome, 'email' => $email, 'phone' => $telefone]);

        Auth::logAudit((int) Auth::id(), $user['email'], 'admin_edited_user');

        $this->redirect('admin/usuarios', ['success' => 'Dados de ' . $nome . ' atualizados.']);
    }

    /**
     * Exclui uma conta em definitivo.
     */
    public function deleteUser(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $confirmacao = $this->text('confirmacao', '', 20);
        $adminId = (int) Auth::id();

        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/usuarios', ['error' => 'Usuário não encontrado.']);
        }

        if ($userId === $adminId) {
            $this->redirect('admin/usuarios', ['error' => 'Você não pode excluir a sua própria conta.']);
        }

        if (!empty($user['is_admin']) && User::countOtherAdmins($userId) === 0) {
            $this->redirect('admin/usuarios', ['error' => 'Não é possível excluir o último administrador.']);
        }

        if ($confirmacao !== 'EXCLUIR') {
            $this->redirect('admin/usuarios', ['error' => 'Digite EXCLUIR para confirmar a remoção da conta.']);
        }

        // Pagamentos são apagados em cascata junto com o usuário, e isso
        // levaria embora o histórico financeiro da empresa.
        if (User::hasPayments($userId)) {
            $this->redirect('admin/usuarios', [
                'error' => 'Esta conta possui pagamentos registrados e não pode ser excluída '
                    . '(o histórico financeiro seria perdido). Bloqueie o acesso no lugar.',
            ]);
        }

        $email = (string) $user['email'];

        // As linhas de product_photos saem por cascata, mas os arquivos
        // precisam ser apagados do disco explicitamente.
        $imagens = \App\Services\ImageService::apagarPastaDoUsuario($userId);

        User::delete($userId);

        Auth::logAudit($adminId, $email, 'admin_deleted_user');

        if ($imagens > 0) {
            error_log("[StoqueOnline] {$imagens} imagem(ns) removida(s) com a conta {$email}.");
        }

        $this->redirect('admin/usuarios', [
            'success' => 'Conta de ' . $user['name'] . ' excluída junto com estoque, empresa e licenças.',
        ]);
    }

    // -----------------------------------------------------------------
    // Planos
    // -----------------------------------------------------------------

    public function plans(): void
    {
        $this->render('admin/plans', ['plans' => Product::getAllForAdmin()]);
    }

    public function savePlan(): void
    {
        $this->validateCsrf();

        $planId = (int) $this->input('plan_id', 0);

        $nome = $this->text('name', '', 100);
        $periodo = $this->text('period_name', '', 50);
        $dias = (int) $this->input('duration_days', 0);
        $preco = (float) str_replace(',', '.', (string) $this->input('price', '0'));
        $usuarios = (int) $this->input('max_users', 1);

        if ($nome === '' || $periodo === '') {
            $this->redirect('admin/planos', ['error' => 'Informe o nome e o período do plano.']);
        }

        if ($dias < 1 || $dias > 3650) {
            $this->redirect('admin/planos', ['error' => 'A duração deve ficar entre 1 e 3650 dias.']);
        }

        if ($preco < 0) {
            $this->redirect('admin/planos', ['error' => 'O preço não pode ser negativo.']);
        }

        $dados = [
            'name'          => $nome,
            'period_name'   => $periodo,
            'duration_days' => $dias,
            'price'         => round($preco, 2),
            'max_users'     => max(1, min($usuarios, 999)),
            'description'   => $this->text('description', '', 500),
            'is_free_trial' => (int) $this->input('is_free_trial', 0) === 1 ? 1 : 0,
            'status'        => (int) $this->input('status', 1) === 1 ? 1 : 0,
        ];

        // Só pode existir um plano marcado como teste gratuito
        if ($dados['is_free_trial'] === 1) {
            Database::query(
                "UPDATE plans SET is_free_trial = 0 WHERE is_free_trial = 1" . ($planId > 0 ? " AND id <> :id" : ''),
                $planId > 0 ? ['id' => $planId] : []
            );
        }

        if ($planId > 0) {
            if (!Product::findById($planId, true)) {
                $this->redirect('admin/planos', ['error' => 'Plano não encontrado.']);
            }

            Product::update($planId, $dados);
            Auth::logAudit((int) Auth::id(), $nome, 'admin_updated_plan');

            $this->redirect('admin/planos', ['success' => 'Plano "' . $nome . '" atualizado.']);
        }

        Product::create($dados);
        Auth::logAudit((int) Auth::id(), $nome, 'admin_created_plan');

        $this->redirect('admin/planos', ['success' => 'Plano "' . $nome . '" criado.']);
    }

    public function togglePlan(): void
    {
        $this->validateCsrf();

        $plan = Product::toggleStatus((int) $this->input('plan_id', 0));

        if (!$plan) {
            $this->redirect('admin/planos', ['error' => 'Plano não encontrado.']);
        }

        Auth::logAudit((int) Auth::id(), (string) $plan['name'], 'admin_toggled_plan');

        $this->redirect('admin/planos', [
            'success' => empty($plan['status'])
                ? 'Plano "' . $plan['name'] . '" desativado. Quem já assinou continua com a licença até o vencimento.'
                : 'Plano "' . $plan['name'] . '" reativado e disponível para contratação.',
        ]);
    }

    public function deletePlan(): void
    {
        $this->validateCsrf();

        $planId = (int) $this->input('plan_id', 0);
        $plan = Product::findById($planId, true);

        if (!$plan) {
            $this->redirect('admin/planos', ['error' => 'Plano não encontrado.']);
        }

        if (!Product::canDelete($planId)) {
            $this->redirect('admin/planos', [
                'error' => 'Este plano já foi usado em licenças ou cobranças e não pode ser apagado. Desative-o.',
            ]);
        }

        Product::delete($planId);
        Auth::logAudit((int) Auth::id(), (string) $plan['name'], 'admin_deleted_plan');

        $this->redirect('admin/planos', ['success' => 'Plano "' . $plan['name'] . '" excluído.']);
    }

    // -----------------------------------------------------------------
    // Pagamentos
    // -----------------------------------------------------------------

    /**
     * Reconsulta o status da cobrança no PicPay.
     */
    public function verifyPayment(): void
    {
        $this->validateCsrf();

        $paymentId = (int) $this->input('payment_id', 0);
        $payment = Database::fetch("SELECT * FROM payments WHERE id = :id LIMIT 1", ['id' => $paymentId]);

        if (!$payment) {
            $this->redirect('admin/pagamentos', ['error' => 'Pedido não encontrado.']);
        }

        $gateway = Gateways::obter((string) ($payment['gateway'] ?? 'picpay'));

        if (!$gateway) {
            $this->redirect('admin/pagamentos', ['error' => 'Meio de pagamento desta cobrança não está mais disponível.']);
        }

        $consulta = $gateway->consultarStatus(
            (string) $payment['reference_id'],
            $payment['gateway_payment_id'] ?: ($payment['authorization_id'] ?: null)
        );

        if (!$consulta['sucesso']) {
            $this->redirect('admin/pagamentos', ['error' => 'Não foi possível consultar o ' . $gateway->nome() . ': ' . $consulta['erro']]);
        }

        $status = (string) ($consulta['dados']['status'] ?? 'pending');
        $idExterno = $consulta['dados']['id_externo'] ?? null;

        Database::update('payments', [
            'status'             => $status,
            'gateway_payment_id' => $idExterno,
            'authorization_id'   => $idExterno,
            'last_checked_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $paymentId]);

        if (in_array($status, ['paid', 'completed'], true)) {
            $ativacao = LicenseService::activateFromPayment($paymentId, 'admin_released_license');
            $this->redirect('admin/pagamentos', ['success' => 'Status: ' . $status . '. ' . $ativacao['message']]);
        }

        $this->redirect('admin/pagamentos', ['info' => 'Status atualizado no ' . $gateway->nome() . ': ' . $status]);
    }

    /**
     * Libera a licença sem depender do PicPay — para pagamento recebido
     * por fora (transferência, boleto avulso) ou conciliação manual.
     */
    public function releasePayment(): void
    {
        $this->validateCsrf();

        $paymentId = (int) $this->input('payment_id', 0);
        $payment = Database::fetch("SELECT * FROM payments WHERE id = :id LIMIT 1", ['id' => $paymentId]);

        if (!$payment) {
            $this->redirect('admin/pagamentos', ['error' => 'Pedido não encontrado.']);
        }

        if (!in_array($payment['status'], ['paid', 'completed'], true)) {
            Database::update('payments', [
                'status'  => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $paymentId]);
        }

        $resultado = LicenseService::activateFromPayment($paymentId, 'admin_released_license');

        Auth::logAudit((int) Auth::id(), (string) $payment['reference_id'], 'admin_released_payment');

        $this->redirect('admin/pagamentos', [
            $resultado['success'] ? 'success' : 'error' => $resultado['message'],
        ]);
    }

    public function logs(): void
    {
        $action = $this->text('acao', '', 60);
        [$limit, $offset] = Database::paginate((int) $this->input('pagina', 1), 50);

        $where = '';
        $params = [];

        if ($action !== '') {
            $where = 'WHERE l.action = :action';
            $params['action'] = $action;
        }

        $logs = Database::fetchAll(
            "SELECT l.*, u.name as user_name
             FROM user_logs l
             LEFT JOIN users u ON u.id = l.user_id
             {$where}
             ORDER BY l.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $total = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM user_logs l {$where}",
            $params,
            0
        );

        $actions = Database::fetchAll(
            "SELECT DISTINCT action FROM user_logs ORDER BY action ASC"
        );

        $this->render('admin/logs', [
            'logs'     => $logs,
            'total'    => $total,
            'page'     => max(1, (int) $this->input('pagina', 1)),
            'pages'    => (int) ceil($total / $limit),
            'actions'  => $actions,
            'selected' => $action,
        ]);
    }

    /**
     * Painel de conciliação financeira: mostra todos os pedidos e permite
     * reconsultar o status diretamente no PicPay.
     */
    public function payments(): void
    {
        [$limit, $offset] = Database::paginate((int) $this->input('pagina', 1), 50);

        // Filtros. Ambos são conferidos contra listas fechadas: o que vier
        // fora delas é ignorado, e nada do que o usuário digita entra no SQL.
        $gatewayFiltro = $this->text('gateway', '', 30);
        if ($gatewayFiltro !== '' && !isset(\App\Services\Gateways\Gateways::todos()[$gatewayFiltro])) {
            $gatewayFiltro = '';
        }

        $situacao = $this->text('situacao', '', 20);
        $situacoes = [
            'pagos'     => "status IN ('paid','completed')",
            'pendentes' => "status NOT IN ('paid','completed','cancelled','expired','refused')",
            'problema'  => "status IN ('paid','completed') AND license_id IS NULL",
        ];
        if (!isset($situacoes[$situacao])) {
            $situacao = '';
        }

        $onde   = [];
        $params = [];

        if ($gatewayFiltro !== '') {
            $onde[] = 'p.gateway = :gateway';
            $params['gateway'] = $gatewayFiltro;
        }

        if ($situacao !== '') {
            $onde[] = str_replace('status', 'p.status', $situacoes[$situacao]);
        }

        $filtro = $onde ? ' WHERE ' . implode(' AND ', $onde) : '';

        $payments = Database::fetchAll(
            "SELECT p.*, pl.name as plan_name, u.name as user_name, u.email as user_email
             FROM payments p
             JOIN plans pl ON pl.id = p.plan_id
             JOIN users u ON u.id = p.user_id
             {$filtro}
             ORDER BY p.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $total = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM payments p {$filtro}",
            $params,
            0
        );

        $summary = Database::fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN amount ELSE 0 END), 0) as total_paid,
                SUM(CASE WHEN status IN ('paid','completed') THEN 1 ELSE 0 END) as count_paid,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as count_pending,
                SUM(CASE WHEN status IN ('paid','completed') AND license_id IS NULL THEN 1 ELSE 0 END) as count_unlinked,
                COALESCE(SUM(CASE WHEN status IN ('paid','completed')
                                   AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                              THEN amount ELSE 0 END), 0) as total_mes,
                COALESCE(SUM(CASE WHEN status NOT IN ('paid','completed','cancelled','expired','refused')
                              THEN amount ELSE 0 END), 0) as total_em_aberto
             FROM payments"
        ) ?? [];

        // Quanto entrou por meio de pagamento, para a comparação ficar visível
        $porGateway = Database::fetchAll(
            "SELECT gateway,
                    COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN amount ELSE 0 END), 0) as total,
                    SUM(CASE WHEN status IN ('paid','completed') THEN 1 ELSE 0 END) as pagos
               FROM payments
              GROUP BY gateway
              ORDER BY total DESC"
        );

        $pagos = (int) ($summary['count_paid'] ?? 0);
        $summary['ticket_medio'] = $pagos > 0 ? ((float) $summary['total_paid'] / $pagos) : 0.0;

        $nomes = [];
        foreach (\App\Services\Gateways\Gateways::todos() as $slug => $g) {
            $nomes[$slug] = $g->nome();
        }

        $this->render('admin/payments', [
            'payments'      => $payments,
            'total'         => $total,
            'page'          => max(1, (int) $this->input('pagina', 1)),
            'pages'         => (int) ceil($total / $limit),
            'summary'       => $summary,
            'porGateway'    => $porGateway,
            'nomesGateway'  => $nomes,
            'gatewayFiltro' => $gatewayFiltro,
            'situacao'      => $situacao,
        ]);
    }
}
