<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Inventory;
use App\Models\Company;
use App\Models\User;
use App\Models\ProductPhoto;
use App\Services\ImageService;

class SearchController extends Controller
{
    private const PER_PAGE = 50;
    private const EXPORT_LIMIT = 5000;

    public function index(): void
    {
        $this->render('search/index', [
            'initialKeyword' => $this->text('q', '', 60),
            'initialMode'    => $this->mode(),
            'marcas'         => Inventory::brandsInStock(),
        ]);
    }

    public function query(): void
    {
        $filters = $this->filters();

        if ($filters['keyword'] === '' && $filters['brand'] === null) {
            $this->json([
                'success' => true,
                'data'    => [],
                'total'   => 0,
                'page'    => 1,
                'pages'   => 0,
            ]);
        }

        $page = $this->page();
        $offset = ($page - 1) * self::PER_PAGE;

        $results = Inventory::search(
            $filters['keyword'],
            $filters['mode'],
            $filters['brand'],
            $filters['minQty'],
            $filters['include'],
            $filters['exclude'],
            self::PER_PAGE,
            $offset
        );

        $total = Inventory::countSearch(
            $filters['keyword'],
            $filters['mode'],
            $filters['brand'],
            $filters['minQty'],
            $filters['include'],
            $filters['exclude']
        );

        foreach ($results as &$item) {
            $item['whatsapp_link'] = $this->whatsappLink(
                (string) ($item['whatsapp'] ?? ''),
                (string) $item['brand'],
                (string) $item['product'],
                (int) $item['quantity']
            );

            // Endereço da miniatura e quantas fotos a peça tem
            $item['foto_url'] = !empty($item['foto_miniatura'])
                ? ImageService::url((int) $item['user_id'], (string) $item['foto_miniatura'])
                : null;
            $item['fotos_total'] = (int) ($item['fotos_total'] ?? 0);

            // Dados que não precisam trafegar para o navegador
            unset($item['whatsapp'], $item['phone'], $item['contact_email'], $item['foto_miniatura']);
        }
        unset($item);

        $this->json([
            'success' => true,
            'data'    => $results,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) ceil($total / self::PER_PAGE),
            // Quando o "Começa com" não acha nada, a tela oferece o "Contém"
            // dizendo quantos ele traria. O resultado continua sendo o do
            // filtro escolhido — quem decide trocar é a pessoa.
            'sugestao_contem' => $total === 0 ? $this->totalSeFosseContem($filters) : 0,
        ]);
    }

    public function models(): void
    {
        $filters = $this->filters();

        if ($filters['keyword'] === '' && $filters['brand'] === null) {
            $this->json(['success' => true, 'models' => []]);
        }

        $this->json([
            'success' => true,
            'models'  => Inventory::getDistinctModels(
                $filters['keyword'],
                $filters['mode'],
                $filters['brand'],
                $filters['minQty'],
                $filters['include'],
                $filters['exclude']
            ),
        ]);
    }

    public function supplier(): void
    {
        $userId = (int) $this->input('id_user', 0);

        if ($userId <= 0) {
            $this->json(['success' => false, 'message' => 'Fornecedor inválido'], 400);
        }

        $company = Company::findByUserId($userId) ?? [];
        $user = User::findById($userId);

        if (!$user || empty($user['allowed_access'])) {
            $this->json(['success' => false, 'message' => 'Fornecedor não encontrado'], 404);
        }

        $address = trim(trim((string) ($company['address'] ?? '')) . ', ' . trim((string) ($company['number'] ?? '')), ' ,');

        $this->json([
            'success' => true,
            'data'    => [
                'trading_name'  => ($company['trading_name'] ?? '') ?: (($company['company_name'] ?? '') ?: $user['name']),
                'company_name'  => ($company['company_name'] ?? '') ?: 'Não informado',
                'cnpj'          => ($company['cnpj'] ?? '') ?: 'Não informado',
                'address'       => $address !== '' ? $address : 'Não informado',
                'district'      => $company['district'] ?? '',
                'city'          => $company['city'] ?? '',
                'state'         => $company['state'] ?? '',
                'phone'         => ($company['phone'] ?? '') ?: (($user['phone'] ?? '') ?: 'Não informado'),
                'cell_phone'    => $company['cell_phone'] ?? '',
                'whatsapp'      => preg_replace('/[^0-9]/', '', (string) ($company['whatsapp'] ?? '')),
                'contact_email' => ($company['contact_email'] ?? '') ?: $user['email'],
                // Esquemas perigosos (javascript:, data:) são descartados aqui,
                // antes de o valor virar um href no navegador.
                'website'       => safe_url($company['website'] ?? ''),
            ],
        ]);
    }

    /**
     * Todas as fotos de uma peça, para a galeria com ampliação.
     */
    public function photos(): void
    {
        $itemId = (int) $this->input('item_id', 0);

        if ($itemId <= 0) {
            $this->json(['success' => false, 'message' => 'Peça inválida'], 400);
        }

        $item = Inventory::findItem($itemId);

        if (!$item) {
            $this->json(['success' => false, 'message' => 'Peça não encontrada'], 404);
        }

        $fotos = ProductPhoto::doProduto(
            (int) $item['user_id'],
            (string) $item['product_search'],
            (string) $item['brand']
        );

        $this->json([
            'success' => true,
            'produto' => [
                'brand'    => $item['brand'],
                'product'  => $item['product'],
                'quantity' => (int) $item['quantity'],
            ],
            'fotos'   => array_map(static fn(array $f): array => [
                'grande'  => ImageService::url((int) $f['user_id'], (string) $f['filename']),
                'mini'    => ImageService::url((int) $f['user_id'], (string) $f['thumbname']),
                'largura' => (int) $f['width'],
                'altura'  => (int) $f['height'],
            ], $fotos),
        ]);
    }

    /**
     * Exporta o resultado da busca em CSV (recurso equivalente ao export do
     * DataTables na v1). Exige login para evitar varredura anônima da base.
     */
    public function export(): void
    {
        $filters = $this->filters();

        if ($filters['keyword'] === '' && $filters['brand'] === null) {
            $this->redirect('pesquisa', ['warning' => 'Faça uma busca antes de exportar os resultados.']);
        }

        $results = Inventory::search(
            $filters['keyword'],
            $filters['mode'],
            $filters['brand'],
            $filters['minQty'],
            $filters['include'],
            $filters['exclude'],
            self::EXPORT_LIMIT,
            0
        );

        Auth::logAudit(Auth::id(), Auth::user()['email'] ?? null, 'search_exported');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pesquisa_stoqueonline_' . date('Y-m-d_His') . '.csv');
        header('Cache-Control: no-store');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Marca', 'Produto', 'Quantidade', 'Fornecedor', 'Cidade', 'UF'], ';');

        foreach ($results as $row) {
            fputcsv($output, [
                $row['brand'],
                $row['product'],
                $row['quantity'],
                $row['supplier_name'],
                $row['city'] ?? '',
                $row['state'] ?? '',
            ], ';');
        }

        fclose($output);
        exit;
    }

    /**
     * @return array{keyword:string, mode:string, brand:?string, minQty:?int, include:?string, exclude:?string}
     */
    private function filters(): array
    {
        $minQty = (int) $this->input('quantidade_minima', 0);

        return [
            'keyword' => $this->text('palavra_chave', $this->text('q', '', 60), 60),
            'mode'    => $this->mode(),
            'brand'   => $this->text('marca', '', 60) ?: null,
            'minQty'  => $minQty > 0 ? min($minQty, 1000000) : null,
            'include' => $this->text('incluindo', '', 60) ?: null,
            'exclude' => $this->text('excluindo', '', 60) ?: null,
        ];
    }

    /**
     * Quantas peças o "Contém" traria, para a sugestão da tela vazia. Só faz
     * sentido saindo do "Começa com" com um código digitado.
     *
     * @param array{keyword:string, mode:string, brand:?string, minQty:?int, include:?string, exclude:?string} $filters
     */
    private function totalSeFosseContem(array $filters): int
    {
        if ($filters['mode'] !== 'comeca' || Inventory::normalizeCode($filters['keyword']) === '') {
            return 0;
        }

        return Inventory::countSearch(
            $filters['keyword'],
            'contem',
            $filters['brand'],
            $filters['minQty'],
            $filters['include'],
            $filters['exclude']
        );
    }

    private function mode(): string
    {
        $mode = (string) $this->input('tipo_busca', $this->input('modo', 'comeca'));

        return in_array($mode, ['comeca', 'contem', 'exata'], true) ? $mode : 'comeca';
    }

    private function page(): int
    {
        $page = (int) $this->input('pagina', 1);

        // Teto de paginação: evita varreduras profundas e caras na tabela
        return max(1, min($page, 200));
    }

    private function whatsappLink(string $whatsapp, string $brand, string $product, int $quantity): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', $whatsapp) ?? '';

        if (strlen($clean) < 10) {
            return null;
        }

        if (!str_starts_with($clean, '55')) {
            $clean = '55' . $clean;
        }

        if (strlen($clean) > 13) {
            return null;
        }

        $text = rawurlencode(
            "Olá! Vi no StoqueOnline que vocês possuem o rolamento {$brand} {$product} "
            . "com {$quantity} peça(s) em estoque. Ainda está disponível?"
        );

        return "https://wa.me/{$clean}?text={$text}";
    }
}
