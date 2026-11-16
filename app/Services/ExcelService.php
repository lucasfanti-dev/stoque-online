<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Inventory;
use ZipArchive;
use XMLReader;

class ExcelService
{
    /** Limite de linhas por importação (protege memória e banco) */
    private const MAX_ROWS = 300000;

    /** Tamanho máximo aceito do arquivo enviado */
    private const MAX_FILE_BYTES = 33554432; // 32 MB

    /**
     * Processa a importação de planilhas (.xlsx ou .csv).
     *
     * @return array{success:bool, message:string, total_items?:int}
     */
    public static function processUpload(int $userId, string $tmpFilePath, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // .xls é o formato binário antigo do Excel (BIFF) e não é um ZIP:
        // avisar é melhor do que devolver "nenhum item encontrado".
        if ($ext === 'xls') {
            return self::error(
                'O formato .xls (Excel 97-2003) não é suportado. Abra a planilha no Excel e use '
                . '"Salvar como" escolhendo "Pasta de Trabalho do Excel (*.xlsx)" ou "CSV".'
            );
        }

        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            return self::error('Formato não suportado. Envie um arquivo .xlsx ou .csv.');
        }

        if (!is_file($tmpFilePath) || !is_readable($tmpFilePath)) {
            return self::error('Não foi possível ler o arquivo enviado. Tente novamente.');
        }

        if (filesize($tmpFilePath) > self::MAX_FILE_BYTES) {
            return self::error('O arquivo excede o limite de 32 MB. Divida a planilha em partes menores.');
        }

        $items = ($ext === 'csv')
            ? self::parseCsv($tmpFilePath)
            : self::parseXlsx($tmpFilePath);

        if ($items === null) {
            return self::error(
                'Não foi possível ler a planilha. Verifique se o arquivo não está corrompido ou protegido por senha.'
            );
        }

        if (empty($items)) {
            return self::error(
                'Nenhum item válido encontrado. Confira se a planilha segue o modelo: '
                . 'coluna 1 = Marca, coluna 2 = Produto, coluna 3 = Quantidade.'
            );
        }

        $totalItems = count($items);
        $truncated = false;

        if ($totalItems > self::MAX_ROWS) {
            $items = array_slice($items, 0, self::MAX_ROWS);
            $totalItems = self::MAX_ROWS;
            $truncated = true;
        }

        Database::beginTransaction();

        try {
            // A troca do estoque é atômica: ou entra a planilha inteira,
            // ou o estoque anterior permanece intacto.
            Inventory::deleteUserInventory($userId);
            Inventory::closePreviousBatches($userId);

            $batchId = Inventory::createBatch($userId, $originalName, $totalItems);

            foreach (array_chunk($items, 1000) as $chunk) {
                Inventory::insertBatch($userId, $batchId, $chunk);
            }

            Database::commit();

            $message = "Estoque atualizado com sucesso! {$totalItems} itens importados.";
            if ($truncated) {
                $message .= ' Atenção: o arquivo excedia ' . number_format(self::MAX_ROWS, 0, ',', '.')
                    . ' linhas e o excedente foi ignorado.';
            }

            return [
                'success'     => true,
                'total_items' => $totalItems,
                'message'     => $message,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            error_log('[StoqueOnline] Falha na importação de estoque: ' . $e->getMessage());

            return self::error(
                app_debug()
                    ? 'Erro ao salvar estoque: ' . $e->getMessage()
                    : 'Erro ao salvar o estoque. Seu estoque anterior foi mantido. Tente novamente.'
            );
        }
    }

    /**
     * @return array<int,array{brand:string,product:string,quantity:int}>|null
     */
    private static function parseCsv(string $filePath): ?array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return null;
        }

        $firstLine = (string) fgets($handle);

        // Remove o BOM do UTF-8 gerado pelo Excel
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

        $delimiter = self::detectDelimiter($firstLine);
        rewind($handle);

        $items = [];
        $isFirstRow = true;

        // Comprimento 0 = sem limite de bytes por linha (o limite fixo de 1000
        // truncava linhas longas e corrompia a última coluna).
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($isFirstRow) {
                $isFirstRow = false;
                // Só descarta a primeira linha quando ela realmente é cabeçalho
                if (self::looksLikeHeader($row)) {
                    continue;
                }
            }

            $item = self::normalizeRow(
                (string) ($row[0] ?? ''),
                (string) ($row[1] ?? ''),
                (string) ($row[2] ?? '')
            );

            if ($item) {
                $items[] = $item;
            }
        }

        fclose($handle);

        return $items;
    }

    /**
     * @return array<int,array{brand:string,product:string,quantity:int}>|null
     */
    private static function parseXlsx(string $filePath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetPath = self::resolveFirstSheetPath($zip);

        $sheetXml = $sheetPath !== null ? $zip->getFromName($sheetPath) : false;
        if ($sheetXml === false) {
            $zip->close();
            return null;
        }

        $items = [];
        $reader = new XMLReader();

        if (!$reader->XML($sheetXml)) {
            $zip->close();
            return null;
        }

        $currentRow = [];
        $isFirstRow = true;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $currentRow = [];
                if ($reader->isEmptyElement) {
                    continue;
                }
                continue;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $type = $reader->getAttribute('t');
                $colIndex = self::columnIndex((string) $reader->getAttribute('r'));

                $value = self::readCellValue($reader, $type, $sharedStrings);
                $currentRow[$colIndex] = $value;
                continue;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row') {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    if (self::looksLikeHeader($currentRow)) {
                        continue;
                    }
                }

                $item = self::normalizeRow(
                    (string) ($currentRow[0] ?? ''),
                    (string) ($currentRow[1] ?? ''),
                    (string) ($currentRow[2] ?? '')
                );

                if ($item) {
                    $items[] = $item;
                }

                if (count($items) > self::MAX_ROWS) {
                    break;
                }
            }
        }

        $reader->close();
        $zip->close();

        return $items;
    }

    /**
     * Lê o valor da célula tratando também strings inline (t="inlineStr"),
     * geradas por vários exportadores de ERP.
     */
    private static function readCellValue(XMLReader $reader, ?string $type, array $sharedStrings): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $value = '';
        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'c' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'v') {
                $value = (string) $reader->readString();
                continue;
            }

            // <is><t>texto</t></is>
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'is') {
                $value = trim(strip_tags((string) $reader->readInnerXml()));
                continue;
            }
        }

        if ($type === 's' && $value !== '') {
            $index = (int) $value;
            $value = $sharedStrings[$index] ?? '';
        }

        return trim($value);
    }

    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlContent === false) {
            return [];
        }

        $strings = [];
        $reader = new XMLReader();

        if (!$reader->XML($xmlContent)) {
            return [];
        }

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                $inner = (string) $reader->readInnerXml();

                // Concatena todos os <t> do item (texto com formatação mista)
                if (preg_match_all('#<t[^>]*>(.*?)</t>#s', $inner, $matches)) {
                    $strings[] = html_entity_decode(
                        implode('', $matches[1]),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8'
                    );
                } else {
                    $strings[] = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        $reader->close();

        return $strings;
    }

    /**
     * Descobre a primeira planilha real do arquivo em vez de assumir sheet1.xml
     * (o nome interno varia conforme o programa que gerou o arquivo).
     */
    private static function resolveFirstSheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            if (preg_match('#<sheet[^>]*r:id="([^"]+)"#', $workbook, $sheetMatch)) {
                $relId = $sheetMatch[1];

                if (preg_match('#<Relationship[^>]*Id="' . preg_quote($relId, '#') . '"[^>]*Target="([^"]+)"#', $rels, $relMatch)) {
                    $target = ltrim($relMatch[1], '/');
                    $target = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;

                    if ($zip->locateName($target) !== false) {
                        return $target;
                    }
                }
            }
        }

        // Caminhos mais comuns como alternativa
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                return $candidate;
            }
        }

        // Último recurso: a primeira worksheet que existir no pacote
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Converte a referência da célula ("A1", "B2", "AA10") no índice da
     * coluna começando em zero.
     */
    private static function columnIndex(string $cellRef): int
    {
        if (!preg_match('/([A-Z]+)/i', $cellRef, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return max(0, $index - 1);
    }

    private static function detectDelimiter(string $line): string
    {
        $candidates = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($candidates);

        $best = array_key_first($candidates);
        return $candidates[$best] > 0 ? (string) $best : ';';
    }

    private static function looksLikeHeader(array $row): bool
    {
        $joined = mb_strtolower(trim(implode(' ', array_map('strval', $row))));
        if ($joined === '') {
            return false;
        }

        foreach (['marca', 'produto', 'quantidade', 'brand', 'product', 'quantity', 'qtde', 'qtd', 'codigo', 'código'] as $keyword) {
            if (str_contains($joined, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{brand:string,product:string,quantity:int}|null
     */
    private static function normalizeRow(string $brand, string $product, string $quantity): ?array
    {
        $brand = trim(preg_replace('/\s+/u', ' ', $brand) ?? '');
        $product = trim(preg_replace('/\s+/u', ' ', $product) ?? '');

        if ($product === '') {
            return null;
        }

        // Quantidades exportadas como "1.234,00", "1,234.00" ou "25,5":
        // descarta a parte decimal e depois os separadores de milhar.
        $raw = preg_replace('/[^0-9.,]/', '', $quantity) ?? '';
        $withoutDecimals = preg_replace('/[.,]\d{1,2}$/', '', $raw) ?? $raw;
        $digits = preg_replace('/[^0-9]/', '', $withoutDecimals) ?? '';
        $parsed = (int) ($digits !== '' ? $digits : 0);

        return [
            'brand'    => mb_substr($brand !== '' ? $brand : 'GENÉRICA', 0, 100),
            'product'  => mb_substr($product, 0, 255),
            // Zero é respeitado: a peça entra no catálogo como esgotada em vez
            // de ser anunciada com 1 unidade que não existe.
            'quantity' => max(0, $parsed),
        ];
    }

    private static function error(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
