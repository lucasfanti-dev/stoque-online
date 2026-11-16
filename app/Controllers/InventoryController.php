<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Inventory;
use App\Services\ExcelService;
use App\Services\ImageService;
use App\Models\ProductPhoto;

class InventoryController extends Controller
{
    public function showUpload(): void
    {
        $userId = (int) Auth::id();

        $this->render('inventory/upload', [
            'batches'          => Inventory::getBatches($userId, 10),
            'totalBatches'     => Inventory::countBatches($userId),
            'removableBatches' => Inventory::countRemovableBatches($userId),
            'totalItems'       => Inventory::countByUser($userId),
            'maxFileSize'      => $this->maxUploadSize(),
        ]);
    }

    /**
     * Remove um envio antigo do histórico (não mexe no estoque publicado).
     */
    public function deleteBatch(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $batchId = (int) $this->input('batch_id', 0);

        $result = Inventory::deleteBatch($batchId, $userId);

        if (!$result['success']) {
            $this->redirect('estoque/upload', ['error' => $result['message']]);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'batch_deleted');
        $this->redirect('estoque/upload', ['success' => $result['message']]);
    }

    /**
     * Limpa de uma vez todos os envios já substituídos.
     */
    public function clearBatchHistory(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $removidos = Inventory::deleteOldBatches($userId);

        if ($removidos === 0) {
            $this->redirect('estoque/upload', ['info' => 'Não há envios antigos para remover.']);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'batch_history_cleared');

        $this->redirect('estoque/upload', [
            'success' => $removidos . ' envio(s) antigo(s) removido(s) do histórico. '
                . 'Seu estoque publicado não foi alterado.',
        ]);
    }

    public function upload(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $file = $_FILES['file'] ?? null;

        // POST vazio significa, quase sempre, arquivo maior que post_max_size
        if ($file === null && empty($_POST)) {
            $this->redirect('estoque/upload', [
                'error' => 'O arquivo enviado excede o limite do servidor ('
                    . $this->formatBytes($this->maxUploadSize()) . '). Divida a planilha em partes menores.',
            ]);
        }

        if (!$file || !isset($file['error'])) {
            $this->redirect('estoque/upload', ['error' => 'Selecione um arquivo para enviar.']);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('estoque/upload', ['error' => $this->uploadErrorMessage((int) $file['error'])]);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->redirect('estoque/upload', ['error' => 'Envio inválido. Tente novamente.']);
        }

        $result = ExcelService::processUpload($userId, $file['tmp_name'], (string) $file['name']);

        if ($result['success']) {
            Auth::logAudit($userId, Auth::user()['email'] ?? null, 'stock_uploaded');
            $this->redirect('estoque/upload', ['success' => $result['message']]);
        }

        $this->redirect('estoque/upload', ['error' => $result['message']]);
    }

    public function myStock(): void
    {
        $userId = (int) Auth::id();
        $search = $this->text('busca', '', 60);

        [$limit, $offset] = Database::paginate((int) $this->input('pagina', 1), 50);

        $items = Inventory::getByUser($userId, $limit, $offset, $search);
        $total = Inventory::countByUser($userId, $search);

        $this->render('inventory/my_stock', [
            'items'      => $items,
            'fotos'      => ProductPhoto::mapaParaItens($items),
            'total'      => $total,
            'totalGeral' => Inventory::countByUser($userId),
            'esgotados'  => Inventory::countOutOfStock($userId),
            'totalFotos' => ProductPhoto::contarDoFornecedor($userId),
            'fotosOrfas' => ProductPhoto::orfas($userId),
            'maxFotos'   => ProductPhoto::MAX_POR_PRODUTO,
            'page'       => max(1, (int) $this->input('pagina', 1)),
            'pages'      => (int) ceil($total / $limit),
            'search'     => $search,
            'podeEditar' => Auth::hasActiveLicense(),
        ]);
    }

    /**
     * Inclui uma peça avulsa, sem precisar reenviar a planilha inteira.
     */
    public function addItem(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();

        $resultado = Inventory::addItem(
            $userId,
            $this->text('brand', '', 100),
            $this->text('product', '', 255),
            (int) $this->input('quantity', 0)
        );

        if (!$resultado['success']) {
            $this->redirect($this->voltarPara(), ['error' => $resultado['message']]);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'stock_item_added');

        $this->redirect($this->voltarPara(), ['success' => $resultado['message']]);
    }

    /**
     * Ajusta a quantidade de uma peça (o caso de "vendi 3 unidades").
     */
    public function updateItemQuantity(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $itemId = (int) $this->input('item_id', 0);
        $quantidade = (int) $this->input('quantity', 0);

        $item = Inventory::updateQuantity($itemId, $userId, $quantidade);

        if (!$item) {
            $this->redirect($this->voltarPara(), ['error' => 'Peça não encontrada no seu estoque.']);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'stock_item_updated');

        $mensagem = sprintf(
            '%s %s: %s → %s un.',
            $item['brand'],
            $item['product'],
            number_format((int) $item['quantity_anterior'], 0, ',', '.'),
            number_format((int) $item['quantity'], 0, ',', '.')
        );

        if ((int) $item['quantity'] === 0) {
            $mensagem .= ' A peça ficou marcada como esgotada e saiu da busca.';
        }

        $this->redirect($this->voltarPara(), ['success' => $mensagem]);
    }

    /**
     * Remove uma peça do catálogo.
     */
    public function deleteItem(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $item = Inventory::deleteUserItem((int) $this->input('item_id', 0), $userId);

        if (!$item) {
            $this->redirect($this->voltarPara(), ['error' => 'Peça não encontrada no seu estoque.']);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'stock_item_deleted');

        $this->redirect($this->voltarPara(), [
            'success' => "Peça {$item['brand']} {$item['product']} removida do seu estoque.",
        ]);
    }

    /**
     * Envia uma foto para uma peça do próprio estoque.
     */
    public function uploadPhoto(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $item = Inventory::findItem((int) $this->input('item_id', 0), $userId);

        if (!$item) {
            $this->redirect($this->voltarPara(), ['error' => 'Peça não encontrada no seu estoque.']);
        }

        $codigo = (string) $item['product_search'];
        $marca = (string) $item['brand'];

        if (ProductPhoto::contarDoProduto($userId, $codigo, $marca) >= ProductPhoto::MAX_POR_PRODUTO) {
            $this->redirect($this->voltarPara(), [
                'error' => 'Esta peça já tem ' . ProductPhoto::MAX_POR_PRODUTO
                    . ' fotos. Exclua uma antes de enviar outra.',
            ]);
        }

        if (ProductPhoto::contarDoFornecedor($userId) >= ProductPhoto::MAX_POR_FORNECEDOR) {
            $this->redirect($this->voltarPara(), [
                'error' => 'Você atingiu o limite de ' . number_format(ProductPhoto::MAX_POR_FORNECEDOR, 0, ',', '.')
                    . ' fotos na conta. Remova fotos antigas para enviar novas.',
            ]);
        }

        $resultado = ImageService::processarUpload($_FILES['foto'] ?? [], $userId);

        if (!$resultado['success']) {
            $this->redirect($this->voltarPara(), ['error' => $resultado['message']]);
        }

        ProductPhoto::criar($userId, $codigo, $marca, (string) $item['product'], $resultado['dados']);

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'product_photo_added');

        $this->redirect($this->voltarPara(), [
            'success' => "Foto adicionada à peça {$item['brand']} {$item['product']}.",
        ]);
    }

    /**
     * Remove uma foto.
     */
    public function deletePhoto(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $foto = ProductPhoto::excluir((int) $this->input('photo_id', 0), $userId);

        if (!$foto) {
            $this->redirect($this->voltarPara(), ['error' => 'Foto não encontrada.']);
        }

        Auth::logAudit($userId, Auth::user()['email'] ?? null, 'product_photo_deleted');

        $this->redirect($this->voltarPara(), [
            'success' => "Foto de {$foto['brand']} {$foto['product_label']} removida.",
        ]);
    }

    /**
     * Escolhe qual foto aparece na busca.
     */
    public function setMainPhoto(): void
    {
        $this->validateCsrf();

        $userId = (int) Auth::id();
        $foto = ProductPhoto::definirPrincipal((int) $this->input('photo_id', 0), $userId);

        if (!$foto) {
            $this->redirect($this->voltarPara(), ['error' => 'Foto não encontrada.']);
        }

        $this->redirect($this->voltarPara(), ['success' => 'Foto principal atualizada.']);
    }

    /**
     * Preserva a busca e a página em que o usuário estava ao voltar.
     */
    private function voltarPara(): string
    {
        $destino = 'estoque/meu-estoque';
        $query = [];

        $busca = $this->text('retorno_busca', '', 60);
        if ($busca !== '') {
            $query['busca'] = $busca;
        }

        $pagina = (int) $this->input('retorno_pagina', 0);
        if ($pagina > 1) {
            $query['pagina'] = $pagina;
        }

        return $query ? $destino . '?' . http_build_query($query) : $destino;
    }

    /**
     * Exporta o estoque do próprio usuário em CSV (aberto direto no Excel).
     */
    public function exportMyStock(): void
    {
        $userId = (int) Auth::id();
        $stmt = Inventory::streamByUser($userId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=meu_estoque_' . date('Y-m-d') . '.csv');
        header('Cache-Control: no-store');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Marca', 'Produto', 'Quantidade'], ';');

        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['brand'], $row['product'], $row['quantity']], ';');
        }

        fclose($output);
        exit;
    }

    public function downloadTemplate(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=modelo_estoque_stoqueonline.csv');
        header('Cache-Control: no-store');

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para o Excel abrir com acentos corretos
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Marca', 'Produto', 'Quantidade'], ';');
        fputcsv($output, ['SKF', '6205-2RSH/C3', '25'], ';');
        fputcsv($output, ['NSK', '6004 DDU', '10'], ';');
        fputcsv($output, ['TIMKEN', '32210', '8'], ';');
        fputcsv($output, ['FAG', '22218-E1-K', '4'], ';');
        fputcsv($output, ['NTN', '320/28 X', '15'], ';');
        fputcsv($output, ['INA', 'HK 2016', '50'], ';');

        fclose($output);
        exit;
    }

    private function maxUploadSize(): int
    {
        $post = $this->parseIniSize((string) ini_get('post_max_size'));
        $upload = $this->parseIniSize((string) ini_get('upload_max_filesize'));

        $limits = array_filter([$post, $upload]);

        return $limits ? (int) min($limits) : 2097152;
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024) . ' KB';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'O arquivo excede o tamanho máximo permitido pelo servidor ('
                . $this->formatBytes($this->maxUploadSize()) . ').',
            UPLOAD_ERR_PARTIAL   => 'O envio foi interrompido. Verifique sua conexão e tente novamente.',
            UPLOAD_ERR_NO_FILE   => 'Nenhum arquivo foi selecionado.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                'Falha temporária do servidor ao gravar o arquivo. Tente novamente em instantes.',
            UPLOAD_ERR_EXTENSION => 'O envio foi bloqueado pelo servidor.',
            default              => 'Não foi possível enviar o arquivo. Tente novamente.',
        };
    }
}
