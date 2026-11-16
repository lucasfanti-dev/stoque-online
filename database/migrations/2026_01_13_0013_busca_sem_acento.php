<?php

/**
 * Código de busca sem acento.
 *
 * Até aqui a normalização só jogava fora o que não era letra ou número, e o
 * "Ç" de "AÇO" ia junto: a peça ficava indexada como "AO" e quem digitava
 * "aço" ou "aco" não a encontrava. Agora o acento vira a letra simples
 * ("ACO"), e esta migração refaz o código das peças que já estão no banco.
 *
 * As fotos são ligadas à peça pelo mesmo código, então mudam junto — sem
 * isso a foto de uma peça com acento sumiria da busca.
 *
 * Pode rodar quantas vezes for preciso: só mexe no que ainda está diferente.
 */

use App\Core\Esquema;

/*
 * A regra fica escrita aqui, e não chamada de Inventory::normalizeCode(): no
 * envio pelo FTP esta migração pode rodar antes de o Inventory.php novo
 * chegar, e com a regra antiga ela se daria por concluída sem mudar nada.
 * Precisa ser idêntica a Inventory::normalizeCode().
 */
$semAcento = static function (string $codigo): string {
    $mapa = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N', 'Ý' => 'Y',
    ];

    return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', strtr($codigo, $mapa)));
};

return [
    'descricao' => 'Código de busca das peças sem acento (aço = aco)',

    'executar' => function (PDO $pdo, Esquema $e) use ($semAcento): array {
        // Só as linhas com algum caractere fora do ASCII podem mudar
        $linhas = $pdo->query(
            "SELECT id, user_id, brand, product, product_search
               FROM inventory
              WHERE product REGEXP '[^ -~]'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $atualizarPeca = $pdo->prepare('UPDATE inventory SET product_search = :novo WHERE id = :id');
        $atualizarFoto = $pdo->prepare(
            'UPDATE product_photos SET product_search = :novo
              WHERE user_id = :user_id AND brand = :brand AND product_search = :antigo'
        );

        $pecas = 0;
        $fotos = 0;

        $pdo->beginTransaction();
        try {
            foreach ($linhas as $linha) {
                $novo = $semAcento((string) $linha['product']);

                if ($novo === '' || $novo === $linha['product_search']) {
                    continue;
                }

                $atualizarPeca->execute(['novo' => $novo, 'id' => $linha['id']]);
                $pecas++;

                $atualizarFoto->execute([
                    'novo'    => $novo,
                    'user_id' => $linha['user_id'],
                    'brand'   => $linha['brand'],
                    'antigo'  => $linha['product_search'],
                ]);
                $fotos += $atualizarFoto->rowCount();
            }
            $pdo->commit();
        } catch (\Throwable $erro) {
            $pdo->rollBack();
            throw $erro;
        }

        $passos = [];
        if ($pecas > 0) {
            $passos[] = "Código de busca refeito em {$pecas} peça(s) com acento";
        }
        if ($fotos > 0) {
            $passos[] = "Vínculo de {$fotos} foto(s) acompanhou o novo código";
        }

        return $passos;
    },
];
