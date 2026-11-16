<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Product;
use App\Models\License;
use App\Models\User;
use App\Services\Gateways\Gateways;
use App\Services\MailService;
use App\Services\LicenseService;

class PaymentController extends Controller
{
    /** Status do PicPay que representam pagamento efetivado */
    private const PAID_STATUSES = ['paid', 'completed'];

    public function plans(): void
    {
        $user = Auth::user();
        $canHaveFreeTrial = $user && !empty($user['first_access_free']);

        $plans = Product::getAllActive($canHaveFreeTrial);

        $this->render('payment/plans', [
            'plans'            => $plans,
            'canHaveFreeTrial' => $canHaveFreeTrial,
        ]);
    }

    public function claimFreeTrial(): void
    {
        $this->validateCsrf();
        $user = Auth::user();

        $freePlan = Database::fetch("SELECT * FROM plans WHERE is_free_trial = 1 AND status = 1 LIMIT 1");
        if (!$freePlan) {
            $this->redirect('planos', ['error' => 'Plano de teste não está disponível no momento.']);
        }

        // Consumo atômico do direito ao teste: mesmo com dois cliques
        // simultâneos apenas uma requisição consegue alterar a linha.
        $claimed = Database::update(
            'users',
            ['first_access_free' => 0],
            'id = :id AND first_access_free = 1',
            ['id' => (int) $user['id']]
        );

        if ($claimed === 0) {
            $this->redirect('planos', ['error' => 'O plano de teste gratuito já foi utilizado nesta conta.']);
        }

        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . (int) $freePlan['duration_days'] . ' days'));

        License::create([
            'user_id'    => (int) $user['id'],
            'plan_id'    => (int) $freePlan['id'],
            'price'      => 0.00,
            'max_users'  => (int) $freePlan['max_users'],
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 1,
            'is_closed'  => 0,
            'origin'     => 'trial',
        ]);

        Auth::logAudit((int) $user['id'], $user['email'], 'free_trial_activated');
        Auth::flushCache();

        $this->redirect('estoque/upload', [
            'success' => 'Parabéns! Seu teste gratuito de ' . (int) $freePlan['duration_days']
                . ' dias foi ativado. Agora você já pode carregar seu estoque.',
        ]);
    }

    public function checkout(string $id): void
    {
        $plan = Product::findById((int) $id);

        if (!$plan) {
            $this->redirect('planos', ['error' => 'Plano não encontrado ou indisponível.']);
        }

        if (!empty($plan['is_free_trial'])) {
            $this->redirect('planos', ['info' => 'O teste gratuito é ativado direto na página de planos.']);
        }

        $disponiveis = Gateways::disponiveis();

        if ($disponiveis === []) {
            $this->redirect('planos', [
                'error' => 'O pagamento online está temporariamente indisponível. '
                    . 'Fale conosco pelo WhatsApp para concluir a contratação.',
            ]);
        }

        $this->render('payment/checkout', [
            'plan'        => $plan,
            'user'        => Auth::user(),
            // Com um só, o cliente nem vê a escolha; com dois ou mais, escolhe.
            'gateways'    => $disponiveis,
            'gatewayUnico' => count($disponiveis) === 1 ? array_key_first($disponiveis) : null,
        ]);
    }

    public function processCheckout(): void
    {
        $this->validateCsrf();
        $user = Auth::user();

        $planId = (int) $this->input('plan_id', 0);
        $plan = Product::findById($planId);

        if (!$plan || !empty($plan['is_free_trial'])) {
            $this->redirect('planos', ['error' => 'Plano inválido.']);
        }

        // A escolha vem do formulário, mas é conferida contra os que estão
        // realmente ligados: ninguém cobra por um meio desligado mexendo no HTML.
        $gateway = Gateways::escolhido($this->text('gateway', '', 30));

        if (!$gateway) {
            $this->redirect('planos', [
                'error' => 'O pagamento online está temporariamente indisponível. '
                    . 'Fale conosco pelo WhatsApp para concluir a contratação.',
            ]);
        }

        $document = preg_replace('/[^0-9]/', '', $this->text('document', '', 20));
        if (strlen((string) $document) !== 11 && strlen((string) $document) !== 14) {
            $this->redirect('checkout/' . $planId, ['error' => 'Informe um CPF ou CNPJ válido para emissão da cobrança.']);
        }

        $comprador = [
            'nome'      => $this->text('first_name', '', 60) ?: (string) $user['name'],
            'sobrenome' => $this->text('last_name', '', 60) ?: 'Cliente',
            'documento' => $document,
            'email'     => $this->text('email', '', 255) ?: (string) $user['email'],
            'telefone'  => $this->text('phone', '', 30) ?: (string) ($user['phone'] ?? ''),
        ];

        $referenceId = 'SO' . $user['id'] . 'P' . $plan['id'] . 'T' . time();
        $expiraEm = new \DateTimeImmutable('+3 days');

        $resultado = $gateway->criarCobranca(
            $comprador,
            (float) $plan['price'],
            $referenceId,
            'StoqueOnline - ' . (string) $plan['name'],
            $expiraEm
        );

        // Falha na cobrança: nada é gravado e o cliente recebe o motivo real
        if (!$resultado['sucesso']) {
            Auth::logAudit((int) $user['id'], $user['email'], 'payment_creation_failed_' . $gateway->slug());

            $this->redirect('checkout/' . $planId, [
                'error' => 'Não foi possível gerar a cobrança: ' . $resultado['erro']
                    . ' Nenhum valor foi cobrado. Tente novamente em alguns instantes.',
            ]);
        }

        $dados = $resultado['dados'] ?? [];
        $paymentUrl    = $dados['url_pagamento'] ?? null;
        $qrcodeBase64  = $dados['qrcode_imagem'] ?? null;
        $qrcodeContent = $dados['qrcode_texto'] ?? null;

        if (!$paymentUrl && !$qrcodeContent) {
            Auth::logAudit((int) $user['id'], $user['email'], 'payment_missing_url');

            $this->redirect('checkout/' . $planId, [
                'error' => 'O ' . $gateway->nome() . ' não retornou a forma de pagamento. '
                    . 'Nenhum valor foi cobrado. Tente novamente.',
            ]);
        }

        Database::insert('payments', [
            'user_id'            => (int) $user['id'],
            'plan_id'            => (int) $plan['id'],
            'gateway'            => $gateway->slug(),
            'gateway_payment_id' => $dados['id_externo'] ?? null,
            'payment_method'     => $dados['forma_pagamento'] ?? null,
            'reference_id'       => $referenceId,
            'amount'             => $plan['price'],
            'payment_url'        => $paymentUrl,
            'qrcode_image'       => $qrcodeBase64,
            'qrcode_text'        => $qrcodeContent,
            'status'             => $dados['status'] ?? 'pending',
            'expires_at'         => $expiraEm->format('Y-m-d H:i:s'),
        ]);

        Auth::logAudit((int) $user['id'], $user['email'], 'payment_created_' . $gateway->slug());

        $this->render('payment/pay', [
            'plan'          => $plan,
            'referenceId'   => $referenceId,
            'paymentUrl'    => $paymentUrl,
            'qrcodeBase64'  => $qrcodeBase64,
            'qrcodeContent' => $qrcodeContent,
            'gateway'       => $gateway,
        ]);
    }

    /**
     * Notificação do gateway.
     *
     * O slug vem na URL, e cada gateway confere a própria assinatura. Mesmo
     * com a assinatura válida, o status NUNCA vem do corpo recebido: ele é
     * consultado na API do provedor. Corpo se forja; resposta autenticada não.
     */
    public function webhook(string $slug = 'picpay'): void
    {
        $gateway = Gateways::obter($slug);

        if (!$gateway) {
            $this->json(['success' => false, 'message' => 'Meio de pagamento desconhecido.'], 404);
        }

        $bruto = (string) file_get_contents('php://input');
        $corpo = json_decode($bruto, true);
        $corpo = is_array($corpo) ? $corpo : [];

        $leitura = $gateway->lerNotificacao($corpo, $_SERVER, $bruto);

        if (!$leitura['autorizado']) {
            error_log('[StoqueOnline] Webhook recusado (' . $slug . '): ' . (string) ($leitura['erro'] ?? ''));
            $this->json(['success' => false, 'message' => 'Não autorizado'], 401);
        }

        $referencia = $leitura['referencia'] ?? null;

        // Notificação de assunto que não nos interessa: respondemos 200 para
        // o provedor parar de reenviar.
        if (!$referencia) {
            $this->json(['success' => true, 'message' => 'Ignorado']);
        }

        $payment = Database::fetch(
            "SELECT * FROM payments WHERE reference_id = :ref LIMIT 1",
            ['ref' => $referencia]
        );

        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Pedido não encontrado'], 404);
        }

        if (!empty($leitura['id_externo']) && empty($payment['gateway_payment_id'])) {
            Database::update(
                'payments',
                ['gateway_payment_id' => (string) $leitura['id_externo']],
                'id = :id',
                ['id' => (int) $payment['id']]
            );
            $payment['gateway_payment_id'] = (string) $leitura['id_externo'];
        }

        $this->syncPaymentStatus($payment);

        $this->json(['success' => true]);
    }

    /**
     * Página de retorno do PicPay após o pagamento.
     */
    public function success(): void
    {
        $user = Auth::user();

        $payment = null;
        if ($user) {
            $payment = Database::fetch(
                "SELECT p.*, pl.name as plan_name FROM payments p
                 JOIN plans pl ON pl.id = p.plan_id
                 WHERE p.user_id = :user_id
                 ORDER BY p.id DESC LIMIT 1",
                ['user_id' => (int) $user['id']]
            );

            // Tenta conciliar na hora: o callback pode ainda não ter chegado
            if ($payment && !in_array($payment['status'], self::PAID_STATUSES, true)) {
                $this->syncPaymentStatus($payment);
                $payment = Database::fetch(
                    "SELECT p.*, pl.name as plan_name FROM payments p
                     JOIN plans pl ON pl.id = p.plan_id
                     WHERE p.id = :id LIMIT 1",
                    ['id' => (int) $payment['id']]
                );
            }
        }

        Auth::flushCache();

        $this->render('payment/success', [
            'payment'    => $payment,
            'hasLicense' => Auth::hasActiveLicense(),
        ]);
    }

    public function orders(): void
    {
        $userId = Auth::id();

        $orders = Database::fetchAll(
            "SELECT p.*, pl.name as plan_name
             FROM payments p
             JOIN plans pl ON pl.id = p.plan_id
             WHERE p.user_id = :user_id
             ORDER BY p.id DESC",
            ['user_id' => $userId]
        );

        $this->render('payment/orders', ['orders' => $orders]);
    }

    /**
     * Conciliação manual: o cliente confere no PicPay se o pagamento caiu,
     * sem depender do webhook.
     */
    public function checkOrder(): void
    {
        $this->validateCsrf();

        $paymentId = (int) $this->input('payment_id', 0);
        $userId = Auth::id();

        $payment = Database::fetch(
            "SELECT * FROM payments WHERE id = :id AND user_id = :user_id LIMIT 1",
            ['id' => $paymentId, 'user_id' => $userId]
        );

        if (!$payment) {
            $this->redirect('pedidos', ['error' => 'Pedido não encontrado.']);
        }

        $status = $this->syncPaymentStatus($payment);

        if ($status === null) {
            $this->redirect('pedidos', [
                'warning' => 'Não conseguimos consultar o PicPay agora. Tente novamente em alguns minutos.',
            ]);
        }

        if (in_array($status, self::PAID_STATUSES, true)) {
            $this->redirect('pedidos', ['success' => 'Pagamento confirmado! Sua licença já está ativa.']);
        }

        $labels = [
            'created'   => 'aguardando pagamento',
            'expired'   => 'expirado',
            'analysis'  => 'em análise pelo PicPay',
            'chargeback' => 'com chargeback',
            'refunded'  => 'estornado',
        ];

        $this->redirect('pedidos', [
            'info' => 'Status atual do pedido: ' . ($labels[$status] ?? $status) . '.',
        ]);
    }

    /**
     * Cancelamento de uma cobrança ainda não paga (recurso que existia na v1).
     */
    public function cancelOrder(): void
    {
        $this->validateCsrf();

        $paymentId = (int) $this->input('payment_id', 0);
        $userId = Auth::id();

        $payment = Database::fetch(
            "SELECT * FROM payments WHERE id = :id AND user_id = :user_id LIMIT 1",
            ['id' => $paymentId, 'user_id' => $userId]
        );

        if (!$payment) {
            $this->redirect('pedidos', ['error' => 'Pedido não encontrado.']);
        }

        if (in_array($payment['status'], self::PAID_STATUSES, true)) {
            $this->redirect('pedidos', [
                'warning' => 'Este pedido já foi pago. Para solicitar estorno, fale com o suporte.',
            ]);
        }

        $gateway = Gateways::obter((string) ($payment['gateway'] ?? 'picpay'));

        if (!$gateway) {
            $this->redirect('pedidos', ['error' => 'O meio de pagamento desta cobrança não está mais disponível. Fale com o suporte.']);
        }

        $result = $gateway->cancelar(
            (string) $payment['reference_id'],
            $payment['gateway_payment_id'] ?: ($payment['authorization_id'] ?: null)
        );

        if (!$result['sucesso']) {
            $this->redirect('pedidos', ['error' => 'Não foi possível cancelar: ' . $result['erro']]);
        }

        Database::update('payments', [
            'status'       => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $payment['id']]);

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'payment_cancelled');

        $this->redirect('pedidos', ['success' => 'Cobrança cancelada com sucesso.']);
    }

    /**
     * Consulta o status no PicPay, grava no banco e ativa a licença uma única
     * vez por pagamento. Devolve o status consultado ou null em caso de falha.
     */
    /**
     * Confere a situação real da cobrança no gateway e reflete no banco.
     *
     * Funciona para qualquer meio: quem sabe traduzir o status é a classe do
     * gateway, e o que chega aqui já vem no vocabulário do sistema.
     */
    private function syncPaymentStatus(array $payment): ?string
    {
        $gateway = Gateways::obter((string) ($payment['gateway'] ?? 'picpay'));

        if (!$gateway) {
            return null;
        }

        $resultado = $gateway->consultarStatus(
            (string) $payment['reference_id'],
            $payment['gateway_payment_id'] ?? null
        );

        if (!$resultado['sucesso']) {
            return null;
        }

        $status    = (string) ($resultado['dados']['status'] ?? 'pending');
        $idExterno = $resultado['dados']['id_externo'] ?? ($payment['gateway_payment_id'] ?? null);

        $campos = [
            'status'          => $status,
            'last_checked_at' => date('Y-m-d H:i:s'),
        ];

        if ($idExterno) {
            $campos['gateway_payment_id'] = (string) $idExterno;
            // Mantido por compatibilidade com o que a tela antiga já exibia
            $campos['authorization_id'] = (string) $idExterno;
        }

        Database::update('payments', $campos, 'id = :id', ['id' => (int) $payment['id']]);

        if (in_array($status, self::PAID_STATUSES, true)) {
            // A liberação é idempotente: o gateway notifica mais de uma vez e
            // a administração também pode liberar manualmente.
            LicenseService::activateFromPayment((int) $payment['id']);
        }

        return $status;
    }
}
