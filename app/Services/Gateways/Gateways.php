<?php

namespace App\Services\Gateways;

use App\Models\PaymentGateway;

/**
 * Lista de meios de pagamento que o sistema conhece.
 *
 * Acrescentar um terceiro (PagBank, Asaas, Stripe...) é escrever a classe,
 * implementar o contrato e incluir aqui — nada mais no sistema muda.
 */
class Gateways
{
    /** @var class-string<Gateway>[] */
    private const CLASSES = [
        PicPayGateway::class,
        MercadoPagoGateway::class,
    ];

    /** @var array<string,Gateway>|null */
    private static ?array $instancias = null;

    /**
     * Todos os que o sistema sabe usar, ligados ou não.
     *
     * @return array<string,Gateway>
     */
    public static function todos(): array
    {
        if (self::$instancias !== null) {
            return self::$instancias;
        }

        $lista = [];
        foreach (self::CLASSES as $classe) {
            /** @var Gateway $g */
            $g = new $classe();
            $lista[$g->slug()] = $g;
        }

        return self::$instancias = $lista;
    }

    public static function obter(?string $slug): ?Gateway
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return self::todos()[$slug] ?? null;
    }

    /**
     * Os que o administrador ligou E que têm credencial preenchida.
     *
     * A segunda condição importa: um gateway ligado sem token só serviria
     * para o cliente escolher e tomar erro na tela seguinte.
     *
     * @return array<string,Gateway>
     */
    public static function disponiveis(): array
    {
        $saida = [];

        foreach (self::todos() as $slug => $gateway) {
            if (PaymentGateway::estaAtivo($slug) && $gateway->estaConfigurado()) {
                $saida[$slug] = $gateway;
            }
        }

        return $saida;
    }

    public static function temAlgumDisponivel(): bool
    {
        return self::disponiveis() !== [];
    }

    /**
     * Quando só existe um disponível, o cliente não precisa escolher nada.
     */
    public static function unico(): ?Gateway
    {
        $disponiveis = self::disponiveis();

        return count($disponiveis) === 1 ? reset($disponiveis) : null;
    }

    /**
     * Valida a escolha que veio do formulário. Devolve null se o meio não
     * existir ou não estiver disponível — nunca confiamos no que foi enviado.
     */
    public static function escolhido(?string $slug): ?Gateway
    {
        $disponiveis = self::disponiveis();

        if ($slug !== null && isset($disponiveis[$slug])) {
            return $disponiveis[$slug];
        }

        return self::unico();
    }

    public static function limparCache(): void
    {
        self::$instancias = null;
        PaymentGateway::limparCache();
    }
}
