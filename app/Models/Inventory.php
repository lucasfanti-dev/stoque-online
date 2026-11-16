<?php

namespace App\Models;

use App\Core\Database;

class Inventory
{
    /**
     * Letras acentuadas viram a letra simples antes de a pontuação sair. Sem
     * isso "AÇO" ficava "AO" no índice e quem digitava "aço" ou "aco" não
     * achava a peça.
     */
    private const SEM_ACENTO = [
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

    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', strtr($code, self::SEM_ACENTO)));
    }

    /**
     * Ordena do mais parecido para o menos: código idêntico, código que
     * começa com o que foi digitado e, por último, os que só contêm. Muda só
     * a ordem — quais peças aparecem continua sendo decidido pelos filtros.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function relevance(string $keyword, string $prefix = 'i.'): array
    {
        $normalized = self::normalizeCode($keyword);

        if ($normalized === '') {
            // Busca só por marca. "0" aqui viraria ORDER BY 0, que o MySQL lê
            // como posição de coluna e recusa; NULL é neutro.
            return ['NULL', []];
        }

        $escapado = self::escapeLike($normalized);

        return [
            "CASE WHEN {$prefix}product_search = :rel_exato THEN 0
                  WHEN {$prefix}product_search LIKE :rel_inicio THEN 1
                  WHEN {$prefix}product_search LIKE :rel_meio THEN 2
                  ELSE 3 END",
            [
                'rel_exato'  => $normalized,
                'rel_inicio' => $escapado . '%',
                'rel_meio'   => '%' . $escapado . '%',
            ],
        ];
    }

    /**
     * Monta as condições compartilhadas entre a busca, a contagem e o
     * agrupamento por modelo — uma única fonte de verdade para os filtros.
     *
     * Segue a mesma regra da versão 1, que é a que os clientes conhecem:
     *
     *   Começa com  código começa com o que foi digitado      "23048" -> 23048 CAK, 23048 KMB...
     *   Contém      o que foi digitado aparece em qualquer ponto
     *   Exata       o código é exatamente o digitado
     *
     * Espaço, hífen, barra e qualquer pontuação são ignorados nos dois lados,
     * então "6205-2RS", "6205 2RS" e "62052RS" são o mesmo código.
     *
     * "Incluindo" pede um texto a mais no código, antes ou depois do que foi
     * digitado — é assim que se acha "23048 com K": código 23048, incluindo K.
     * "Excluindo" tira os códigos que tenham o texto.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildFilters(
        string $keyword,
        string $mode,
        ?string $brand,
        ?int $minQty,
        ?string $include,
        ?string $exclude,
        string $prefix = 'i.'
    ): array {
        $kw  = self::escapeLike(self::normalizeCode($keyword));
        $inc = self::escapeLike(self::normalizeCode((string) $include));
        $exc = self::escapeLike(self::normalizeCode((string) $exclude));
        $params = [];

        // Peça esgotada continua no catálogo do fornecedor, mas não aparece
        // para quem procura: anunciar "0 un" só gera contato frustrado.
        $where = ["{$prefix}quantity > 0"];

        if ($kw !== '' && $inc === '') {
            if ($mode === 'exata') {
                // Compara pelo código normalizado: product_search é a versão
                // sem pontuação de product, então cobre também a forma original.
                // O "OR product = ..." que existia aqui era redundante e, por
                // envolver coluna sem índice, forçava varredura da tabela
                // inteira (1,8 s contra 1 ms em uma base de 600 mil itens).
                $where[] = "{$prefix}product_search = :kw_norm";
                $params['kw_norm'] = self::normalizeCode($keyword);
            } else {
                $where[] = "{$prefix}product_search LIKE :kw_like";
                $params['kw_like'] = ($mode === 'contem' ? '%' : '') . $kw . '%';
            }
        } elseif ($kw !== '') {
            // Com "Incluindo", o texto extra pode vir antes ou depois do
            // código, como na v1. Os dois padrões ficam entre parênteses:
            // na v1 eles ficavam soltos, e a marca e a quantidade valiam só
            // para um dos lados do OR.
            [$antes, $depois] = match ($mode) {
                'exata'  => ["{$inc}%{$kw}",    "{$kw}%{$inc}"],
                'contem' => ["%{$inc}%{$kw}%",  "%{$kw}%{$inc}%"],
                default  => ["{$inc}%{$kw}%",   "{$kw}%{$inc}%"],
            };
            $where[] = "({$prefix}product_search LIKE :inc_antes OR {$prefix}product_search LIKE :inc_depois)";
            $params['inc_antes'] = $antes;
            $params['inc_depois'] = $depois;
        } elseif ($inc !== '') {
            // Busca só pela marca, com um texto obrigatório no código
            $where[] = "{$prefix}product_search LIKE :inc";
            $params['inc'] = '%' . $inc . '%';
        }

        if ($exc !== '') {
            $where[] = "{$prefix}product_search NOT LIKE :exc";
            $params['exc'] = '%' . $exc . '%';
        }

        if (!empty($brand)) {
            // Marca exata, como na v1 (maiúsculas não importam: collation _ci).
            // A tela sugere as marcas existentes enquanto a pessoa digita.
            $where[] = "{$prefix}brand = :brand";
            $params['brand'] = trim((string) preg_replace('/\s+/u', ' ', $brand));
        }

        if ($minQty !== null && $minQty > 0) {
            $where[] = "{$prefix}quantity >= :min_qty";
            $params['min_qty'] = $minQty;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Neutraliza os curingas do LIKE para que "%" digitado pelo usuário não
     * transforme a busca em uma varredura completa da tabela.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public static function search(
        string $keyword,
        string $mode = 'comeca',
        ?string $brand = null,
        ?int $minQty = null,
        ?string $include = null,
        ?string $exclude = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        if (self::normalizeCode($keyword) === '' && empty($brand)) {
            return [];
        }

        [$whereSql, $params] = self::buildFilters($keyword, $mode, $brand, $minQty, $include, $exclude);
        [$relevanceSql, $relevanceParams] = self::relevance($keyword);
        $params += $relevanceParams;

        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        // A foto principal vem junto pelo código da peça (sobrevive aos
        // reenvios de planilha, que trocam os ids de inventory).
        $sql = "SELECT i.id, i.brand, i.product, i.product_search, i.quantity, i.created_at, i.user_id,
                       COALESCE(c.trading_name, c.company_name, u.name) as supplier_name,
                       c.city, c.state, c.whatsapp, c.phone, c.contact_email,
                       (SELECT f.thumbname FROM product_photos f
                         WHERE f.user_id = i.user_id
                           AND f.product_search = i.product_search
                           AND f.brand = i.brand
                         ORDER BY f.position ASC, f.id ASC LIMIT 1) AS foto_miniatura,
                       (SELECT COUNT(*) FROM product_photos f
                         WHERE f.user_id = i.user_id
                           AND f.product_search = i.product_search
                           AND f.brand = i.brand) AS fotos_total
                FROM inventory i
                JOIN users u ON u.id = i.user_id
                LEFT JOIN companies c ON c.user_id = u.id
                WHERE {$whereSql}
                ORDER BY {$relevanceSql}, i.product ASC, i.quantity DESC, i.id ASC
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }

    public static function countSearch(
        string $keyword,
        string $mode = 'comeca',
        ?string $brand = null,
        ?int $minQty = null,
        ?string $include = null,
        ?string $exclude = null
    ): int {
        if (self::normalizeCode($keyword) === '' && empty($brand)) {
            return 0;
        }

        [$whereSql, $params] = self::buildFilters($keyword, $mode, $brand, $minQty, $include, $exclude);

        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM inventory i WHERE {$whereSql}",
            $params,
            0
        );
    }

    public static function getDistinctModels(
        string $keyword,
        string $mode = 'comeca',
        ?string $brand = null,
        ?int $minQty = null,
        ?string $include = null,
        ?string $exclude = null
    ): array {
        if (self::normalizeCode($keyword) === '' && empty($brand)) {
            return [];
        }

        // Mesmos filtros da tabela: um chip de modelo que a tabela não mostra
        // (por causa do "Excluindo", por exemplo) só confundiria.
        [$whereSql, $params] = self::buildFilters($keyword, $mode, $brand, $minQty, $include, $exclude);
        [$relevanceSql, $relevanceParams] = self::relevance($keyword);
        $params += $relevanceParams;

        $sql = "SELECT i.product, i.brand,
                       COUNT(DISTINCT i.user_id) as suppliers_count,
                       SUM(i.quantity) as total_pieces,
                       MIN({$relevanceSql}) as relevancia
                FROM inventory i
                WHERE {$whereSql}
                GROUP BY i.product, i.brand
                ORDER BY relevancia ASC, total_pieces DESC, i.product ASC
                LIMIT 60";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Marcas com peça em estoque, das mais comuns para as menos, para o campo
     * Marca sugerir enquanto a pessoa digita. Como o filtro de marca é exato,
     * a sugestão evita o "não achei" por escrever "Timken " ou "INA BULK".
     *
     * @return list<string>
     */
    public static function brandsInStock(): array
    {
        try {
            $linhas = Database::fetchAll(
                "SELECT brand FROM inventory
                  WHERE quantity > 0 AND brand <> ''
                  GROUP BY brand
                  ORDER BY COUNT(*) DESC, brand ASC
                  LIMIT 1000"
            );
        } catch (\Throwable) {
            // Sem a lista a busca funciona igual; não vale derrubar a página
            return [];
        }

        return array_map(static fn(array $l): string => (string) $l['brand'], $linhas);
    }

    // -----------------------------------------------------------------
    // Edição peça a peça (sem precisar reenviar a planilha)
    // -----------------------------------------------------------------

    /** Nome da carga que agrupa os itens cadastrados à mão */
    public const CARGA_MANUAL = 'Cadastro manual';

    public static function findItem(int $itemId, ?int $userId = null): ?array
    {
        $where = 'id = :id';
        $params = ['id' => $itemId];

        if ($userId !== null) {
            $where .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        return Database::fetch("SELECT * FROM inventory WHERE {$where} LIMIT 1", $params);
    }

    /**
     * Procura uma peça já cadastrada pelo código normalizado, para evitar
     * duplicidade quando o fornecedor inclui um item à mão.
     */
    public static function findByCode(int $userId, string $product, string $brand): ?array
    {
        return Database::fetch(
            "SELECT * FROM inventory
              WHERE user_id = :user_id
                AND product_search = :code
                AND brand = :brand
              LIMIT 1",
            [
                'user_id' => $userId,
                'code'    => self::normalizeCode($product),
                'brand'   => trim($brand),
            ]
        );
    }

    /**
     * Carga usada para agrupar as inclusões manuais do período atual.
     * É substituída junto com o resto quando chega uma planilha nova.
     */
    public static function getOrCreateManualBatch(int $userId): int
    {
        $existente = Database::fetchColumn(
            "SELECT id FROM inventory_batches
              WHERE user_id = :user_id AND filename = :nome AND status = 1
              ORDER BY id DESC LIMIT 1",
            ['user_id' => $userId, 'nome' => self::CARGA_MANUAL]
        );

        if ($existente) {
            return (int) $existente;
        }

        return Database::insert('inventory_batches', [
            'user_id'     => $userId,
            'filename'    => self::CARGA_MANUAL,
            'total_items' => 0,
            'status'      => 1,
        ]);
    }

    /**
     * Mantém o contador da carga alinhado com o que existe de fato.
     */
    public static function refreshBatchCount(int $batchId): void
    {
        Database::query(
            "UPDATE inventory_batches b
                SET b.total_items = (SELECT COUNT(*) FROM inventory i WHERE i.batch_id = b.id)
              WHERE b.id = :id",
            ['id' => $batchId]
        );
    }

    /**
     * Inclui uma peça avulsa.
     *
     * @return array{success:bool, message:string, item:?array}
     */
    public static function addItem(int $userId, string $brand, string $product, int $quantity): array
    {
        $brand = trim(preg_replace('/\s+/u', ' ', $brand) ?? '');
        $product = trim(preg_replace('/\s+/u', ' ', $product) ?? '');

        if ($product === '') {
            return ['success' => false, 'message' => 'Informe o código da peça.', 'item' => null];
        }

        $codigo = self::normalizeCode($product);

        if ($codigo === '') {
            return ['success' => false, 'message' => 'O código precisa ter ao menos uma letra ou número.', 'item' => null];
        }

        $brand = mb_substr($brand !== '' ? $brand : 'GENÉRICA', 0, 100);
        $product = mb_substr($product, 0, 255);
        $quantity = max(0, min($quantity, 99999999));

        $jaExiste = self::findByCode($userId, $product, $brand);

        if ($jaExiste) {
            return [
                'success' => false,
                'message' => "A peça {$brand} {$jaExiste['product']} já está no seu estoque com "
                    . number_format((int) $jaExiste['quantity'], 0, ',', '.')
                    . ' un. Use a coluna de quantidade para ajustar.',
                'item'    => $jaExiste,
            ];
        }

        $batchId = self::getOrCreateManualBatch($userId);

        $id = Database::insert('inventory', [
            'user_id'        => $userId,
            'batch_id'       => $batchId,
            'brand'          => $brand,
            'product'        => $product,
            'product_search' => $codigo,
            'quantity'       => $quantity,
        ]);

        self::refreshBatchCount($batchId);

        return [
            'success' => true,
            'message' => "Peça {$brand} {$product} incluída com "
                . number_format($quantity, 0, ',', '.') . ' un.',
            'item'    => self::findItem($id),
        ];
    }

    /**
     * Ajusta a quantidade de uma peça.
     *
     * Quantidade zero é permitida: a peça continua no catálogo do
     * fornecedor, marcada como esgotada, e some da busca pública.
     */
    public static function updateQuantity(int $itemId, int $userId, int $quantity): ?array
    {
        $item = self::findItem($itemId, $userId);

        if (!$item) {
            return null;
        }

        $quantity = max(0, min($quantity, 99999999));

        Database::update(
            'inventory',
            ['quantity' => $quantity],
            'id = :id AND user_id = :user_id',
            ['id' => $itemId, 'user_id' => $userId]
        );

        $item['quantity_anterior'] = (int) $item['quantity'];
        $item['quantity'] = $quantity;

        return $item;
    }

    /**
     * Exclui uma peça do estoque do próprio dono.
     */
    public static function deleteUserItem(int $itemId, int $userId): ?array
    {
        $item = self::findItem($itemId, $userId);

        if (!$item) {
            return null;
        }

        Database::delete('inventory', 'id = :id AND user_id = :user_id', [
            'id'      => $itemId,
            'user_id' => $userId,
        ]);

        self::refreshBatchCount((int) $item['batch_id']);

        return $item;
    }

    public static function createBatch(int $userId, string $filename, int $totalItems): int
    {
        return Database::insert('inventory_batches', [
            'user_id'     => $userId,
            'filename'    => mb_substr($filename, 0, 255),
            'total_items' => $totalItems,
            'status'      => 1,
        ]);
    }

    /**
     * Marca os envios anteriores como encerrados, preservando o histórico
     * (os itens já foram substituídos pelo novo arquivo).
     */
    public static function closePreviousBatches(int $userId): void
    {
        Database::update(
            'inventory_batches',
            ['status' => 0],
            'user_id = :user_id AND status = 1',
            ['user_id' => $userId]
        );
    }

    public static function deleteUserInventory(int $userId): void
    {
        Database::delete('inventory', 'user_id = :user_id', ['user_id' => $userId]);
    }

    /**
     * Histórico de envios com a contagem real de peças ainda vinculadas a
     * cada lote. Um lote com zero peças é apenas registro histórico.
     */
    public static function getBatches(int $userId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        return Database::fetchAll(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM inventory i WHERE i.batch_id = b.id) AS itens_vinculados
             FROM inventory_batches b
             WHERE b.user_id = :user_id
             ORDER BY b.id DESC
             LIMIT {$limit}",
            ['user_id' => $userId]
        );
    }

    public static function countBatches(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM inventory_batches WHERE user_id = :user_id",
            ['user_id' => $userId],
            0
        );
    }

    /**
     * Lotes que podem ser apagados: os que não têm mais nenhuma peça
     * vinculada, ou seja, cargas já substituídas por um envio mais novo.
     */
    public static function countRemovableBatches(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM inventory_batches b
              WHERE b.user_id = :user_id
                AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.batch_id = b.id)",
            ['user_id' => $userId],
            0
        );
    }

    /**
     * Apaga um registro do histórico de envios.
     *
     * Recusa lotes que ainda tenham peças vinculadas: esse é o estoque que
     * está publicado hoje, e apagá-lo tiraria as peças do ar por engano.
     *
     * @return array{success:bool, message:string}
     */
    public static function deleteBatch(int $batchId, int $userId): array
    {
        $batch = Database::fetch(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM inventory i WHERE i.batch_id = b.id) AS itens_vinculados
             FROM inventory_batches b
             WHERE b.id = :id AND b.user_id = :user_id
             LIMIT 1",
            ['id' => $batchId, 'user_id' => $userId]
        );

        if (!$batch) {
            return ['success' => false, 'message' => 'Envio não encontrado no seu histórico.'];
        }

        if ((int) $batch['itens_vinculados'] > 0) {
            return [
                'success' => false,
                'message' => 'Este é o envio que está publicado no momento ('
                    . number_format((int) $batch['itens_vinculados'], 0, ',', '.')
                    . ' peças no ar). Para trocar o estoque, envie uma planilha nova.',
            ];
        }

        Database::delete(
            'inventory_batches',
            'id = :id AND user_id = :user_id',
            ['id' => $batchId, 'user_id' => $userId]
        );

        return ['success' => true, 'message' => 'Registro de envio removido do histórico.'];
    }

    /**
     * Limpa de uma vez todos os envios antigos (sem peças vinculadas).
     *
     * @return int quantidade de registros removidos
     */
    public static function deleteOldBatches(int $userId): int
    {
        return Database::delete(
            'inventory_batches',
            'user_id = :user_id AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.batch_id = inventory_batches.id)',
            ['user_id' => $userId]
        );
    }

    public static function insertBatch(int $userId, int $batchId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $placeholders = [];
        $values = [];

        foreach ($items as $item) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?)';
            $values[] = $userId;
            $values[] = $batchId;
            $values[] = trim((string) $item['brand']);
            $values[] = trim((string) $item['product']);
            $values[] = self::normalizeCode((string) $item['product']);
            $values[] = (int) $item['quantity'];
        }

        $sql = "INSERT INTO `inventory` (`user_id`, `batch_id`, `brand`, `product`, `product_search`, `quantity`) VALUES "
            . implode(', ', $placeholders);

        Database::getConnection()->prepare($sql)->execute($values);
    }

    public static function getByUser(int $userId, int $limit, int $offset, string $search = ''): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        $where = 'user_id = :user_id';
        $params = ['user_id' => $userId];

        $codigo = self::normalizeCode($search);
        if ($codigo !== '') {
            $where .= ' AND product_search LIKE :search';
            $params['search'] = '%' . self::escapeLike($codigo) . '%';
        }

        return Database::fetchAll(
            "SELECT * FROM inventory WHERE {$where} ORDER BY product ASC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /**
     * Peças que o fornecedor mantém no catálogo mas estão zeradas.
     */
    public static function countOutOfStock(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM inventory WHERE user_id = :user_id AND quantity = 0",
            ['user_id' => $userId],
            0
        );
    }

    public static function countByUser(int $userId, string $search = ''): int
    {
        $where = 'user_id = :user_id';
        $params = ['user_id' => $userId];

        $codigo = self::normalizeCode($search);
        if ($codigo !== '') {
            $where .= ' AND product_search LIKE :search';
            $params['search'] = '%' . self::escapeLike($codigo) . '%';
        }

        return (int) Database::fetchColumn("SELECT COUNT(*) FROM inventory WHERE {$where}", $params, 0);
    }

    /**
     * Cursor para exportação em CSV sem carregar tudo em memória.
     */
    public static function streamByUser(int $userId): \PDOStatement
    {
        return Database::query(
            "SELECT brand, product, quantity FROM inventory WHERE user_id = :user_id ORDER BY product ASC",
            ['user_id' => $userId]
        );
    }

    // -----------------------------------------------------------------
    // Administração: acesso ao estoque de qualquer fornecedor
    // -----------------------------------------------------------------

    /**
     * Lista todos os fornecedores com o tamanho do estoque e a situação
     * da licença, para o painel administrativo.
     */
    public static function adminSuppliers(string $search = ''): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE (u.name LIKE :s1 OR u.email LIKE :s2 OR c.trading_name LIKE :s3 OR c.company_name LIKE :s4)";
            $like = '%' . $search . '%';
            $params = ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
        }

        return Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.allowed_access,
                    c.trading_name, c.city, c.state,
                    (SELECT COUNT(*) FROM inventory i WHERE i.user_id = u.id) AS total_itens,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM inventory i WHERE i.user_id = u.id) AS total_pecas,
                    (SELECT COUNT(*) FROM inventory_batches b WHERE b.user_id = u.id) AS total_cargas,
                    (SELECT MAX(b.created_at) FROM inventory_batches b WHERE b.user_id = u.id) AS ultima_carga,
                    (SELECT p.name FROM user_licenses ul
                       JOIN plans p ON p.id = ul.plan_id
                      WHERE ul.user_id = u.id AND ul.status = 1 AND ul.is_closed = 0 AND ul.end_date >= CURDATE()
                      ORDER BY ul.end_date DESC LIMIT 1) AS plano_ativo
             FROM users u
             LEFT JOIN companies c ON c.user_id = u.id
             {$where}
             HAVING total_itens > 0 OR total_cargas > 0
             ORDER BY total_itens DESC, u.name ASC",
            $params
        );
    }

    /**
     * Exclui uma peça específica. Usado apenas pela administração — o
     * fornecedor troca o estoque enviando uma planilha nova.
     */
    public static function deleteItem(int $itemId): ?array
    {
        $item = Database::fetch(
            "SELECT i.*, u.name AS supplier_name FROM inventory i
             JOIN users u ON u.id = i.user_id
             WHERE i.id = :id LIMIT 1",
            ['id' => $itemId]
        );

        if (!$item) {
            return null;
        }

        Database::delete('inventory', 'id = :id', ['id' => $itemId]);

        return $item;
    }

    /**
     * Tira do ar todo o estoque de um fornecedor, mantendo a conta ativa.
     */
    public static function deleteAllByUser(int $userId): int
    {
        $removidos = Database::delete('inventory', 'user_id = :user_id', ['user_id' => $userId]);

        // Sem peças, nenhuma carga continua vigente
        Database::update(
            'inventory_batches',
            ['status' => 0],
            'user_id = :user_id',
            ['user_id' => $userId]
        );

        return $removidos;
    }

    /**
     * Remove um lote sem a trava de dono — apenas para a administração,
     * que precisa resolver carga com problema de qualquer fornecedor.
     */
    public static function adminDeleteBatch(int $batchId): ?array
    {
        $batch = Database::fetch(
            "SELECT b.*, u.name AS supplier_name,
                    (SELECT COUNT(*) FROM inventory i WHERE i.batch_id = b.id) AS itens_vinculados
             FROM inventory_batches b
             JOIN users u ON u.id = b.user_id
             WHERE b.id = :id LIMIT 1",
            ['id' => $batchId]
        );

        if (!$batch) {
            return null;
        }

        // A exclusão em cascata leva junto as peças da carga
        Database::delete('inventory_batches', 'id = :id', ['id' => $batchId]);

        return $batch;
    }

    public static function countTotal(): array
    {
        $items = Database::fetch(
            "SELECT COUNT(*) as total_items, COALESCE(SUM(quantity), 0) as total_pieces FROM inventory"
        );

        $suppliers = Database::fetchColumn("SELECT COUNT(DISTINCT user_id) FROM inventory", [], 0);

        return [
            'total_items'     => (int) ($items['total_items'] ?? 0),
            'total_pieces'    => (int) ($items['total_pieces'] ?? 0),
            'total_suppliers' => (int) $suppliers,
        ];
    }
}
