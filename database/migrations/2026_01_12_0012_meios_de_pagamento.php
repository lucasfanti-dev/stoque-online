<?php

/**
 * Meios de pagamento configuráveis pelo administrador.
 *
 * Até aqui o PicPay era o único, com as credenciais no .env — trocar um token
 * exigia subir arquivo. Agora cada meio tem uma linha própria, ligada ou
 * desligada pela tela do admin, e a tabela `payments` passa a registrar por
 * onde cada cobrança saiu.
 *
 * As credenciais que já estiverem no .env são copiadas para cá, para a
 * cobrança não parar no dia da atualização. O .env continua servindo de
 * reserva no código, então nada quebra se esta migração rodar antes de o
 * administrador abrir a tela.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Meios de pagamento (PicPay e Mercado Pago) configuráveis pelo admin',

    'executar' => function (PDO $pdo, Esquema $e): array {
        $passos = [];

        $passos[] = $e->tabela('payment_gateways', "
            `slug` VARCHAR(30) NOT NULL PRIMARY KEY,
            `ativo` TINYINT(1) NOT NULL DEFAULT 0,
            `modo` VARCHAR(12) NOT NULL DEFAULT 'producao' COMMENT 'producao ou sandbox',
            `credenciais` TEXT NULL COMMENT 'JSON com tokens do gateway',
            `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");

        // Por onde saiu a cobrança. As já existentes são todas do PicPay.
        $passos[] = $e->coluna(
            'payments',
            'gateway',
            "VARCHAR(30) NOT NULL DEFAULT 'picpay' COMMENT 'Meio de pagamento usado' AFTER `plan_id`"
        );

        // Identificador do pagamento no gateway. O PicPay trabalha só com a
        // nossa referência; o Mercado Pago tem id próprio e precisa dele.
        $passos[] = $e->coluna(
            'payments',
            'gateway_payment_id',
            "VARCHAR(120) NULL COMMENT 'Id do pagamento no gateway' AFTER `gateway`"
        );

        $passos[] = $e->coluna(
            'payments',
            'payment_method',
            "VARCHAR(30) NULL COMMENT 'pix, qrcode, cartao...' AFTER `gateway_payment_id`"
        );

        $passos[] = $e->indice('payments', 'idx_payments_gateway', '(`gateway`, `status`)');

        // --- Semeadura -----------------------------------------------------
        $tokenPicPay  = trim((string) env('PICPAY_TOKEN', ''));
        $sellerPicPay = trim((string) env('PICPAY_SELLER_TOKEN', ''));

        $credenciaisPicPay = [];
        if ($tokenPicPay !== '')  { $credenciaisPicPay['token'] = $tokenPicPay; }
        if ($sellerPicPay !== '') { $credenciaisPicPay['seller_token'] = $sellerPicPay; }

        $inserir = $pdo->prepare(
            'INSERT INTO `payment_gateways` (`slug`, `ativo`, `modo`, `credenciais`, `ordem`)
             VALUES (:slug, :ativo, :modo, :cred, :ordem)
             ON DUPLICATE KEY UPDATE `slug` = `slug`'
        );

        $inserir->execute([
            'slug'  => 'picpay',
            // Só liga sozinho se já havia credencial: ligar um meio sem token
            // faria o cliente escolher e tomar erro na tela seguinte.
            'ativo' => $credenciaisPicPay === [] ? 0 : 1,
            'modo'  => 'producao',
            'cred'  => $credenciaisPicPay === [] ? null : json_encode($credenciaisPicPay, JSON_UNESCAPED_SLASHES),
            'ordem' => 1,
        ]);
        $novos = $inserir->rowCount() > 0 ? 1 : 0;

        $inserir->execute([
            'slug'  => 'mercadopago',
            'ativo' => 0,
            'modo'  => 'producao',
            'cred'  => null,
            'ordem' => 2,
        ]);
        $novos += $inserir->rowCount() > 0 ? 1 : 0;

        if ($novos > 0) {
            $passos[] = "Meios de pagamento cadastrados ({$novos})";
        }

        if ($credenciaisPicPay !== []) {
            $passos[] = 'Credenciais do PicPay copiadas do .env para o banco';
        }

        return array_values(array_filter($passos));
    },
];
