<?php

namespace App\Models;

use App\Core\Database;
use App\Services\ImageService;

/**
 * Fotos das peças.
 *
 * O vínculo é feito pelo código normalizado da peça (product_search) somado
 * à marca, e não pelo id da linha de estoque. Cada planilha enviada apaga e
 * recria as linhas de `inventory`; amarrar a foto ao id faria o fornecedor
 * perder todas as imagens a cada atualização de estoque.
 */
class ProductPhoto
{
    /** Quantas fotos cada peça pode ter */
    public const MAX_POR_PRODUTO = 3;

    /** Teto de fotos por fornecedor, para não estourar o disco da hospedagem */
    public const MAX_POR_FORNECEDOR = 600;

    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM product_photos WHERE id = :id LIMIT 1", ['id' => $id]);
    }

    /**
     * Fotos de uma peça, na ordem definida pelo fornecedor.
     */
    public static function doProduto(int $userId, string $productSearch, string $brand): array
    {
        return Database::fetchAll(
            "SELECT * FROM product_photos
              WHERE user_id = :user_id AND product_search = :codigo AND brand = :marca
              ORDER BY position ASC, id ASC",
            ['user_id' => $userId, 'codigo' => $productSearch, 'marca' => $brand]
        );
    }

    public static function contarDoProduto(int $userId, string $productSearch, string $brand): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM product_photos
              WHERE user_id = :user_id AND product_search = :codigo AND brand = :marca",
            ['user_id' => $userId, 'codigo' => $productSearch, 'marca' => $brand],
            0
        );
    }

    public static function contarDoFornecedor(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM product_photos WHERE user_id = :user_id",
            ['user_id' => $userId],
            0
        );
    }

    public static function espacoUsado(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COALESCE(SUM(size_bytes), 0) FROM product_photos WHERE user_id = :user_id",
            ['user_id' => $userId],
            0
        );
    }

    /**
     * Mapa código+marca => miniatura, para montar uma listagem sem fazer
     * uma consulta por linha.
     *
     * @param array<int,array{user_id:mixed,product_search:mixed,brand:mixed}> $itens
     * @return array<string,array>
     */
    public static function mapaParaItens(array $itens): array
    {
        if (empty($itens)) {
            return [];
        }

        $chaves = [];
        $params = [];
        $i = 0;

        foreach ($itens as $item) {
            $chave = self::chave((int) $item['user_id'], (string) $item['product_search'], (string) $item['brand']);
            if (isset($chaves[$chave])) {
                continue;
            }

            $chaves[$chave] = true;
            $params["u{$i}"] = (int) $item['user_id'];
            $params["c{$i}"] = (string) $item['product_search'];
            $params["m{$i}"] = (string) $item['brand'];
            $i++;
        }

        if ($i === 0) {
            return [];
        }

        $condicoes = [];
        for ($k = 0; $k < $i; $k++) {
            $condicoes[] = "(user_id = :u{$k} AND product_search = :c{$k} AND brand = :m{$k})";
        }

        $linhas = Database::fetchAll(
            "SELECT * FROM product_photos WHERE " . implode(' OR ', $condicoes) . " ORDER BY position ASC, id ASC",
            $params
        );

        $mapa = [];
        foreach ($linhas as $foto) {
            $chave = self::chave((int) $foto['user_id'], (string) $foto['product_search'], (string) $foto['brand']);
            $mapa[$chave][] = $foto;
        }

        return $mapa;
    }

    public static function chave(int $userId, string $productSearch, string $brand): string
    {
        return $userId . '|' . $productSearch . '|' . $brand;
    }

    /**
     * Grava a foto já processada.
     *
     * @return array{success:bool, message:string, id:?int}
     */
    public static function criar(
        int $userId,
        string $productSearch,
        string $brand,
        string $productLabel,
        array $dados
    ): array {
        $posicao = (int) Database::fetchColumn(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM product_photos
              WHERE user_id = :user_id AND product_search = :codigo AND brand = :marca",
            ['user_id' => $userId, 'codigo' => $productSearch, 'marca' => $brand],
            1
        );

        $id = Database::insert('product_photos', [
            'user_id'        => $userId,
            'product_search' => $productSearch,
            'brand'          => $brand,
            'product_label'  => mb_substr($productLabel, 0, 255),
            'filename'       => $dados['filename'],
            'thumbname'      => $dados['thumbname'],
            'size_bytes'     => $dados['size_bytes'],
            'width'          => $dados['width'],
            'height'         => $dados['height'],
            'position'       => min($posicao, 250),
        ]);

        return ['success' => true, 'message' => 'Foto salva.', 'id' => $id];
    }

    /**
     * Remove a foto do banco e do disco.
     */
    public static function excluir(int $photoId, ?int $userId = null): ?array
    {
        $where = 'id = :id';
        $params = ['id' => $photoId];

        if ($userId !== null) {
            $where .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $foto = Database::fetch("SELECT * FROM product_photos WHERE {$where} LIMIT 1", $params);

        if (!$foto) {
            return null;
        }

        Database::delete('product_photos', 'id = :id', ['id' => $photoId]);

        ImageService::apagar(
            (int) $foto['user_id'],
            (string) $foto['filename'],
            (string) $foto['thumbname']
        );

        return $foto;
    }

    /**
     * Torna uma foto a principal (a que aparece na busca).
     */
    public static function definirPrincipal(int $photoId, ?int $userId = null): ?array
    {
        $foto = self::findById($photoId);

        if (!$foto || ($userId !== null && (int) $foto['user_id'] !== $userId)) {
            return null;
        }

        // Empurra as demais para trás e coloca esta em primeiro
        Database::query(
            "UPDATE product_photos SET position = position + 1
              WHERE user_id = :user_id AND product_search = :codigo AND brand = :marca AND id <> :id",
            [
                'user_id' => (int) $foto['user_id'],
                'codigo'  => (string) $foto['product_search'],
                'marca'   => (string) $foto['brand'],
                'id'      => $photoId,
            ]
        );

        Database::update('product_photos', ['position' => 1], 'id = :id', ['id' => $photoId]);

        return $foto;
    }

    /**
     * Fotos sem nenhuma peça correspondente no estoque atual.
     * Servem de aviso: o fornecedor pode tê-las perdido em um reenvio.
     */
    public static function orfas(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM product_photos p
              WHERE p.user_id = :user_id
                AND NOT EXISTS (
                    SELECT 1 FROM inventory i
                     WHERE i.user_id = p.user_id
                       AND i.product_search = p.product_search
                       AND i.brand = p.brand
                )",
            ['user_id' => $userId],
            0
        );
    }
}
