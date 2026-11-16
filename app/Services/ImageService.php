<?php

namespace App\Services;

/**
 * Tratamento das fotos de produto.
 *
 * Toda imagem enviada é reprocessada pelo GD antes de ser gravada. Além de
 * padronizar tamanho e peso, isso descarta qualquer conteúdo embutido no
 * arquivo original: o que vai para o disco é uma imagem nova, gerada aqui.
 */
class ImageService
{
    /** Maior lado da imagem exibida na ampliação */
    private const LADO_GRANDE = 1400;

    /** Maior lado da miniatura usada nas listagens */
    private const LADO_MINIATURA = 320;

    /** Peso máximo aceito no envio */
    private const MAX_BYTES = 12582912; // 12 MB

    /** Teto de pixels da imagem de origem (protege a memória do servidor) */
    private const MAX_PIXELS = 40000000; // 40 megapixels

    private const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Valida, redimensiona e grava a imagem.
     *
     * @return array{success:bool, message:string, dados:?array}
     */
    public static function processarUpload(array $arquivo, int $userId): array
    {
        $erro = self::validar($arquivo);
        if ($erro !== null) {
            return ['success' => false, 'message' => $erro, 'dados' => null];
        }

        $origem = self::carregar($arquivo['tmp_name']);
        if ($origem === null) {
            return [
                'success' => false,
                'message' => 'Não foi possível ler esta imagem. Envie um arquivo JPG, PNG ou WebP.',
                'dados'   => null,
            ];
        }

        // Fotos de celular costumam vir giradas por metadado
        $origem = self::corrigirRotacao($origem, $arquivo['tmp_name']);

        $pasta = self::pastaDoUsuario($userId);
        if ($pasta === null) {
            imagedestroy($origem);
            return [
                'success' => false,
                'message' => 'O servidor não conseguiu criar a pasta das imagens. Verifique as permissões de storage/uploads.',
                'dados'   => null,
            ];
        }

        $base = bin2hex(random_bytes(16));
        $nomeGrande = $base . '.webp';
        $nomeMini = $base . '_t.webp';

        $grande = self::redimensionar($origem, self::LADO_GRANDE);
        $mini = self::redimensionar($origem, self::LADO_MINIATURA);

        $largura = imagesx($grande);
        $altura = imagesy($grande);

        $okGrande = self::gravar($grande, $pasta . '/' . $nomeGrande, 82);
        $okMini = self::gravar($mini, $pasta . '/' . $nomeMini, 72);

        imagedestroy($origem);
        imagedestroy($grande);
        imagedestroy($mini);

        if (!$okGrande || !$okMini) {
            @unlink($pasta . '/' . $nomeGrande);
            @unlink($pasta . '/' . $nomeMini);

            return ['success' => false, 'message' => 'Falha ao gravar a imagem no servidor.', 'dados' => null];
        }

        return [
            'success' => true,
            'message' => 'Imagem processada.',
            'dados'   => [
                'filename'   => $nomeGrande,
                'thumbname'  => $nomeMini,
                'size_bytes' => (int) filesize($pasta . '/' . $nomeGrande),
                'width'      => $largura,
                'height'     => $altura,
            ],
        ];
    }

    /**
     * Apaga os dois arquivos de uma foto.
     */
    public static function apagar(int $userId, string $filename, string $thumbname): void
    {
        $pasta = self::caminhoBase() . '/' . $userId;

        foreach ([$filename, $thumbname] as $arquivo) {
            // Só nomes gerados aqui: bloqueia qualquer tentativa de subir de pasta
            if (preg_match('/^[a-f0-9]{32}(_t)?\.webp$/', $arquivo) && is_file($pasta . '/' . $arquivo)) {
                @unlink($pasta . '/' . $arquivo);
            }
        }
    }

    /**
     * Remove a pasta inteira de imagens de um fornecedor.
     *
     * A exclusão da conta apaga as linhas de product_photos em cascata, mas
     * os arquivos ficariam ocupando disco para sempre.
     *
     * @return int quantidade de arquivos removidos
     */
    public static function apagarPastaDoUsuario(int $userId): int
    {
        $pasta = self::caminhoBase() . '/' . $userId;

        if (!is_dir($pasta)) {
            return 0;
        }

        $removidos = 0;

        foreach (glob($pasta . '/*.webp') ?: [] as $arquivo) {
            if (@unlink($arquivo)) {
                $removidos++;
            }
        }

        // Só remove a pasta se ela tiver ficado vazia
        if (count((array) scandir($pasta)) <= 2) {
            @rmdir($pasta);
        }

        return $removidos;
    }

    /**
     * Arquivos que não têm mais registro no banco.
     *
     * @param array<int,string> $nomesNoBanco
     * @return int quantidade de arquivos removidos
     */
    public static function limparOrfaos(array $nomesNoBanco): int
    {
        $conhecidos = array_flip($nomesNoBanco);
        $removidos = 0;

        foreach (glob(self::caminhoBase() . '/*', GLOB_ONLYDIR) ?: [] as $pastaUsuario) {
            foreach (glob($pastaUsuario . '/*.webp') ?: [] as $arquivo) {
                $nome = basename($arquivo);

                if (!isset($conhecidos[$nome])) {
                    // Margem de segurança: não mexe em arquivo recém-criado,
                    // que pode estar no meio de um envio em andamento.
                    if ((time() - (int) filemtime($arquivo)) > 3600 && @unlink($arquivo)) {
                        $removidos++;
                    }
                }
            }
        }

        return $removidos;
    }

    /**
     * URL pública de uma imagem já gravada.
     */
    public static function url(int $userId, string $arquivo): string
    {
        return url('uploads/produtos/' . $userId . '/' . $arquivo);
    }

    public static function caminhoBase(): string
    {
        return base_path('public/uploads/produtos');
    }

    public static function limiteBytes(): int
    {
        return self::MAX_BYTES;
    }

    // -----------------------------------------------------------------

    private static function validar(array $arquivo): ?string
    {
        if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
            return match ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o tamanho permitido pelo servidor.',
                UPLOAD_ERR_NO_FILE => 'Selecione uma imagem.',
                UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tente novamente.',
                default => 'Não foi possível receber a imagem.',
            };
        }

        if (!is_uploaded_file($arquivo['tmp_name'])) {
            return 'Envio inválido.';
        }

        if ($arquivo['size'] > self::MAX_BYTES) {
            return 'A imagem tem ' . round($arquivo['size'] / 1048576, 1)
                . ' MB. O limite é ' . (int) (self::MAX_BYTES / 1048576) . ' MB.';
        }

        // O tipo é conferido pelo conteúdo do arquivo, não pela extensão
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tipo = (string) $finfo->file($arquivo['tmp_name']);

        if (!in_array($tipo, self::TIPOS_ACEITOS, true)) {
            return 'Formato não aceito. Envie JPG, PNG ou WebP.';
        }

        $info = @getimagesize($arquivo['tmp_name']);
        if ($info === false) {
            return 'O arquivo enviado não é uma imagem válida.';
        }

        if (($info[0] * $info[1]) > self::MAX_PIXELS) {
            return 'A imagem tem resolução alta demais. Reduza antes de enviar.';
        }

        if ($info[0] < 100 || $info[1] < 100) {
            return 'A imagem é pequena demais (mínimo 100x100 pixels).';
        }

        return null;
    }

    private static function carregar(string $caminho): ?\GdImage
    {
        $info = @getimagesize($caminho);
        if ($info === false) {
            return null;
        }

        $imagem = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($caminho),
            IMAGETYPE_PNG  => @imagecreatefrompng($caminho),
            IMAGETYPE_WEBP => @imagecreatefromwebp($caminho),
            default        => false,
        };

        return $imagem instanceof \GdImage ? $imagem : null;
    }

    /**
     * Aplica a orientação gravada no EXIF. Sem isso, foto tirada de lado
     * no celular aparece deitada no site.
     */
    private static function corrigirRotacao(\GdImage $imagem, string $caminho): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data($caminho);
        $orientacao = (int) ($exif['Orientation'] ?? 0);

        $girada = match ($orientacao) {
            3 => imagerotate($imagem, 180, 0),
            6 => imagerotate($imagem, -90, 0),
            8 => imagerotate($imagem, 90, 0),
            default => null,
        };

        if ($girada instanceof \GdImage) {
            imagedestroy($imagem);
            return $girada;
        }

        return $imagem;
    }

    private static function redimensionar(\GdImage $origem, int $ladoMaximo): \GdImage
    {
        $largura = imagesx($origem);
        $altura = imagesy($origem);
        $maior = max($largura, $altura);

        // Imagem menor que o alvo não é ampliada
        $escala = $maior > $ladoMaximo ? $ladoMaximo / $maior : 1;

        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $destino = imagecreatetruecolor($novaLargura, $novaAltura);

        // Fundo branco: peça fotografada em PNG com transparência não vira preto
        $branco = imagecolorallocate($destino, 255, 255, 255);
        imagefilledrectangle($destino, 0, 0, $novaLargura, $novaAltura, $branco);

        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        return $destino;
    }

    private static function gravar(\GdImage $imagem, string $caminho, int $qualidade): bool
    {
        if (function_exists('imagewebp')) {
            return @imagewebp($imagem, $caminho, $qualidade);
        }

        // Servidor sem WebP: grava JPEG com o mesmo nome
        return @imagejpeg($imagem, $caminho, $qualidade);
    }

    private static function pastaDoUsuario(int $userId): ?string
    {
        $pasta = self::caminhoBase() . '/' . $userId;

        if (!is_dir($pasta) && !@mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            return null;
        }

        // Impede que qualquer arquivo dentro de uploads seja executado
        $protecao = self::caminhoBase() . '/.htaccess';
        if (!is_file($protecao)) {
            @file_put_contents($protecao, implode("\n", [
                '# Somente imagens: nada aqui dentro pode ser executado',
                'php_flag engine off',
                'AddType text/plain .php .phtml .php5 .php7 .phar .cgi .pl',
                '<FilesMatch "\.(php|phtml|php5|php7|phar|cgi|pl|sh)$">',
                '    <IfModule mod_authz_core.c>',
                '        Require all denied',
                '    </IfModule>',
                '</FilesMatch>',
                'Options -Indexes -ExecCGI',
            ]));
        }

        return $pasta;
    }
}
