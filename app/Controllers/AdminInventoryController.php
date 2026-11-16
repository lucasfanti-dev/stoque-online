<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Inventory;
use App\Models\User;
use App\Models\Company;
use App\Services\ExcelService;

/**
 * Gestão do estoque de qualquer fornecedor pela administração.
 *
 * Toda ação aqui mexe em dados de terceiros, então tudo é registrado na
 * auditoria com o e-mail do administrador e o do dono do estoque.
 */
class AdminInventoryController extends Controller
{
    /**
     * Lista de fornecedores com o tamanho do estoque de cada um.
     */
    public function index(): void
    {
        $search = $this->text('busca', '', 100);

        $this->render('admin/inventory', [
            'suppliers' => Inventory::adminSuppliers($search),
            'search'    => $search,
            'totals'    => Inventory::countTotal(),
        ]);
    }

    /**
     * Estoque de um fornecedor específico.
     */
    public function show(string $id): void
    {
        $userId = (int) $id;
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/estoque', ['error' => 'Fornecedor não encontrado.']);
        }

        $search = $this->text('busca', '', 60);
        [$limit, $offset] = Database::paginate((int) $this->input('pagina', 1), 50);

        $total = Inventory::countByUser($userId, $search);

        $this->render('admin/inventory_detail', [
            'owner'        => $user,
            'company'      => Company::findByUserId($userId) ?? [],
            'items'        => Inventory::getByUser($userId, $limit, $offset, $search),
            'total'        => $total,
            'totalGeral'   => Inventory::countByUser($userId),
            'batches'      => Inventory::getBatches($userId, 20),
            'removable'    => Inventory::countRemovableBatches($userId),
            'search'       => $search,
            'page'         => max(1, (int) $this->input('pagina', 1)),
            'pages'        => (int) ceil($total / $limit),
            'maxFileSize'  => 0,
        ]);
    }

    /**
     * Exclui uma peça do estoque de um fornecedor.
     */
    public function deleteItem(): void
    {
        $this->validateCsrf();

        $itemId = (int) $this->input('item_id', 0);
        $item = Inventory::deleteItem($itemId);

        if (!$item) {
            $this->redirect('admin/estoque', ['error' => 'Item não encontrado.']);
        }

        $this->registrar('admin_deleted_item', (int) $item['user_id'],
            "{$item['brand']} {$item['product']}");

        $this->redirect('admin/estoque/' . (int) $item['user_id'], [
            'success' => "Peça {$item['brand']} {$item['product']} removida do estoque de {$item['supplier_name']}.",
        ]);
    }

    /**
     * Ajusta a quantidade de uma peça de qualquer fornecedor.
     */
    public function updateItemQuantity(): void
    {
        $this->validateCsrf();

        $itemId = (int) $this->input('item_id', 0);
        $item = Inventory::findItem($itemId);

        if (!$item) {
            $this->redirect('admin/estoque', ['error' => 'Peça não encontrada.']);
        }

        $donoId = (int) $item['user_id'];
        $atualizado = Inventory::updateQuantity($itemId, $donoId, (int) $this->input('quantity', 0));

        $this->registrar('admin_updated_item', $donoId, sprintf(
            '%s %s: %d -> %d un',
            $atualizado['brand'],
            $atualizado['product'],
            (int) $atualizado['quantity_anterior'],
            (int) $atualizado['quantity']
        ));

        $this->redirect('admin/estoque/' . $donoId, [
            'success' => sprintf(
                '%s %s: %s → %s un.%s',
                $atualizado['brand'],
                $atualizado['product'],
                number_format((int) $atualizado['quantity_anterior'], 0, ',', '.'),
                number_format((int) $atualizado['quantity'], 0, ',', '.'),
                (int) $atualizado['quantity'] === 0 ? ' A peça saiu da busca pública.' : ''
            ),
        ]);
    }

    /**
     * Inclui uma peça avulsa no estoque de um fornecedor.
     */
    public function addItem(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/estoque', ['error' => 'Fornecedor não encontrado.']);
        }

        $resultado = Inventory::addItem(
            $userId,
            $this->text('brand', '', 100),
            $this->text('product', '', 255),
            (int) $this->input('quantity', 0)
        );

        if (!$resultado['success']) {
            $this->redirect('admin/estoque/' . $userId, ['error' => $resultado['message']]);
        }

        $this->registrar('admin_added_item', $userId, (string) $resultado['message']);

        $this->redirect('admin/estoque/' . $userId, ['success' => $resultado['message']]);
    }

    /**
     * Tira todo o estoque de um fornecedor do ar (a conta continua ativa).
     */
    public function clearStock(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/estoque', ['error' => 'Fornecedor não encontrado.']);
        }

        $removidos = Inventory::deleteAllByUser($userId);

        $this->registrar('admin_cleared_stock', $userId, "{$removidos} itens");

        $this->redirect('admin/estoque/' . $userId, [
            'success' => number_format($removidos, 0, ',', '.')
                . " peça(s) de {$user['name']} foram retiradas do ar. A conta continua ativa.",
        ]);
    }

    /**
     * Exclui uma carga de qualquer fornecedor, inclusive a que está no ar.
     */
    public function deleteBatch(): void
    {
        $this->validateCsrf();

        $batchId = (int) $this->input('batch_id', 0);
        $batch = Inventory::adminDeleteBatch($batchId);

        if (!$batch) {
            $this->redirect('admin/estoque', ['error' => 'Carga não encontrada.']);
        }

        $itens = (int) $batch['itens_vinculados'];
        $this->registrar('admin_deleted_batch', (int) $batch['user_id'],
            "{$batch['filename']} ({$itens} itens)");

        $this->redirect('admin/estoque/' . (int) $batch['user_id'], [
            'success' => $itens > 0
                ? "Carga \"{$batch['filename']}\" removida junto com {$itens} peça(s)."
                : "Registro da carga \"{$batch['filename']}\" removido do histórico.",
        ]);
    }

    /**
     * Limpa o histórico de cargas já substituídas de um fornecedor.
     */
    public function clearHistory(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $removidos = Inventory::deleteOldBatches($userId);

        $this->registrar('admin_cleared_batch_history', $userId, "{$removidos} cargas");

        $this->redirect('admin/estoque/' . $userId, [
            'success' => "{$removidos} carga(s) antiga(s) removida(s) do histórico.",
        ]);
    }

    /**
     * Envia uma planilha em nome do fornecedor — útil quando o cliente
     * não consegue subir sozinho e pede ajuda ao suporte.
     */
    public function upload(): void
    {
        $this->validateCsrf();

        $userId = (int) $this->input('user_id', 0);
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/estoque', ['error' => 'Fornecedor não encontrado.']);
        }

        $file = $_FILES['file'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->redirect('admin/estoque/' . $userId, ['error' => 'Selecione um arquivo válido para enviar.']);
        }

        $result = ExcelService::processUpload($userId, $file['tmp_name'], (string) $file['name']);

        if (!$result['success']) {
            $this->redirect('admin/estoque/' . $userId, ['error' => $result['message']]);
        }

        $this->registrar('admin_uploaded_stock', $userId, (string) $file['name']);

        $this->redirect('admin/estoque/' . $userId, [
            'success' => 'Enviado em nome de ' . $user['name'] . '. ' . $result['message'],
        ]);
    }

    /**
     * Exporta o estoque de um fornecedor em CSV.
     */
    public function export(string $id): void
    {
        $userId = (int) $id;
        $user = User::findById($userId);

        if (!$user) {
            $this->redirect('admin/estoque', ['error' => 'Fornecedor não encontrado.']);
        }

        $stmt = Inventory::streamByUser($userId);
        $nome = preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $user['name']);

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=estoque_{$nome}_" . date('Y-m-d') . '.csv');
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

    /**
     * Auditoria: registra a ação do administrador identificando o dono
     * do estoque que foi alterado.
     */
    private function registrar(string $acao, int $donoId, string $detalhe): void
    {
        $admin = Auth::user();
        $dono = User::findById($donoId);

        Auth::logAudit(
            (int) Auth::id(),
            sprintf(
                '%s -> %s | %s',
                $admin['email'] ?? 'admin',
                $dono['email'] ?? "usuario#{$donoId}",
                $detalhe
            ),
            $acao
        );
    }
}
