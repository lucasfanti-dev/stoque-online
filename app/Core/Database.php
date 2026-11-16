<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require base_path('config/database.php');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            } catch (PDOException $e) {
                // A credencial jamais pode vazar para a tela do visitante
                error_log('[StoqueOnline] Falha de conexão com o banco: ' . $e->getMessage());

                throw new \RuntimeException(
                    app_debug()
                        ? 'Erro de conexão com o banco de dados: ' . $e->getMessage()
                        : 'Não foi possível conectar ao banco de dados.'
                );
            }
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);

        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }

        $stmt->execute();

        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    /**
     * Converte um número de página em LIMIT/OFFSET seguros.
     * Devolve inteiros já validados, prontos para interpolação.
     *
     * @return array{0:int,1:int} [limit, offset]
     */
    public static function paginate(int $page, int $perPage = 50, int $maxPerPage = 200): array
    {
        $perPage = max(1, min($perPage, $maxPerPage));
        $page = max(1, $page);

        return [$perPage, ($page - 1) * $perPage];
    }

    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_map(fn($col) => ":$col", array_keys($data)));

        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        self::query($sql, $data);

        return (int) self::getConnection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $paramKey = 'set_' . $column;
            $setClauses[] = "`$column` = :$paramKey";
            $params[$paramKey] = $value;
        }

        $params = array_merge($params, $whereParams);
        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses) . " WHERE $where";

        return self::query($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public static function beginTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? true : $pdo->beginTransaction();
    }

    public static function commit(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->commit() : false;
    }

    public static function rollBack(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->rollBack() : false;
    }

    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }
}
