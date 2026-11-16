<?php

namespace App\Core;

use PDO;

/**
 * Aplicador de migrações.
 *
 * Guarda em uma tabela de controle tudo o que já rodou, então pode ser
 * executado quantas vezes quiser: só as migrações pendentes são aplicadas.
 *
 * As migrações também são escritas de forma idempotente (criam coluna só se
 * ela não existir, e assim por diante). Isso permite apontar o sistema para
 * um banco que já foi atualizado à mão: nada é duplicado, e o histórico
 * passa a ficar registrado a partir dali.
 */
class Migrator
{
    private PDO $pdo;
    private string $banco;
    private Esquema $esquema;

    /** @var array<int,string> */
    private array $mensagens = [];

    public function __construct(?PDO $pdo = null)
    {
        $config = require base_path('config/database.php');

        $this->pdo = $pdo ?? Database::getConnection();
        $this->banco = (string) $config['database'];
        $this->esquema = new Esquema($this->pdo, $this->banco);
    }

    public function banco(): string
    {
        return $this->banco;
    }

    /**
     * Cria a tabela que registra o histórico, se ainda não existir.
     */
    public function prepararControle(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `arquivo` VARCHAR(191) NOT NULL UNIQUE,
                `descricao` VARCHAR(255) DEFAULT NULL,
                `alteracoes` TEXT DEFAULT NULL,
                `executada_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `duracao_ms` INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<int,string> nomes dos arquivos já aplicados
     */
    public function jaAplicadas(): array
    {
        $this->prepararControle();

        return $this->pdo
            ->query("SELECT arquivo FROM migrations ORDER BY arquivo")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Todos os arquivos de migração presentes no projeto, em ordem.
     *
     * @return array<int,string>
     */
    public function disponiveis(): array
    {
        $pasta = base_path('database/migrations');

        if (!is_dir($pasta)) {
            return [];
        }

        $arquivos = array_map('basename', glob($pasta . '/*.php') ?: []);
        sort($arquivos, SORT_STRING);

        return $arquivos;
    }

    /**
     * @return array<int,string> migrações que ainda faltam
     */
    public function pendentes(): array
    {
        return array_values(array_diff($this->disponiveis(), $this->jaAplicadas()));
    }

    /**
     * Aplica tudo o que está pendente.
     *
     * @return array{aplicadas:array<int,array>, erro:?string}
     */
    public function executar(): array
    {
        $this->prepararControle();

        $aplicadas = [];

        foreach ($this->pendentes() as $arquivo) {
            $inicio = microtime(true);

            try {
                $migracao = require base_path('database/migrations/' . $arquivo);

                if (!is_array($migracao) || !isset($migracao['executar'])) {
                    throw new \RuntimeException("Arquivo de migração inválido: {$arquivo}");
                }

                $resultado = ($migracao['executar'])($this->pdo, $this->esquema);
                $alteracoes = array_values(array_filter((array) $resultado));

                $duracao = (int) round((microtime(true) - $inicio) * 1000);

                $stmt = $this->pdo->prepare(
                    "INSERT INTO migrations (arquivo, descricao, alteracoes, duracao_ms)
                     VALUES (:arquivo, :descricao, :alteracoes, :duracao)"
                );
                $stmt->execute([
                    'arquivo'    => $arquivo,
                    'descricao'  => $migracao['descricao'] ?? null,
                    'alteracoes' => $alteracoes ? implode("\n", $alteracoes) : 'nada a fazer (já estava aplicado)',
                    'duracao'    => $duracao,
                ]);

                $aplicadas[] = [
                    'arquivo'    => $arquivo,
                    'descricao'  => $migracao['descricao'] ?? '',
                    'alteracoes' => $alteracoes,
                    'duracao_ms' => $duracao,
                ];
            } catch (\Throwable $e) {
                // A migração que falhou não é registrada: ao corrigir o
                // problema e rodar de novo, ela será tentada outra vez.
                return [
                    'aplicadas' => $aplicadas,
                    'erro'      => "Falha em {$arquivo}: " . $e->getMessage(),
                ];
            }
        }

        return ['aplicadas' => $aplicadas, 'erro' => null];
    }

    /** Depois de uma falha, espera isto antes de tentar de novo sozinho */
    private const ESPERA_APOS_FALHA = 600;

    /**
     * Aplica as migrações pendentes sem ninguém precisar abrir o
     * atualizar.php: basta subir os arquivos e o primeiro acesso ao site
     * deixa o banco em dia.
     *
     * Custa quase nada nas outras visitas: compara a lista de arquivos de
     * migração com uma marca gravada em storage/cache. Só quando aparece
     * arquivo novo é que o banco é consultado.
     *
     * A marca leva o nome do banco junto. A pasta storage/ também vai pelo
     * FTP, e sem isso a marca do computador local diria ao servidor que o
     * banco dele já está em dia.
     *
     * Nunca derruba a página: qualquer falha vai para o log e a próxima
     * tentativa só acontece depois de ESPERA_APOS_FALHA segundos.
     */
    public static function aplicarPendentesSozinho(): void
    {
        try {
            $pasta = base_path('database/migrations');
            $arquivos = array_map('basename', glob($pasta . '/*.php') ?: []);
            sort($arquivos, SORT_STRING);

            $config = require base_path('config/database.php');
            $banco = ($config['host'] ?? '') . '|' . ($config['database'] ?? '');
            $assinatura = hash('sha256', $banco . '|' . implode('|', $arquivos));

            // Arquivos de controle com o nome do banco: os do computador
            // local, se subirem junto, nunca valem para o servidor.
            $cache = base_path('storage/cache');
            $chave = substr(hash('sha256', $banco), 0, 12);
            $marca = $cache . "/migracoes-{$chave}.ok";
            $falha = $cache . "/migracoes-{$chave}.falha";

            if (is_file($marca) && trim((string) @file_get_contents($marca)) === $assinatura) {
                return;
            }

            if (is_file($falha) && (time() - (int) @filemtime($falha)) < self::ESPERA_APOS_FALHA) {
                return;
            }

            if (!is_dir($cache) && !@mkdir($cache, 0755, true) && !is_dir($cache)) {
                error_log('[StoqueOnline] Atualização automática: não foi possível criar storage/cache');
                return;
            }

            // Duas visitas ao mesmo tempo não aplicam a mesma migração duas
            // vezes: quem não pega a trava segue a vida e deixa a outra rodar.
            $trava = @fopen($cache . '/migracoes.lock', 'c');
            if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
                return;
            }

            try {
                @set_time_limit(300);

                $resultado = (new self())->executar();

                if ($resultado['erro'] !== null) {
                    @touch($falha);
                    error_log('[StoqueOnline] Atualização automática do banco falhou: ' . $resultado['erro']);
                    return;
                }

                @file_put_contents($marca, $assinatura);
                @unlink($falha);

                foreach ($resultado['aplicadas'] as $aplicada) {
                    error_log('[StoqueOnline] Migração aplicada automaticamente: ' . $aplicada['arquivo']);
                }
            } finally {
                flock($trava, LOCK_UN);
                fclose($trava);
            }
        } catch (\Throwable $e) {
            if (isset($falha)) {
                @touch($falha);
            }
            error_log('[StoqueOnline] Atualização automática do banco falhou: ' . $e->getMessage());
        }
    }

    /**
     * Histórico do que já foi aplicado.
     */
    public function historico(int $limite = 50): array
    {
        $this->prepararControle();

        $limite = max(1, min($limite, 500));

        return $this->pdo
            ->query("SELECT * FROM migrations ORDER BY id DESC LIMIT {$limite}")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrato rápido do banco, para conferência depois da atualização.
     */
    public function resumoDoBanco(): array
    {
        $tabelas = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $resumo = [];

        foreach ($tabelas as $tabela) {
            if ($tabela === 'migrations') {
                continue;
            }

            $resumo[$tabela] = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM `{$tabela}`")
                ->fetchColumn();
        }

        return $resumo;
    }
}
