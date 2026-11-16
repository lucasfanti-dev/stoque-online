<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configuração dos meios de pagamento, editável pela tela do admin.
 *
 * As credenciais ficam aqui e não no .env porque trocar um token passa a ser
 * um formulário, não uma nova subida de arquivos. Elas nunca são devolvidas
 * inteiras para a tela: o formulário mostra máscara e só grava o que for
 * digitado de novo.
 */
class PaymentGateway
{
    public const MODO_PRODUCAO = 'producao';
    public const MODO_SANDBOX  = 'sandbox';

    /** @var array<string,array>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,array{slug:string, ativo:int, modo:string, credenciais:array, ordem:int}>
     */
    public static function todos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $linhas = [];

        try {
            $linhas = Database::fetchAll('SELECT * FROM payment_gateways ORDER BY ordem ASC, slug ASC');
        } catch (\Throwable) {
            // Migração ainda não rodou: o sistema segue sem meio de pagamento
            // em vez de derrubar a página de planos.
        }

        $saida = [];
        foreach ($linhas as $linha) {
            $credenciais = json_decode((string) ($linha['credenciais'] ?? ''), true);

            $saida[(string) $linha['slug']] = [
                'slug'        => (string) $linha['slug'],
                'ativo'       => (int) ($linha['ativo'] ?? 0),
                'modo'        => (string) ($linha['modo'] ?? self::MODO_PRODUCAO),
                'credenciais' => is_array($credenciais) ? $credenciais : [],
                'ordem'       => (int) ($linha['ordem'] ?? 0),
            ];
        }

        return self::$cache = $saida;
    }

    public static function obter(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }

    public static function estaAtivo(string $slug): bool
    {
        return (int) (self::obter($slug)['ativo'] ?? 0) === 1;
    }

    public static function modo(string $slug): string
    {
        return (string) (self::obter($slug)['modo'] ?? self::MODO_PRODUCAO);
    }

    /**
     * @return array<string,string>
     */
    public static function credenciais(string $slug): array
    {
        return (array) (self::obter($slug)['credenciais'] ?? []);
    }

    /**
     * Grava a configuração de um meio de pagamento.
     *
     * Credencial vazia no formulário quer dizer "não mexi neste campo": o
     * valor que já estava gravado é mantido. Para apagar de verdade existe
     * o parâmetro $limpar.
     *
     * @param array<string,string> $credenciais
     * @param string[]             $limpar      Chaves a zerar
     */
    public static function salvar(string $slug, bool $ativo, string $modo, array $credenciais, array $limpar = []): void
    {
        $atuais = self::credenciais($slug);

        foreach ($credenciais as $chave => $valor) {
            $valor = trim((string) $valor);
            if ($valor !== '') {
                $atuais[$chave] = $valor;
            }
        }

        foreach ($limpar as $chave) {
            unset($atuais[$chave]);
        }

        $modo = $modo === self::MODO_SANDBOX ? self::MODO_SANDBOX : self::MODO_PRODUCAO;

        Database::query(
            'INSERT INTO payment_gateways (slug, ativo, modo, credenciais)
             VALUES (:slug, :ativo, :modo, :cred)
             ON DUPLICATE KEY UPDATE ativo = VALUES(ativo), modo = VALUES(modo), credenciais = VALUES(credenciais)',
            [
                'slug'  => $slug,
                'ativo' => $ativo ? 1 : 0,
                'modo'  => $modo,
                'cred'  => json_encode($atuais, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        self::$cache = null;
    }

    /**
     * Mostra só o fim da credencial, o suficiente para conferir se é a certa
     * sem devolver o segredo para a tela.
     */
    public static function mascarar(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        return strlen($valor) <= 6
            ? str_repeat('•', strlen($valor))
            : str_repeat('•', 12) . substr($valor, -4);
    }

    public static function limparCache(): void
    {
        self::$cache = null;
    }
}
