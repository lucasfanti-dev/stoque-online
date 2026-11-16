<?php

namespace App\Services;

/**
 * Leitura e gravação do arquivo .env.
 *
 * Este é o arquivo mais sensível do sistema: dentro dele estão a senha do
 * banco, a senha do e-mail e as chaves de acesso. Três cuidados guiam o que
 * está escrito aqui.
 *
 *  1. Comentários e ordem são preservados. O .env é documentação também: ele
 *     explica o que cada chave faz. Reescrever o arquivo inteiro a cada
 *     gravação apagaria isso.
 *
 *  2. Toda gravação faz cópia antes. Um erro de digitação na senha do banco
 *     derruba o site inteiro, e sem cópia não haveria como voltar pela web —
 *     justamente porque o site estaria fora.
 *
 *  3. Mudança nos dados de banco é testada antes de valer. Abrimos uma
 *     conexão nova com os valores novos; só se ela funcionar o arquivo é
 *     gravado. Isso é o que impede o administrador de se trancar para fora.
 */
class EnvFile
{
    /** Chaves cujo valor nunca aparece na tela sem confirmação de senha. */
    private const PADROES_SECRETOS = ['PASS', 'PASSWORD', 'SECRET', 'TOKEN', 'KEY', 'CREDENTIAL'];

    /**
     * Chaves que a tela não deixa editar.
     *
     * APP_URL fica de fora porque um valor errado aqui quebra todos os links
     * do sistema, inclusive o caminho de volta para esta tela.
     */
    private const SOMENTE_LEITURA = [];

    /**
     * O arquivo que está valendo nesta máquina: .env.producao no servidor,
     * .env no computador local (ver env_file_path() em config/app.php).
     */
    public static function caminho(): string
    {
        return env_file_path();
    }

    /** Nome do arquivo, para a tela dizer qual está sendo editado. */
    public static function nome(): string
    {
        return basename(self::caminho());
    }

    public static function existe(): bool
    {
        return is_file(self::caminho());
    }

    public static function gravavel(): bool
    {
        return self::existe() && is_writable(self::caminho());
    }

    /**
     * Lê o arquivo preservando a estrutura.
     *
     * @return array<int, array{tipo:string, chave:?string, valor:?string, texto:string}>
     *         tipo: 'chave' | 'comentario' | 'vazia'
     */
    public static function linhas(): array
    {
        if (!self::existe()) {
            return [];
        }

        $saida = [];

        foreach (file(self::caminho(), FILE_IGNORE_NEW_LINES) as $linha) {
            $limpa = trim($linha);

            if ($limpa === '') {
                $saida[] = ['tipo' => 'vazia', 'chave' => null, 'valor' => null, 'texto' => $linha];
                continue;
            }

            if (str_starts_with($limpa, '#')) {
                $saida[] = ['tipo' => 'comentario', 'chave' => null, 'valor' => null, 'texto' => $linha];
                continue;
            }

            if (!str_contains($limpa, '=')) {
                $saida[] = ['tipo' => 'comentario', 'chave' => null, 'valor' => null, 'texto' => $linha];
                continue;
            }

            [$chave, $valor] = explode('=', $limpa, 2);

            $saida[] = [
                'tipo'  => 'chave',
                'chave' => trim($chave),
                'valor' => self::semAspas(trim($valor)),
                'texto' => $linha,
            ];
        }

        return $saida;
    }

    /**
     * @return array<string,string>
     */
    public static function valores(): array
    {
        $saida = [];

        foreach (self::linhas() as $linha) {
            if ($linha['tipo'] === 'chave') {
                $saida[(string) $linha['chave']] = (string) $linha['valor'];
            }
        }

        return $saida;
    }

    public static function ehSecreta(string $chave): bool
    {
        foreach (self::PADROES_SECRETOS as $padrao) {
            if (str_contains(strtoupper($chave), $padrao)) {
                return true;
            }
        }

        return false;
    }

    public static function ehSomenteLeitura(string $chave): bool
    {
        return in_array(strtoupper($chave), self::SOMENTE_LEITURA, true);
    }

    /**
     * Valor escondido: só o tamanho e o fim, o suficiente para conferir se é
     * o valor certo sem mostrar o segredo.
     */
    public static function mascarar(string $valor): string
    {
        if ($valor === '') {
            return '(vazio)';
        }

        return strlen($valor) <= 6
            ? str_repeat('•', strlen($valor))
            : str_repeat('•', 10) . substr($valor, -4) . '  (' . strlen($valor) . ' caracteres)';
    }

    /**
     * Grava as alterações.
     *
     * @param array<string,string> $alteracoes chave => valor novo
     * @return array{ok:bool, erro:?string, alteradas:string[], copia:?string}
     */
    public static function salvar(array $alteracoes): array
    {
        if (!self::existe()) {
            return ['ok' => false, 'erro' => 'O arquivo ' . self::nome() . ' não foi encontrado no servidor.', 'alteradas' => [], 'copia' => null];
        }

        if (!self::gravavel()) {
            return ['ok' => false, 'erro' => 'O arquivo ' . self::nome() . ' existe mas não tem permissão de escrita. Ajuste para 644 no gerenciador de arquivos.', 'alteradas' => [], 'copia' => null];
        }

        $atuais = self::valores();
        $efetivas = [];

        foreach ($alteracoes as $chave => $valor) {
            if (self::ehSomenteLeitura($chave)) {
                continue;
            }

            // Campo em branco quer dizer "não mexi nele"
            if ($valor === '') {
                continue;
            }

            if (($atuais[$chave] ?? null) === $valor) {
                continue;
            }

            $efetivas[$chave] = $valor;
        }

        if ($efetivas === []) {
            return ['ok' => true, 'erro' => null, 'alteradas' => [], 'copia' => null];
        }

        // Mexeu no banco? Testa antes de gravar. Este é o passo que impede
        // o administrador de derrubar o próprio site com um erro de digitação.
        $testeBanco = self::testarBanco(array_merge($atuais, $efetivas));
        if (!$testeBanco['ok']) {
            return [
                'ok'        => false,
                'erro'      => 'Os dados novos do banco não funcionaram, então nada foi gravado: ' . $testeBanco['erro'],
                'alteradas' => [],
                'copia'     => null,
            ];
        }

        $copia = self::copiarAntes();

        $novasLinhas = [];
        $aplicadas = [];

        foreach (self::linhas() as $linha) {
            if ($linha['tipo'] !== 'chave' || !isset($efetivas[(string) $linha['chave']])) {
                $novasLinhas[] = $linha['texto'];
                continue;
            }

            $chave = (string) $linha['chave'];
            $comAspas = preg_match('/=\s*["\']/', $linha['texto']) === 1;
            $novasLinhas[] = $chave . '=' . self::comAspasSePreciso($efetivas[$chave], $comAspas);
            $aplicadas[] = $chave;
        }

        // Chave que ainda não existia entra no fim, com um cabeçalho
        $novas = array_diff(array_keys($efetivas), $aplicadas);
        if ($novas !== []) {
            $novasLinhas[] = '';
            $novasLinhas[] = '# Acrescentadas pela tela de Sistema em ' . date('d/m/Y H:i');
            foreach ($novas as $chave) {
                $novasLinhas[] = $chave . '=' . self::comAspasSePreciso($efetivas[$chave]);
                $aplicadas[] = $chave;
            }
        }

        $quebra = self::quebraDeLinha();
        $conteudo = implode($quebra, $novasLinhas) . $quebra;

        if (@file_put_contents(self::caminho(), $conteudo, LOCK_EX) === false) {
            return ['ok' => false, 'erro' => 'Não foi possível escrever no ' . self::nome() . '.', 'alteradas' => [], 'copia' => $copia];
        }

        return ['ok' => true, 'erro' => null, 'alteradas' => $aplicadas, 'copia' => $copia];
    }

    /**
     * Abre uma conexão nova com os valores informados.
     *
     * @param array<string,string> $valores
     * @return array{ok:bool, erro:?string}
     */
    public static function testarBanco(array $valores): array
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $valores['DB_HOST'] ?? '127.0.0.1',
            $valores['DB_PORT'] ?? '3306',
            $valores['DB_NAME'] ?? ''
        );

        try {
            new \PDO($dsn, $valores['DB_USER'] ?? '', $valores['DB_PASS'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            return ['ok' => true, 'erro' => null];
        } catch (\Throwable $e) {
            // A mensagem do driver pode conter o usuário; a senha nunca aparece
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Cópia com data e hora, para dar como desfazer.
     * As cópias ficam em storage/, que o .htaccess já bloqueia para a web.
     */
    private static function copiarAntes(): ?string
    {
        $dir = base_path('storage/backups/env');

        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return null;
        }

        $destino = $dir . '/env-' . date('Y-m-d_His') . '.bak';

        if (!@copy(self::caminho(), $destino)) {
            return null;
        }

        self::podarCopias($dir);

        return basename($destino);
    }

    /** Mantém as 20 cópias mais recentes. */
    private static function podarCopias(string $dir): void
    {
        $arquivos = glob($dir . '/env-*.bak') ?: [];

        if (count($arquivos) <= 20) {
            return;
        }

        sort($arquivos);
        foreach (array_slice($arquivos, 0, count($arquivos) - 20) as $velho) {
            @unlink($velho);
        }
    }

    /**
     * @return array<int, array{arquivo:string, data:string, tamanho:int}>
     */
    public static function copias(): array
    {
        $dir = base_path('storage/backups/env');
        $arquivos = glob($dir . '/env-*.bak') ?: [];
        rsort($arquivos);

        $saida = [];
        foreach (array_slice($arquivos, 0, 10) as $arquivo) {
            $saida[] = [
                'arquivo'  => basename($arquivo),
                'data'     => date('d/m/Y H:i:s', (int) filemtime($arquivo)),
                'tamanho'  => (int) filesize($arquivo),
            ];
        }

        return $saida;
    }

    private static function semAspas(string $valor): string
    {
        if (strlen($valor) >= 2
            && ((str_starts_with($valor, '"') && str_ends_with($valor, '"'))
                || (str_starts_with($valor, "'") && str_ends_with($valor, "'")))) {
            return substr($valor, 1, -1);
        }

        return $valor;
    }

    /**
     * Valor com espaço ou # precisa de aspas para o leitor não se perder.
     * $jaTinhaAspas mantém o estilo da linha original, para a gravação não
     * mexer em linha que ninguém pediu para mexer.
     */
    private static function comAspasSePreciso(string $valor, bool $jaTinhaAspas = false): string
    {
        if ($valor === '') {
            return '';
        }

        $precisa = preg_match('/[\s#"\']/', $valor) === 1;

        return ($precisa || $jaTinhaAspas)
            ? '"' . str_replace('"', '\\"', $valor) . '"'
            : $valor;
    }

    /**
     * A quebra de linha que o arquivo já usa.
     *
     * PHP_EOL devolve a do sistema onde o PHP roda, não a do arquivo: num
     * servidor Windows isso reescrevia em CRLF um arquivo que estava em LF,
     * e a gravação de uma chave só aparecia como mudança no arquivo inteiro.
     */
    private static function quebraDeLinha(): string
    {
        $conteudo = (string) @file_get_contents(self::caminho());

        if (str_contains($conteudo, "\r\n")) {
            return "\r\n";
        }

        return "\n";
    }
}
