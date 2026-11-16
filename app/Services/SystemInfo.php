<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Migrator;
use App\Core\Session;
use App\Models\PaymentGateway;
use App\Services\Gateways\Gateways;

/**
 * Retrato técnico da instalação.
 *
 * Reúne o que costuma ser preciso quando algo não funciona e a resposta é
 * "aqui na minha máquina roda": versão do PHP, extensões, tamanho do banco,
 * espaço em disco, migrações pendentes, permissões de escrita e os erros mais
 * recentes. Cada leitura tolera falha, porque esta é exatamente a tela que
 * precisa abrir quando alguma coisa está quebrada.
 */
class SystemInfo
{
    /** Extensões sem as quais alguma parte do sistema para de funcionar. */
    private const EXTENSOES = [
        'pdo_mysql' => 'banco de dados',
        'curl'      => 'pagamentos e envio de e-mail',
        'gd'        => 'fotos dos produtos',
        'mbstring'  => 'acentuação',
        'openssl'   => 'HTTPS e SMTP',
        'zip'       => 'leitura de planilhas .xlsx',
        'json'      => 'comunicação com as APIs',
        'fileinfo'  => 'conferência do tipo de arquivo enviado',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function php(): array
    {
        $extensoes = [];
        foreach (self::EXTENSOES as $ext => $paraQue) {
            $extensoes[$ext] = ['carregada' => extension_loaded($ext), 'para' => $paraQue];
        }

        return [
            'versao'            => PHP_VERSION,
            'versao_ok'         => version_compare(PHP_VERSION, '8.1', '>='),
            'sapi'              => PHP_SAPI,
            'memory_limit'      => ini_get('memory_limit'),
            'upload_max'        => ini_get('upload_max_filesize'),
            'post_max'          => ini_get('post_max_size'),
            'max_execution'     => ini_get('max_execution_time'),
            'max_input_vars'    => ini_get('max_input_vars'),
            'fuso'              => date_default_timezone_get(),
            'hora'              => date('d/m/Y H:i:s'),
            'opcache'           => function_exists('opcache_get_status') && @opcache_get_status() !== false,
            'extensoes'         => $extensoes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function banco(): array
    {
        try {
            $pdo = Database::getConnection();
            $versao = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            $tabelas = Database::fetchAll(
                "SELECT TABLE_NAME AS nome,
                        TABLE_ROWS AS linhas,
                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS mb
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                  ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC"
            );

            $totalMb = 0.0;
            foreach ($tabelas as $t) {
                $totalMb += (float) $t['mb'];
            }

            return [
                'conectado' => true,
                'versao'    => $versao,
                'nome'      => (string) env('DB_NAME', ''),
                'host'      => (string) env('DB_HOST', ''),
                'tabelas'   => $tabelas,
                'qtd'       => count($tabelas),
                'total_mb'  => round($totalMb, 2),
                'erro'      => null,
            ];
        } catch (\Throwable $e) {
            return [
                'conectado' => false,
                'versao'    => null,
                'nome'      => (string) env('DB_NAME', ''),
                'host'      => (string) env('DB_HOST', ''),
                'tabelas'   => [],
                'qtd'       => 0,
                'total_mb'  => 0.0,
                'erro'      => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function migracoes(): array
    {
        try {
            $migrator = new Migrator(Database::getConnection());
            $migrator->prepararControle();

            return [
                'ok'        => true,
                'aplicadas' => count($migrator->jaAplicadas()),
                'pendentes' => $migrator->pendentes(),
                'historico' => $migrator->historico(8),
                'erro'      => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'aplicadas' => 0, 'pendentes' => [], 'historico' => [], 'erro' => $e->getMessage()];
        }
    }

    /**
     * Pastas que o sistema precisa escrever e quanto já ocupam.
     *
     * @return array<int,array{caminho:string, existe:bool, gravavel:bool, tamanho:int, arquivos:int}>
     */
    public static function pastas(): array
    {
        $alvos = [
            'storage/logs'            => 'registros de erro',
            'storage/backups'         => 'cópias do .env e do banco',
            'public/uploads/produtos' => 'fotos enviadas pelos fornecedores',
        ];

        $saida = [];
        foreach ($alvos as $relativo => $paraQue) {
            $abs = base_path($relativo);
            [$tamanho, $arquivos] = self::medirPasta($abs);

            $saida[] = [
                'caminho'  => $relativo,
                'para'     => $paraQue,
                'existe'   => is_dir($abs),
                'gravavel' => is_dir($abs) && is_writable($abs),
                'tamanho'  => $tamanho,
                'arquivos' => $arquivos,
            ];
        }

        return $saida;
    }

    /**
     * @return array<string,mixed>
     */
    public static function disco(): array
    {
        $raiz = base_path();

        $livre = @disk_free_space($raiz);
        $total = @disk_total_space($raiz);

        return [
            'livre' => is_float($livre) ? (int) $livre : null,
            'total' => is_float($total) ? (int) $total : null,
            'usado' => (is_float($livre) && is_float($total)) ? (int) ($total - $livre) : null,
        ];
    }

    /**
     * Conferências que costumam ser a causa do problema.
     *
     * @return array<int,array{grupo:string, item:string, ok:bool, alerta:bool, detalhe:string}>
     */
    public static function conferencias(): array
    {
        $itens = [];
        $add = function (string $grupo, string $item, bool $ok, string $detalhe, bool $alerta = false) use (&$itens) {
            $itens[] = compact('grupo', 'item', 'ok', 'alerta', 'detalhe');
        };

        // --- Segurança ---
        $debug = env('APP_DEBUG', false) === true;
        $producao = (string) env('APP_ENV', '') === 'production';

        $add(
            'Segurança',
            'APP_DEBUG desligado',
            !$debug,
            $debug ? 'está ligado — mensagens internas de erro aparecem para o visitante' : 'desligado',
            $debug && !$producao
        );

        $add('Segurança', 'APP_ENV em produção', $producao, (string) env('APP_ENV', 'não definido'), !$producao);

        $https = Session::isHttps();
        $add('Segurança', 'HTTPS ativo', $https, $https ? 'sim' : 'o site responde em HTTP — o cookie de sessão viaja sem criptografia');

        $add(
            'Segurança',
            '.env fora do alcance da web',
            self::envBloqueado(),
            self::envBloqueado()
                ? 'o servidor recusa o acesso direto ao arquivo'
                : 'CONFIRA AGORA: o arquivo pode estar acessível pelo navegador'
        );

        $diag = is_file(base_path('public/diagnostico.php'));
        $add(
            'Segurança',
            'Página de diagnóstico removida',
            !$diag,
            $diag ? 'public/diagnostico.php ainda existe — esta tela já faz o mesmo, pode apagar' : 'removida',
            $diag
        );

        // --- E-mail ---
        $temSmtp = trim((string) env('MAIL_HOST', '')) !== '';
        $add('E-mail', 'SMTP configurado', $temSmtp, $temSmtp ? (string) env('MAIL_HOST') : 'sem MAIL_HOST: nenhum código de cadastro sai');

        $de = trim((string) env('MAIL_FROM_ADDRESS', ''));
        $usuario = trim((string) env('MAIL_USER', ''));
        $add(
            'E-mail',
            'Remetente igual à conta autenticada',
            $usuario !== '' && strcasecmp($de, $usuario) === 0,
            $usuario === '' ? 'MAIL_USER vazio' : ($de === $usuario ? $de : "MAIL_FROM_ADDRESS ({$de}) difere de MAIL_USER ({$usuario}) — cai em spam")
        );

        // --- Pagamento ---
        $disponiveis = Gateways::disponiveis();
        $add(
            'Pagamento',
            'Meio de pagamento disponível',
            $disponiveis !== [],
            $disponiveis === [] ? 'nenhum ligado: ninguém consegue contratar plano' : implode(', ', array_map(
                fn($g) => $g->nome(),
                $disponiveis
            ))
        );

        foreach (Gateways::todos() as $slug => $gateway) {
            if (!PaymentGateway::estaAtivo($slug)) {
                continue;
            }

            $add(
                'Pagamento',
                $gateway->nome() . ' com credenciais',
                $gateway->estaConfigurado(),
                $gateway->estaConfigurado()
                    ? 'ambiente: ' . PaymentGateway::modo($slug)
                    : 'ligado sem credencial — não aparece no checkout'
            );
        }

        return $itens;
    }

    /**
     * Últimas linhas dos registros de erro.
     *
     * @return array<string,array{existe:bool, tamanho:int, linhas:string[]}>
     */
    public static function logs(int $quantas = 12): array
    {
        $arquivos = [
            'mail-errors.log' => 'Falhas de envio de e-mail',
            'pagamentos.log'  => 'Comunicação com os meios de pagamento',
        ];

        $saida = [];
        foreach ($arquivos as $arquivo => $titulo) {
            $caminho = base_path('storage/logs/' . $arquivo);

            if (!is_file($caminho)) {
                $saida[$arquivo] = ['titulo' => $titulo, 'existe' => false, 'tamanho' => 0, 'linhas' => []];
                continue;
            }

            $linhas = @file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            $saida[$arquivo] = [
                'titulo'  => $titulo,
                'existe'  => true,
                'tamanho' => (int) filesize($caminho),
                'linhas'  => array_slice($linhas, -$quantas),
            ];
        }

        return $saida;
    }

    public static function formatarBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $valor = (float) $bytes;

        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return number_format($valor, $i === 0 ? 0 : 1, ',', '.') . ' ' . $unidades[$i];
    }

    /**
     * O .htaccess bloqueia o .env? Conferimos lendo a regra, sem fazer a
     * requisição: a tela precisa abrir mesmo sem acesso à rede de saída.
     */
    private static function envBloqueado(): bool
    {
        foreach ([base_path('.htaccess'), base_path('public/.htaccess')] as $arquivo) {
            if (!is_file($arquivo)) {
                continue;
            }

            $conteudo = (string) @file_get_contents($arquivo);

            if (preg_match('/\.env|FilesMatch.*env/i', $conteudo) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:int, 1:int} tamanho em bytes e quantidade de arquivos
     */
    private static function medirPasta(string $dir): array
    {
        if (!is_dir($dir)) {
            return [0, 0];
        }

        $tamanho = 0;
        $arquivos = 0;

        try {
            $iterador = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterador as $arquivo) {
                if ($arquivo->isFile()) {
                    $tamanho += $arquivo->getSize();
                    $arquivos++;
                }
            }
        } catch (\Throwable) {
            // Pasta ilegível: devolvemos o que já foi contado
        }

        return [$tamanho, $arquivos];
    }
}
