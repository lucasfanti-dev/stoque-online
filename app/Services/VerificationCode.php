<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;

/**
 * Códigos de 6 dígitos para confirmar cadastro e recuperar senha.
 *
 * Por que código e não link: o link chega no e-mail, e quem se cadastra pelo
 * celular costuma abrir a caixa postal em outro aplicativo — voltava para o
 * navegador e a sessão era outra. O código a pessoa lê e digita na tela onde
 * já está.
 *
 * O preço disso é que seis dígitos são só um milhão de combinações. Por isso:
 *
 *  - o código é guardado em sha256, nunca em claro (quem ler o banco não
 *    consegue entrar na conta de ninguém);
 *  - a comparação é feita com hash_equals, que não vaza tempo;
 *  - cinco erros seguidos derrubam o código, e é preciso pedir outro;
 *  - o código vale 15 minutos, não 48 horas como o link antigo.
 */
class VerificationCode
{
    /** Minutos de validade do código. */
    public const VALIDADE_MINUTOS = 15;

    /** Erros seguidos antes de o código ser descartado. */
    public const MAX_TENTATIVAS = 5;

    /** Segundos de espera entre um envio e o próximo, por conta. */
    public const ESPERA_REENVIO = 60;

    /** Confirmação de cadastro. */
    public const ATIVACAO = 'ativacao';

    /** Recuperação de senha. */
    public const RECUPERACAO = 'recuperacao';

    /**
     * Nome das colunas de cada finalidade. Manter num só lugar evita que o
     * código de ativação acabe gravado na coluna da recuperação.
     *
     * @return array{codigo:string, expira:string, tentativas:string}
     */
    private static function colunas(string $finalidade): array
    {
        return $finalidade === self::RECUPERACAO
            ? ['codigo' => 'password_reset_token', 'expira' => 'password_reset_expires', 'tentativas' => 'password_reset_attempts']
            : ['codigo' => 'token', 'expira' => 'token_expires_at', 'tentativas' => 'token_attempts'];
    }

    /**
     * Seis dígitos sorteados com gerador criptográfico. Vai de 000000 a
     * 999999 — inclusive com zeros à esquerda, que o padding preserva.
     */
    public static function gerar(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function hash(string $codigo): string
    {
        return hash('sha256', $codigo);
    }

    /**
     * Grava um código novo para a conta e devolve o código em claro, que só
     * existe nesse instante — daqui ele vai direto para o e-mail.
     */
    public static function emitir(int $userId, string $finalidade): string
    {
        $c = self::colunas($finalidade);
        $codigo = self::gerar();

        User::update($userId, [
            $c['codigo']     => self::hash($codigo),
            $c['expira']     => date('Y-m-d H:i:s', time() + self::VALIDADE_MINUTOS * 60),
            $c['tentativas'] => 0,
        ]);

        return $codigo;
    }

    /**
     * Quantos segundos ainda faltam para a conta poder pedir outro código.
     * Zero quer dizer que já pode.
     */
    public static function esperaParaReenvio(array $user, string $finalidade): int
    {
        $c = self::colunas($finalidade);

        if (empty($user[$c['expira']])) {
            return 0;
        }

        $emitidoEm = strtotime((string) $user[$c['expira']]) - self::VALIDADE_MINUTOS * 60;
        $faltam    = self::ESPERA_REENVIO - (time() - $emitidoEm);

        return max(0, $faltam);
    }

    /**
     * Confere o código digitado.
     *
     * @return array{ok:bool, motivo:string, restantes:int}
     *         motivo: 'ok' | 'sem_codigo' | 'expirado' | 'errado' | 'bloqueado'
     */
    public static function conferir(array $user, string $finalidade, string $digitado): array
    {
        $c = self::colunas($finalidade);
        $digitado = preg_replace('/\D+/', '', $digitado) ?? '';

        if (empty($user[$c['codigo']]) || empty($user[$c['expira']])) {
            return ['ok' => false, 'motivo' => 'sem_codigo', 'restantes' => 0];
        }

        if (strtotime((string) $user[$c['expira']]) < time()) {
            self::descartar((int) $user['id'], $finalidade);
            return ['ok' => false, 'motivo' => 'expirado', 'restantes' => 0];
        }

        if ((int) ($user[$c['tentativas']] ?? 0) >= self::MAX_TENTATIVAS) {
            self::descartar((int) $user['id'], $finalidade);
            return ['ok' => false, 'motivo' => 'bloqueado', 'restantes' => 0];
        }

        if ($digitado === '' || !hash_equals((string) $user[$c['codigo']], self::hash($digitado))) {
            $tentativas = (int) ($user[$c['tentativas']] ?? 0) + 1;

            if ($tentativas >= self::MAX_TENTATIVAS) {
                self::descartar((int) $user['id'], $finalidade);
                return ['ok' => false, 'motivo' => 'bloqueado', 'restantes' => 0];
            }

            Database::query(
                "UPDATE users SET `{$c['tentativas']}` = :n WHERE id = :id",
                ['n' => $tentativas, 'id' => (int) $user['id']]
            );

            return ['ok' => false, 'motivo' => 'errado', 'restantes' => self::MAX_TENTATIVAS - $tentativas];
        }

        return ['ok' => true, 'motivo' => 'ok', 'restantes' => self::MAX_TENTATIVAS];
    }

    /**
     * Apaga o código. Chamado tanto quando ele é usado quanto quando queima
     * as tentativas — nos dois casos ele não serve mais.
     */
    public static function descartar(int $userId, string $finalidade): void
    {
        $c = self::colunas($finalidade);

        User::update($userId, [
            $c['codigo']     => null,
            $c['expira']     => null,
            $c['tentativas'] => 0,
        ]);
    }

    /**
     * Código formatado para leitura: "123 456" cansa menos o olho do que
     * "123456" na hora de copiar do e-mail.
     */
    public static function paraLeitura(string $codigo): string
    {
        return substr($codigo, 0, 3) . ' ' . substr($codigo, 3);
    }
}
