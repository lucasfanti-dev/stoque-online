<?php

namespace App\Services\Gateways;

use App\Models\PaymentGateway;

/**
 * O que PicPay e Mercado Pago fazem igual: ler credencial, falar HTTP e
 * registrar falha em log. Cada gateway cuida só do que é próprio dele.
 */
abstract class GatewayBase implements Gateway
{
    /** Situações que o sistema entende, para onde o status de cada gateway é traduzido. */
    public const PAGO       = 'paid';
    public const PENDENTE   = 'pending';
    public const EXPIRADO   = 'expired';
    public const CANCELADO  = 'cancelled';
    public const RECUSADO   = 'refused';
    public const ESTORNADO  = 'refunded';
    public const ANALISE    = 'analysis';

    /** @var array<string,string>|null */
    private ?array $credenciais = null;

    public function icone(): string
    {
        return 'fa-solid fa-credit-card';
    }

    /**
     * Credencial guardada no banco, preenchida pela tela do admin.
     */
    protected function credencial(string $chave): string
    {
        if ($this->credenciais === null) {
            $this->credenciais = PaymentGateway::credenciais($this->slug());
        }

        return trim((string) ($this->credenciais[$chave] ?? ''));
    }

    public function modo(): string
    {
        return PaymentGateway::modo($this->slug());
    }

    public function ehSandbox(): bool
    {
        return $this->modo() === PaymentGateway::MODO_SANDBOX;
    }

    public function estaConfigurado(): bool
    {
        foreach ($this->camposDeCredencial() as $chave => $campo) {
            if (($campo['obrigatorio'] ?? true) && $this->credencial($chave) === '') {
                return false;
            }
        }

        return true;
    }

    /** Descarta o que foi lido do banco — usado depois de salvar credencial nova. */
    public function esquecerCredenciais(): void
    {
        $this->credenciais = null;
    }

    /**
     * Chamada HTTP com as defesas que pagamento exige: certificado sempre
     * conferido, tempo limitado e corpo de erro aproveitado na mensagem.
     *
     * @return array{sucesso:bool, http:int, dados:?array, erro:?string}
     */
    protected function http(string $metodo, string $url, array $cabecalhos = [], ?array $corpo = null): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Sem validar o certificado, a comunicação financeira fica aberta
            // a interceptação. Nunca desligue isto para "resolver" erro de SSL.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json', 'Accept: application/json'],
                $cabecalhos
            ),
        ]);

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
        }

        $resposta = curl_exec($ch);
        $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            $this->log("Falha de comunicação com {$url}: {$erroCurl}");
            return [
                'sucesso' => false,
                'http'    => 0,
                'dados'   => null,
                'erro'    => 'Não foi possível falar com o ' . $this->nome() . '. Tente novamente em instantes.',
            ];
        }

        $dados = json_decode((string) $resposta, true);
        $dados = is_array($dados) ? $dados : [];

        if ($http < 200 || $http >= 300) {
            $this->log("{$this->nome()} respondeu HTTP {$http} em {$url}: " . substr((string) $resposta, 0, 500));

            return [
                'sucesso' => false,
                'http'    => $http,
                'dados'   => $dados,
                'erro'    => $this->mensagemDeErro($dados, $http),
            ];
        }

        return ['sucesso' => true, 'http' => $http, 'dados' => $dados, 'erro' => null];
    }

    /**
     * Cada gateway devolve o erro num campo diferente; aqui fica o palpite
     * genérico e cada classe sobrescreve se souber melhor.
     */
    protected function mensagemDeErro(array $dados, int $http): string
    {
        foreach (['message', 'error', 'detail'] as $campo) {
            if (!empty($dados[$campo]) && is_string($dados[$campo])) {
                return $dados[$campo];
            }
        }

        return 'O ' . $this->nome() . ' recusou a operação (HTTP ' . $http . ').';
    }

    protected function ok(array $dados): array
    {
        return ['sucesso' => true, 'dados' => $dados, 'erro' => null];
    }

    protected function falha(string $mensagem): array
    {
        return ['sucesso' => false, 'dados' => null, 'erro' => $mensagem];
    }

    protected function log(string $mensagem): void
    {
        $dir = base_path('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $dir . '/pagamentos.log',
            '[' . date('Y-m-d H:i:s') . '] [' . $this->slug() . '] ' . $mensagem . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Cabeçalho HTTP vindo de $_SERVER.
     *
     * O PHP entrega 'X-Signature' como 'HTTP_X_SIGNATURE'. Sem tirar esse
     * prefixo, a assinatura nunca era encontrada e TODA notificação era
     * recusada — o pagamento caía e a licença só liberava na conferência
     * manual. Aceita as duas formas para o teste poder passar o nome cru.
     */
    protected function cabecalho(array $cabecalhos, string $nome): string
    {
        $alvo = strtolower(str_replace('_', '-', $nome));

        foreach ($cabecalhos as $chave => $valor) {
            $atual = strtolower(str_replace('_', '-', (string) $chave));

            if (str_starts_with($atual, 'http-')) {
                $atual = substr($atual, 5);
            }

            if ($atual === $alvo) {
                return (string) $valor;
            }
        }

        return '';
    }
}
