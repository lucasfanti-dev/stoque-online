<?php

namespace App\Services\Gateways;

/**
 * PicPay — cobrança por QR Code.
 *
 * As credenciais passaram a vir do banco (tela do admin). O .env continua
 * valendo como reserva, para a instalação que ainda não migrou não parar de
 * cobrar no dia da atualização.
 */
class PicPayGateway extends GatewayBase
{
    private const API = 'https://appws.picpay.com/ecommerce/public/payments';

    public function slug(): string
    {
        return 'picpay';
    }

    public function nome(): string
    {
        return 'PicPay';
    }

    public function icone(): string
    {
        return 'fa-solid fa-qrcode';
    }

    public function formasDePagamento(): string
    {
        return 'QR Code no aplicativo PicPay';
    }

    public function camposDeCredencial(): array
    {
        return [
            'token' => [
                'rotulo'      => 'x-picpay-token',
                'dica'        => 'Painel do PicPay E-commerce → Credenciais. É o token que autoriza criar cobrança.',
                'segredo'     => true,
                'obrigatorio' => true,
            ],
            'seller_token' => [
                'rotulo'      => 'x-seller-token',
                'dica'        => 'No mesmo lugar. Sem ele o webhook fica fechado e a licença não libera sozinha.',
                'segredo'     => true,
                'obrigatorio' => true,
            ],
        ];
    }

    /** Banco primeiro; .env como reserva da instalação antiga. */
    protected function credencial(string $chave): string
    {
        $valor = parent::credencial($chave);
        if ($valor !== '') {
            return $valor;
        }

        return match ($chave) {
            'token'        => trim((string) env('PICPAY_TOKEN', '')),
            'seller_token' => trim((string) env('PICPAY_SELLER_TOKEN', '')),
            default        => '',
        };
    }

    public function criarCobranca(
        array $comprador,
        float $valor,
        string $referencia,
        string $descricao,
        \DateTimeInterface $expiraEm
    ): array {
        if (!$this->estaConfigurado()) {
            return $this->falha('O PicPay não está configurado.');
        }

        $documento = preg_replace('/\D+/', '', (string) ($comprador['documento'] ?? '')) ?? '';

        $resposta = $this->http('POST', self::API, $this->cabecalhosApi(), [
            'referenceId' => $referencia,
            'callbackUrl' => url('pagamento/webhook/picpay'),
            'returnUrl'   => url('pagamento/sucesso'),
            'value'       => round($valor, 2),
            'expiresAt'   => $expiraEm->format(DATE_ATOM),
            'buyer'       => [
                'firstName' => $comprador['nome'] ?? 'Cliente',
                'lastName'  => $comprador['sobrenome'] ?? 'StoqueOnline',
                'document'  => $documento,
                'email'     => $comprador['email'] ?? '',
                'phone'     => preg_replace('/\D+/', '', (string) ($comprador['telefone'] ?? '')),
            ],
        ]);

        if (!$resposta['sucesso']) {
            return $this->falha((string) $resposta['erro']);
        }

        $d = $resposta['dados'];

        return $this->ok([
            'id_externo'      => null, // o PicPay identifica pela própria referência
            'url_pagamento'   => $d['paymentUrl'] ?? null,
            'qrcode_imagem'   => $d['qrcode']['base64'] ?? null,
            'qrcode_texto'    => $d['qrcode']['content'] ?? null,
            'status'          => self::PENDENTE,
            'forma_pagamento' => 'qrcode',
        ]);
    }

    public function consultarStatus(string $referencia, ?string $idExterno): array
    {
        if (!$this->estaConfigurado()) {
            return $this->falha('O PicPay não está configurado.');
        }

        $resposta = $this->http(
            'GET',
            self::API . '/' . rawurlencode($referencia) . '/status',
            $this->cabecalhosApi()
        );

        if (!$resposta['sucesso']) {
            return $this->falha((string) $resposta['erro']);
        }

        return $this->ok([
            'status'     => $this->traduzir((string) ($resposta['dados']['status'] ?? 'created')),
            'id_externo' => $resposta['dados']['authorizationId'] ?? null,
        ]);
    }

    public function cancelar(string $referencia, ?string $idExterno): array
    {
        if (!$this->estaConfigurado()) {
            return $this->falha('O PicPay não está configurado.');
        }

        $resposta = $this->http(
            'POST',
            self::API . '/' . rawurlencode($referencia) . '/cancellations',
            $this->cabecalhosApi(),
            $idExterno ? ['authorizationId' => $idExterno] : []
        );

        return $resposta['sucesso']
            ? $this->ok(['status' => self::CANCELADO])
            : $this->falha((string) $resposta['erro']);
    }

    public function lerNotificacao(array $corpo, array $cabecalhos, string $bruto): array
    {
        $esperado = $this->credencial('seller_token');

        // Sem seller token o webhook fica fechado: aceitar chamada anônima
        // seria deixar qualquer um disparar liberação de licença.
        if ($esperado === '') {
            return ['autorizado' => false, 'referencia' => null, 'id_externo' => null,
                    'erro' => 'Webhook do PicPay sem seller token configurado.'];
        }

        $recebido = $this->cabecalho($cabecalhos, 'x-seller-token');

        if (!hash_equals($esperado, $recebido)) {
            return ['autorizado' => false, 'referencia' => null, 'id_externo' => null,
                    'erro' => 'Assinatura do webhook não confere.'];
        }

        $referencia = $corpo['referenceId'] ?? null;

        return [
            'autorizado' => true,
            'referencia' => is_string($referencia) ? $referencia : null,
            'id_externo' => $corpo['authorizationId'] ?? null,
            'erro'       => null,
        ];
    }

    /**
     * @return string[]
     */
    private function cabecalhosApi(): array
    {
        return ['x-picpay-token: ' . $this->credencial('token')];
    }

    private function traduzir(string $status): string
    {
        return match ($status) {
            'paid', 'completed' => self::PAGO,
            'expired'           => self::EXPIRADO,
            'analysis'          => self::ANALISE,
            'refunded'          => self::ESTORNADO,
            'chargeback'        => self::ESTORNADO,
            default             => self::PENDENTE,
        };
    }
}
