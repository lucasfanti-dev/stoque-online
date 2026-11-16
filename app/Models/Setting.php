<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configurações do site, editáveis pelo administrador.
 *
 * Tudo é lido de uma vez na primeira consulta e guardado em memória: as telas
 * pedem meia dúzia de chaves por requisição (rodapé, barra de topo, página de
 * contato) e não faz sentido ir ao banco a cada uma.
 *
 * Toda leitura tolera falha de banco e devolve o padrão. A página de erro 500
 * também mostra telefone e e-mail, e ela costuma aparecer justamente quando o
 * banco caiu — pedir socorro numa tela que não carrega não ajudaria ninguém.
 */
class Setting
{
    /** Valores de fábrica, usados enquanto a chave não existir no banco. */
    public const PADROES = [
        'contato_email'        => 'daniel_amorimtj@hotmail.com',
        'contato_telefone'     => '(11) 99527-0932',
        'contato_whatsapp'     => '5511995270932',
        'contato_endereco'     => 'Rua General Osório, 299 Sala 1 - Santa Ifigênia - São Paulo/SP',
        'contato_horario'      => 'Seg - Sex: 08:00 às 18:00',
    ];

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,string>
     */
    public static function todos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $valores = self::PADROES;

        try {
            foreach (Database::fetchAll('SELECT `chave`, `valor` FROM `settings`') as $linha) {
                $chave = (string) $linha['chave'];
                if ($linha['valor'] !== null && $linha['valor'] !== '') {
                    $valores[$chave] = (string) $linha['valor'];
                }
            }
        } catch (\Throwable) {
            // Banco fora do ar ou migração ainda não rodada: seguem os padrões.
        }

        return self::$cache = $valores;
    }

    public static function obter(string $chave, string $padrao = ''): string
    {
        $todos = self::todos();
        return $todos[$chave] ?? (self::PADROES[$chave] ?? $padrao);
    }

    /**
     * Grava as chaves conhecidas. Chave desconhecida é ignorada de propósito:
     * o formulário do painel não deve conseguir criar configuração nova.
     *
     * @param array<string,string> $valores
     */
    public static function salvar(array $valores): int
    {
        $gravadas = 0;

        foreach ($valores as $chave => $valor) {
            if (!array_key_exists($chave, self::PADROES)) {
                continue;
            }

            Database::query(
                'INSERT INTO `settings` (`chave`, `valor`) VALUES (:chave, :valor)
                 ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)',
                ['chave' => $chave, 'valor' => (string) $valor]
            );

            $gravadas++;
        }

        self::$cache = null;

        return $gravadas;
    }

    /**
     * Só os dígitos, no formato que o wa.me espera, com 55 na frente quando
     * o administrador digitar apenas DDD + número.
     */
    public static function whatsappNumero(): string
    {
        $digitos = preg_replace('/\D+/', '', self::obter('contato_whatsapp'));

        if ($digitos === '') {
            return '';
        }

        return str_starts_with($digitos, '55') ? $digitos : '55' . $digitos;
    }

    public static function whatsappLink(): string
    {
        $numero = self::whatsappNumero();
        return $numero === '' ? '' : 'https://wa.me/' . $numero;
    }

    /**
     * Link de telefone. Usa o número do WhatsApp, que é o que está em dígitos.
     */
    public static function telefoneLink(): string
    {
        $numero = self::whatsappNumero();
        return $numero === '' ? '' : 'tel:+' . $numero;
    }

    /** Limpa o cache — usado pelos testes e depois de gravar. */
    public static function limparCache(): void
    {
        self::$cache = null;
    }
}
