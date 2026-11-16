<?php

namespace App\Services\Gateways;

/**
 * Contrato de um meio de pagamento.
 *
 * O checkout não conhece PicPay nem Mercado Pago: conhece este contrato. Isso
 * é o que permite ligar e desligar meio de pagamento pela tela do admin, e o
 * que fará um terceiro gateway ser uma classe nova, sem tocar no resto.
 *
 * Todo retorno segue a mesma forma para o chamador não precisar saber de quem
 * veio: ['sucesso' => bool, 'dados' => ?array, 'erro' => ?string].
 */
interface Gateway
{
    /** Identificador curto, usado no banco e nas URLs. Ex.: 'picpay'. */
    public function slug(): string;

    /** Nome como aparece para o cliente. Ex.: 'Mercado Pago'. */
    public function nome(): string;

    /** Classe do ícone do Font Awesome exibido no botão de escolha. */
    public function icone(): string;

    /** Como a pessoa vai pagar, em uma linha. Ex.: 'PIX, cartão ou boleto'. */
    public function formasDePagamento(): string;

    /**
     * Quais credenciais este gateway precisa, para a tela do admin montar o
     * formulário sozinha.
     *
     * @return array<string, array{rotulo:string, dica:string, segredo:bool}>
     */
    public function camposDeCredencial(): array;

    /** Falta alguma credencial obrigatória? */
    public function estaConfigurado(): bool;

    /**
     * Cria a cobrança.
     *
     * @param array{nome:string, sobrenome:string, documento:string, email:string, telefone:string} $comprador
     * @return array{sucesso:bool, dados:?array, erro:?string}
     *         dados: id_externo, url_pagamento, qrcode_imagem, qrcode_texto,
     *                status, forma_pagamento
     */
    public function criarCobranca(
        array $comprador,
        float $valor,
        string $referencia,
        string $descricao,
        \DateTimeInterface $expiraEm
    ): array;

    /**
     * Situação atual da cobrança no gateway.
     *
     * @return array{sucesso:bool, dados:?array, erro:?string}
     *         dados: status (já traduzido), id_externo
     */
    public function consultarStatus(string $referencia, ?string $idExterno): array;

    /**
     * @return array{sucesso:bool, dados:?array, erro:?string}
     */
    public function cancelar(string $referencia, ?string $idExterno): array;

    /**
     * Interpreta a notificação recebida do gateway.
     *
     * Devolve a referência do pedido para o controlador achar a cobrança no
     * banco e conferir o status na fonte — nunca confiamos no status que veio
     * dentro do webhook, porque o corpo pode ser forjado.
     *
     * @param array  $corpo      Corpo já decodificado
     * @param array  $cabecalhos Cabeçalhos HTTP da requisição
     * @param string $bruto      Corpo cru, necessário para conferir assinatura
     * @return array{autorizado:bool, referencia:?string, id_externo:?string, erro:?string}
     */
    public function lerNotificacao(array $corpo, array $cabecalhos, string $bruto): array;
}
