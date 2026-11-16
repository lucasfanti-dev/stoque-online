<?php

namespace App\Services\Gateways;

/**
 * Mercado Pago — PIX com QR Code.
 *
 * Usa a API de pagamentos (/v1/payments), que devolve o QR Code do PIX na
 * hora, sem levar o cliente para fora do site. É o caminho mais direto para
 * assinatura: a pessoa paga e cai na tela de sucesso.
 *
 * Duas coisas merecem atenção:
 *
 *  - A chave de idempotência. Sem ela, um clique duplo ou um tempo esgotado
 *    que fez o cliente tentar de novo geram DUAS cobranças. Mandamos sempre
 *    a mesma chave para a mesma referência de pedido.
 *
 *  - A assinatura do webhook. O Mercado Pago assina a notificação com um
 *    segredo próprio; conferimos antes de mexer em qualquer licença. Mesmo
 *    assim o status vem da consulta à API, nunca do corpo recebido.
 */
class MercadoPagoGateway extends GatewayBase
{
    private const API = 'https://api.mercadopago.com';

    public function slug(): string
    {
        return 'mercadopago';
    }

    public function nome(): string
    {
        return 'Mercado Pago';
    }

    public function icone(): string
    {
        return 'fa-solid fa-bolt';
    }

    public function formasDePagamento(): string
    {
        return 'PIX com QR Code, aprovação na hora';
    }

    public function camposDeCredencial(): array
    {
        return [
            'access_token' => [
                'rotulo'      => 'Access Token',
                'dica'        => 'Mercado Pago → Seu negócio → Configurações → Credenciais. Use as de teste enquanto estiver validando; começa com TEST- nesse caso.',
                'segredo'     => true,
                'obrigatorio' => true,
            ],
            'public_key' => [
                'rotulo'      => 'Public Key',
                'dica'        => 'No mesmo lugar. Não é segredo, mas fica junto para não se perder.',
                'segredo'     => false,
                'obrigatorio' => false,
            ],
            'webhook_secret' => [
                'rotulo'      => 'Assinatura secreta do webhook',
                'dica'        => 'Credenciais → Webhooks → "Assinatura secreta". Sem ela a notificação é recusada e a licença só libera na conferência manual.',
                'segredo'     => true,
                'obrigatorio' => false,
            ],
        ];
    }

    public function criarCobranca(
        array $comprador,
        float $valor,
        string $referencia,
        string $descricao,
        \DateTimeInterface $expiraEm
    ): array {
        if (!$this->estaConfigurado()) {
            return $this->falha('O Mercado Pago não está configurado.');
        }

        $documento = preg_replace('/\D+/', '', (string) ($comprador['documento'] ?? '')) ?? '';

        $pagador = [
            'email'      => $comprador['email'] ?? '',
            'first_name' => $comprador['nome'] ?? 'Cliente',
            'last_name'  => $comprador['sobrenome'] ?? 'StoqueOnline',
        ];

        if ($documento !== '') {
            $pagador['identification'] = [
                'type'   => strlen($documento) === 14 ? 'CNPJ' : 'CPF',
                'number' => $documento,
            ];
        }

        $resposta = $this->http('POST', self::API . '/v1/payments', $this->cabecalhosApi($referencia), [
            'transaction_amount' => round($valor, 2),
            'description'        => $descricao,
            'payment_method_id'  => 'pix',
            'external_reference' => $referencia,
            'notification_url'   => url('pagamento/webhook/mercadopago'),
            'date_of_expiration' => $this->dataMercadoPago($expiraEm),
            'payer'              => $pagador,
        ]);

        if (!$resposta['sucesso']) {
            return $this->falha((string) $resposta['erro']);
        }

        $d = $resposta['dados'];
        $pix = $d['point_of_interaction']['transaction_data'] ?? [];

        return $this->ok([
            'id_externo'      => isset($d['id']) ? (string) $d['id'] : null,
            'url_pagamento'   => $pix['ticket_url'] ?? null,
            'qrcode_imagem'   => $pix['qr_code_base64'] ?? null,
            'qrcode_texto'    => $pix['qr_code'] ?? null,
            'status'          => $this->traduzir((string) ($d['status'] ?? 'pending')),
            'forma_pagamento' => 'pix',
        ]);
    }

    public function consultarStatus(string $referencia, ?string $idExterno): array
    {
        if (!$this->estaConfigurado()) {
            return $this->falha('O Mercado Pago não está configurado.');
        }

        // Com o id do pagamento a consulta é direta; sem ele, procuramos pela
        // referência que gravamos na criação.
        if ($idExterno !== null && $idExterno !== '') {
            $resposta = $this->http('GET', self::API . '/v1/payments/' . rawurlencode($idExterno), $this->cabecalhosApi());

            if (!$resposta['sucesso']) {
                return $this->falha((string) $resposta['erro']);
            }

            return $this->ok([
                'status'     => $this->traduzir((string) ($resposta['dados']['status'] ?? 'pending')),
                'id_externo' => $idExterno,
            ]);
        }

        $busca = $this->http(
            'GET',
            self::API . '/v1/payments/search?external_reference=' . rawurlencode($referencia) . '&sort=date_created&criteria=desc',
            $this->cabecalhosApi()
        );

        if (!$busca['sucesso']) {
            return $this->falha((string) $busca['erro']);
        }

        $resultados = $busca['dados']['results'] ?? [];
        if (!is_array($resultados) || $resultados === []) {
            return $this->falha('Cobrança não encontrada no Mercado Pago.');
        }

        // A busca pode trazer tentativas antigas da mesma referência; a que
        // vale é uma aprovada, se existir, senão a mais recente.
        $escolhido = $resultados[0];
        foreach ($resultados as $r) {
            if (($r['status'] ?? '') === 'approved') {
                $escolhido = $r;
                break;
            }
        }

        return $this->ok([
            'status'     => $this->traduzir((string) ($escolhido['status'] ?? 'pending')),
            'id_externo' => isset($escolhido['id']) ? (string) $escolhido['id'] : null,
        ]);
    }

    public function cancelar(string $referencia, ?string $idExterno): array
    {
        if (!$this->estaConfigurado()) {
            return $this->falha('O Mercado Pago não está configurado.');
        }

        if ($idExterno === null || $idExterno === '') {
            $status = $this->consultarStatus($referencia, null);
            $idExterno = $status['dados']['id_externo'] ?? null;
        }

        if (!$idExterno) {
            return $this->falha('Não encontramos essa cobrança no Mercado Pago para cancelar.');
        }

        $resposta = $this->http(
            'PUT',
            self::API . '/v1/payments/' . rawurlencode((string) $idExterno),
            $this->cabecalhosApi(),
            ['status' => 'cancelled']
        );

        return $resposta['sucesso']
            ? $this->ok(['status' => self::CANCELADO])
            : $this->falha((string) $resposta['erro']);
    }

    public function lerNotificacao(array $corpo, array $cabecalhos, string $bruto): array
    {
        // O Mercado Pago avisa de vários assuntos; só pagamento nos interessa.
        $tipo = $corpo['type'] ?? ($_GET['type'] ?? '');
        if ($tipo !== '' && $tipo !== 'payment') {
            return ['autorizado' => true, 'referencia' => null, 'id_externo' => null, 'erro' => null];
        }

        $idPagamento = $corpo['data']['id'] ?? ($_GET['data_id'] ?? ($_GET['id'] ?? null));
        $idPagamento = $idPagamento !== null ? (string) $idPagamento : null;

        if ($idPagamento === null || $idPagamento === '') {
            return ['autorizado' => false, 'referencia' => null, 'id_externo' => null,
                    'erro' => 'Notificação sem identificador de pagamento.'];
        }

        $conferencia = $this->conferirAssinatura($cabecalhos, $idPagamento);
        if (!$conferencia['ok']) {
            return ['autorizado' => false, 'referencia' => null, 'id_externo' => null, 'erro' => $conferencia['erro']];
        }

        // A referência do pedido vem da consulta à API, não do corpo recebido:
        // o corpo pode ser forjado, a resposta autenticada da API não.
        $consulta = $this->http('GET', self::API . '/v1/payments/' . rawurlencode($idPagamento), $this->cabecalhosApi());

        if (!$consulta['sucesso']) {
            return ['autorizado' => false, 'referencia' => null, 'id_externo' => $idPagamento,
                    'erro' => 'Não foi possível confirmar o pagamento no Mercado Pago.'];
        }

        $referencia = $consulta['dados']['external_reference'] ?? null;

        return [
            'autorizado' => true,
            'referencia' => is_string($referencia) ? $referencia : null,
            'id_externo' => $idPagamento,
            'erro'       => null,
        ];
    }

    /**
     * Confere a assinatura x-signature.
     *
     * O Mercado Pago manda "ts=...,v1=..." e o v1 é o HMAC-SHA256 de
     * "id:{id};request-id:{x-request-id};ts:{ts};" com a assinatura secreta.
     *
     * @return array{ok:bool, erro:?string}
     */
    private function conferirAssinatura(array $cabecalhos, string $idPagamento): array
    {
        $segredo = $this->credencial('webhook_secret');

        // Sem segredo cadastrado não dá para conferir. Recusamos: liberar
        // licença a partir de uma chamada que qualquer um pode fazer seria
        // dar assinatura de graça a quem descobrisse a URL.
        if ($segredo === '') {
            return ['ok' => false, 'erro' => 'Assinatura secreta do webhook não configurada no painel.'];
        }

        $assinatura = $this->cabecalho($cabecalhos, 'x-signature');
        if ($assinatura === '') {
            return ['ok' => false, 'erro' => 'Notificação sem cabeçalho de assinatura.'];
        }

        $partes = [];
        foreach (explode(',', $assinatura) as $pedaco) {
            $par = explode('=', trim($pedaco), 2);
            if (count($par) === 2) {
                $partes[trim($par[0])] = trim($par[1]);
            }
        }

        $ts = $partes['ts'] ?? '';
        $v1 = $partes['v1'] ?? '';

        if ($ts === '' || $v1 === '') {
            return ['ok' => false, 'erro' => 'Cabeçalho de assinatura incompleto.'];
        }

        // Janela de 10 minutos: barra o reenvio de uma notificação antiga
        // capturada por terceiros.
        if (abs(time() - (int) $ts) > 600) {
            return ['ok' => false, 'erro' => 'Notificação fora da janela de tempo aceita.'];
        }

        $requestId = $this->cabecalho($cabecalhos, 'x-request-id');
        $base = 'id:' . $idPagamento . ';request-id:' . $requestId . ';ts:' . $ts . ';';
        $esperado = hash_hmac('sha256', $base, $segredo);

        if (!hash_equals($esperado, $v1)) {
            $this->log('Assinatura do webhook não confere para o pagamento ' . $idPagamento);
            return ['ok' => false, 'erro' => 'Assinatura do webhook não confere.'];
        }

        return ['ok' => true, 'erro' => null];
    }

    /**
     * @return string[]
     */
    private function cabecalhosApi(?string $idempotencia = null): array
    {
        $cabecalhos = ['Authorization: Bearer ' . $this->credencial('access_token')];

        if ($idempotencia !== null) {
            // Mesma referência, mesma chave: se a cobrança já foi criada, o
            // Mercado Pago devolve a existente em vez de criar outra.
            $cabecalhos[] = 'X-Idempotency-Key: ' . $idempotencia;
        }

        return $cabecalhos;
    }

    /** O Mercado Pago exige o fuso escrito com dois pontos e milissegundos. */
    private function dataMercadoPago(\DateTimeInterface $data): string
    {
        return $data->format('Y-m-d\TH:i:s.vP');
    }

    protected function mensagemDeErro(array $dados, int $http): string
    {
        // Erro de validação vem numa lista em "cause"; a mensagem de lá é a
        // que diz o que realmente está errado.
        if (!empty($dados['cause'][0]['description'])) {
            return (string) $dados['cause'][0]['description'];
        }

        if (!empty($dados['message']) && is_string($dados['message'])) {
            return (string) $dados['message'];
        }

        return parent::mensagemDeErro($dados, $http);
    }

    private function traduzir(string $status): string
    {
        return match ($status) {
            'approved', 'authorized'   => self::PAGO,
            'in_process', 'in_mediation' => self::ANALISE,
            'rejected'                 => self::RECUSADO,
            'cancelled'                => self::CANCELADO,
            'refunded', 'charged_back' => self::ESTORNADO,
            default                    => self::PENDENTE,
        };
    }
}
