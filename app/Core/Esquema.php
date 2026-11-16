<?php

namespace App\Core;

use PDO;

/**
 * Ferramentas para alterar a estrutura do banco sem risco de repetição.
 *
 * Toda operação confere antes se já foi feita, então rodar a mesma migração
 * duas vezes não causa erro nem estrago. Cada método devolve uma frase
 * descrevendo o que aconteceu, para o relatório da atualização.
 */
class Esquema
{
    public function __construct(
        private PDO $pdo,
        private string $banco
    ) {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // -----------------------------------------------------------------
    // Verificações
    // -----------------------------------------------------------------

    public function temTabela(string $tabela): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t"
        );
        $stmt->execute(['db' => $this->banco, 't' => $tabela]);

        return (bool) $stmt->fetchColumn();
    }

    public function temColuna(string $tabela, string $coluna): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c"
        );
        $stmt->execute(['db' => $this->banco, 't' => $tabela, 'c' => $coluna]);

        return (bool) $stmt->fetchColumn();
    }

    public function temIndice(string $tabela, string $indice): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND INDEX_NAME = :i"
        );
        $stmt->execute(['db' => $this->banco, 't' => $tabela, 'i' => $indice]);

        return (bool) $stmt->fetchColumn();
    }

    public function tabelaVazia(string $tabela): bool
    {
        if (!$this->temTabela($tabela)) {
            return false;
        }

        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn() === 0;
    }

    // -----------------------------------------------------------------
    // Alterações
    // -----------------------------------------------------------------

    /**
     * Cria a tabela apenas se ela ainda não existir.
     */
    public function tabela(string $nome, string $definicao): ?string
    {
        if ($this->temTabela($nome)) {
            return null;
        }

        $this->pdo->exec("CREATE TABLE `{$nome}` ({$definicao}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return "tabela {$nome} criada";
    }

    public function coluna(string $tabela, string $coluna, string $definicao): ?string
    {
        if (!$this->temTabela($tabela) || $this->temColuna($tabela, $coluna)) {
            return null;
        }

        $this->pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");

        return "coluna {$tabela}.{$coluna} criada";
    }

    public function indice(string $tabela, string $indice, string $colunas): ?string
    {
        if (!$this->temTabela($tabela) || $this->temIndice($tabela, $indice)) {
            return null;
        }

        $this->pdo->exec("ALTER TABLE `{$tabela}` ADD INDEX `{$indice}` {$colunas}");

        return "índice {$tabela}.{$indice} criado";
    }

    /**
     * Chave estrangeira, criada só quando ainda não existe.
     */
    public function chaveEstrangeira(string $tabela, string $nome, string $definicao): ?string
    {
        if (!$this->temTabela($tabela)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c"
        );
        $stmt->execute(['db' => $this->banco, 't' => $tabela, 'c' => $nome]);

        if ($stmt->fetchColumn()) {
            return null;
        }

        $this->pdo->exec("ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$nome}` {$definicao}");

        return "chave estrangeira {$tabela}.{$nome} criada";
    }

    /**
     * Executa um comando livre e descreve quantas linhas foram afetadas.
     */
    public function executar(string $sql, array $params = [], string $descricao = ''): ?string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->rowCount();

        if ($linhas === 0 && $descricao === '') {
            return null;
        }

        return $descricao !== ''
            ? "{$descricao}: {$linhas} linha(s)"
            : "{$linhas} linha(s) alterada(s)";
    }
}
