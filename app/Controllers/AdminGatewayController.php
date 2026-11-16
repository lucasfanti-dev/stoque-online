<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\PaymentGateway;
use App\Services\Gateways\Gateways;

/**
 * Meios de pagamento.
 *
 * Aqui o administrador liga e desliga cada gateway e guarda as credenciais.
 * Ligando um só, todo mundo paga por ele; ligando dois, o cliente escolhe no
 * checkout.
 */
class AdminGatewayController extends Controller
{
    public function index(): void
    {
        $gateways = [];

        foreach (Gateways::todos() as $slug => $gateway) {
            $config = PaymentGateway::obter($slug) ?? ['ativo' => 0, 'modo' => PaymentGateway::MODO_PRODUCAO];
            $credenciais = PaymentGateway::credenciais($slug);

            $campos = [];
            foreach ($gateway->camposDeCredencial() as $chave => $campo) {
                $campos[$chave] = $campo + [
                    'preenchido' => trim((string) ($credenciais[$chave] ?? '')) !== '',
                    'mascara'    => PaymentGateway::mascarar((string) ($credenciais[$chave] ?? '')),
                ];
            }

            $gateways[$slug] = [
                'slug'         => $slug,
                'nome'         => $gateway->nome(),
                'icone'        => $gateway->icone(),
                'formas'       => $gateway->formasDePagamento(),
                'ativo'        => (int) $config['ativo'] === 1,
                'modo'         => (string) $config['modo'],
                'configurado'  => $gateway->estaConfigurado(),
                'campos'       => $campos,
                'urlWebhook'   => url('pagamento/webhook/' . $slug),
            ];
        }

        $this->render('admin/gateways', [
            'gateways'    => $gateways,
            'disponiveis' => count(Gateways::disponiveis()),
            'porGateway'  => $this->totaisPorGateway(),
            'adminAtivo'  => 'gateways',
        ]);
    }

    public function save(): void
    {
        $this->validateCsrf();

        $slug = $this->text('slug', '', 30);
        $gateway = Gateways::obter($slug);

        if (!$gateway) {
            $this->redirect('admin/meios-pagamento', ['error' => 'Meio de pagamento desconhecido.']);
        }

        $credenciais = [];
        $limpar = [];

        foreach ($gateway->camposDeCredencial() as $chave => $campo) {
            $valor = trim((string) $this->input('cred_' . $chave, ''));

            // Campo em branco quer dizer "não mexi": o valor gravado continua.
            // Para apagar de propósito existe a caixinha de limpar.
            if ($this->input('limpar_' . $chave, '') !== '') {
                $limpar[] = $chave;
                continue;
            }

            if ($valor !== '') {
                $credenciais[$chave] = $valor;
            }
        }

        $ativo = $this->input('ativo', '') !== '';
        $modo  = $this->text('modo', PaymentGateway::MODO_PRODUCAO, 12);

        PaymentGateway::salvar($slug, $ativo, $modo, $credenciais, $limpar);
        Gateways::limparCache();

        $gateway = Gateways::obter($slug);
        $gateway->esquecerCredenciais();

        // Ligar sem credencial deixaria o cliente escolher e tomar erro na
        // tela seguinte; avisamos na hora em vez de deixar descobrir depois.
        if ($ativo && !$gateway->estaConfigurado()) {
            Auth::logAudit(Auth::id(), Auth::user()['email'] ?? null, 'gateway_saved_' . $slug);

            $this->redirect('admin/meios-pagamento', [
                'warning' => $gateway->nome() . ' foi salvo como ativo, mas falta credencial obrigatória — '
                    . 'ele não vai aparecer no checkout até você preencher.',
            ]);
        }

        Auth::logAudit(Auth::id(), Auth::user()['email'] ?? null, 'gateway_saved_' . $slug);

        $this->redirect('admin/meios-pagamento', [
            'success' => $gateway->nome() . ($ativo ? ' está ativo e pronto para receber.' : ' foi desativado.'),
        ]);
    }

    /**
     * Quanto entrou por cada meio de pagamento, para a tela mostrar o peso de
     * cada um em vez de só ligar e desligar às cegas.
     *
     * @return array<string,array{pagos:int, total:float, pendentes:int}>
     */
    private function totaisPorGateway(): array
    {
        try {
            $linhas = Database::fetchAll(
                "SELECT gateway,
                        SUM(CASE WHEN status IN ('paid','completed') THEN 1 ELSE 0 END)      AS pagos,
                        SUM(CASE WHEN status IN ('paid','completed') THEN amount ELSE 0 END) AS total,
                        SUM(CASE WHEN status NOT IN ('paid','completed','cancelled','expired','refused') THEN 1 ELSE 0 END) AS pendentes
                   FROM payments
                  GROUP BY gateway"
            );
        } catch (\Throwable) {
            return [];
        }

        $saida = [];
        foreach ($linhas as $linha) {
            $saida[(string) $linha['gateway']] = [
                'pagos'     => (int) $linha['pagos'],
                'total'     => (float) $linha['total'],
                'pendentes' => (int) $linha['pendentes'],
            ];
        }

        return $saida;
    }
}
