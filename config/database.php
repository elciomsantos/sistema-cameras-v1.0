<?php

declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

// Load interface before class definition (loaded via Composer files, before PSR-4 classmap)
$interfacePath = __DIR__ . '/../src/Database/DatabaseInterface.php';
if (file_exists($interfacePath)) {
    require_once $interfacePath;
}

/**
 * database — Singleton PDO wrapper.
 *
 * NOTA: Classe intencionalmente sem namespace (global) porque:
 * 1. Carregada via "files" no composer.json (não PSR-4), fora de src/
 * 2. A função global db() (definida abaixo) precisa referenciá-la sem namespace
 * 3. Refatorar exigiria alterar 100+ arquivos que chamam db() / database::*
 * 4. O autoload PSR-4 (App\ → src/) é para classes em src/ e não conflita
 *
 * @see db()  Helper global para acesso à instância única
 */
class database implements \App\Database\DatabaseInterface
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private bool $inTransaction = false;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_ATTEMPTS = 1;

    final public function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $port = getenv('DB_PORT') ?: '3306';
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_PERSISTENT => false,
            ];

            $sslCa = getenv('DB_SSL_CA');
            if ($sslCa !== false && $sslCa !== '') {
                $options[\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            $this->pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
            $this->reconnectAttempts = 0;
        } catch (\PDOException $err) {
            logError('Erro de conexao', ['error' => $err->getMessage()], 'database');
            throw new \RuntimeException('Erro de conexao com o banco de dados.');
        }
    }

    private function ensureConnection(): void
    {
        $status = $this->pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
        if ($status !== false && strpos($status, 'on ') === 0) {
            return;
        }
        if ($this->reconnectAttempts < self::MAX_RECONNECT_ATTEMPTS && !$this->inTransaction) {
            $this->reconnectAttempts++;
            logWarning('Connection lost, attempting reconnect', ['attempt' => $this->reconnectAttempts], 'database');
            $this->connect();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    public function setUserContext(?int $userId, ?string $origem = 'web'): void
    {
        $this->query("SET @app_user_id = ?", [$userId]);
        $this->query("SET @app_origem = ?", [$origem]);
    }

    public function query($sql, $params = []): array
    {
        $this->ensureConnection();

        try {
            $stmt = $this->pdo->prepare($sql);

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = PDO::PARAM_STR;
                    
                    if (is_int($value)) {
                        $paramType = PDO::PARAM_INT;
                    } elseif (is_null($value)) {
                        $paramType = PDO::PARAM_NULL;
                    }
                    
                    if (is_int($key)) {
                        $stmt->bindValue($key + 1, $value, $paramType);
                    } else {
                        $stmt->bindValue($key, $value, $paramType);
                    }
                }
            }
            
            $stmt->execute();
            
            $trimmedSql = ltrim($sql);
            $isSelect = (stripos($trimmedSql, 'SELECT') === 0)
                || (stripos($trimmedSql, 'WITH') === 0);

            if ($isSelect) {
                $results = $stmt->fetchAll();
                return [
                    'status' => 'success',
                    'data' => $results,
                    'count' => count($results)
                ];
            } else {
                return [
                    'status' => 'success',
                    'affected_rows' => $stmt->rowCount()
                ];
            }

        } catch (\PDOException $err) {
            if ($this->isConnectionLostError($err) && !$this->inTransaction) {
                try {
                    $this->connect();
                    return $this->query($sql, $params);
                } catch (\Throwable $retryErr) {
                    logError('Database error after reconnect', ['error' => $retryErr->getMessage()], 'database');
                    throw new \RuntimeException('Erro interno no banco de dados.');
                }
            }
            logError('Database error', ['error' => $err->getMessage()], 'database');
            if ($this->isDuplicateError($err)) {
                throw new \App\Database\DuplicateEntryException($err->getMessage(), 0, $err);
            }
            throw new \RuntimeException('Erro interno no banco de dados.');
        } catch (\Throwable $err) {
            if ($this->isDuplicateError($err)) {
                throw new \App\Database\DuplicateEntryException($err->getMessage(), 0, $err);
            }
            logError('Database error', ['error' => $err->getMessage()], 'database');
            throw new \RuntimeException('Erro interno no banco de dados.');
        }
    }

    /**
     * Detecta violacao de UNIQUE ou SIGNAL de trigger anti-duplicidade.
     * - SQLSTATE '23000' = integrity constraint violation (UNIQUE/PK).
     * - SQLSTATE '45000' = SIGNAL custom (usado pelas triggers trg_locais_bi/bu).
     * - errno 1062 = Duplicate entry (legacy).
     * - Mensagem 'Ja existe um local...' = SIGNAL do trigger de locais.
     */
    private function isDuplicateError(\Throwable $e): bool
    {
        $code = (string)$e->getCode();
        $msg = $e->getMessage();
        return $code === '23000'
            || $code === '45000'
            || str_contains($msg, 'Duplicate entry')
            || str_contains($msg, '1062')
            || str_contains($msg, 'Ja existe um local');
    }

    private function isConnectionLostError(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return stripos($msg, 'server has gone away') !== false
            || stripos($msg, 'no connection to the server') !== false
            || stripos($msg, 'Lost connection') !== false
            || stripos($msg, 'is not connected') !== false
            || $e->getCode() === 'HY000'
            || $e->getCode() === '08S01';
    }
    
    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }
    
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function beginTransaction(): bool
    {
        try {
            $result = $this->pdo->beginTransaction();
            $this->inTransaction = true;
            return $result;
        } catch (\Throwable $e) {
            $this->inTransaction = $this->pdo->inTransaction();
            throw $e;
        }
    }
    
    public function commit(): bool
    {
        $this->inTransaction = false;
        return $this->pdo->commit();
    }
    
    public function rollback(): bool
    {
        $this->inTransaction = false;
        return $this->pdo->rollBack();
    }
    
    // Metodo auxiliar para INSERT simples
    public function insert($table, $data): array
    {
        // Whitelist de tabelas permitidas para evitar SQL injection
        $tabelasPermitidas = [
            'usuarios', 'user_sessions', 'login_attempts', 'auditoria_eventos',
            'equipamentos', 'equipamentos_camera', 'equipamentos_lpr',
            'equipamentos_dvr', 'equipamentos_totem', 'equipamentos_manutencoes',
            'equipamentos_status_historico', 'central_alarmes', 'alarmes_manutencoes',
            'secretarias', 'locais', 'catalogo_modelos', 'marcas',
            'configuracoes', 'sequencia_codigo_publico', 'equipamentos_anexos',
        ];
        if (!in_array($table, $tabelasPermitidas, true)) {
            throw new \InvalidArgumentException("Tabela nao permitida: $table");
        }
        $columns = array_keys($data);
        foreach ($columns as $col) {
            if (!is_string($col) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                throw new \InvalidArgumentException("Nome de coluna invalido: $col");
            }
        }
        $columnsSql = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);
        
        $sql = "INSERT INTO `$table` ($columnsSql) VALUES ($placeholders)";
        return $this->query($sql, $data);
    }

    /**
     * Retorna um Generator que yielda rows uma a uma — útil para exports grandes
     * sem carregar tudo em memória. Usa PDO::FETCH_NUM para menor overhead.
     *
     * @return \Generator<int, object>
     */
    public function queryCursor(string $sql, array $params = []): \Generator
    {
        $this->ensureConnection();

        try {
            $stmt = $this->pdo->prepare($sql);

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = \PDO::PARAM_STR;
                    if (is_int($value)) {
                        $paramType = \PDO::PARAM_INT;
                    } elseif (is_null($value)) {
                        $paramType = \PDO::PARAM_NULL;
                    }
                    if (is_int($key)) {
                        $stmt->bindValue($key + 1, $value, $paramType);
                    } else {
                        $stmt->bindValue($key, $value, $paramType);
                    }
                }
            }

            $stmt->execute();
            $stmt->setFetchMode(\PDO::FETCH_OBJ);

            while ($row = $stmt->fetch()) {
                yield $row;
            }

            $stmt->closeCursor();
        } catch (\PDOException $err) {
            if ($this->isConnectionLostError($err) && !$this->inTransaction) {
                try {
                    $this->connect();
                    yield from $this->queryCursor($sql, $params);
                    return;
                } catch (\Throwable $retryErr) {
                    logError('Database error after reconnect', ['error' => $retryErr->getMessage()], 'database');
                    throw new \RuntimeException('Erro interno no banco de dados.');
                }
            }
            logError('Database error', ['error' => $err->getMessage()], 'database');
            throw new \RuntimeException('Erro interno no banco de dados.');
        }
    }
}

/**
 * Helper global para obter instancia unica do banco por requisicao.
 * Uso: $db = db();
 */
function db(): database
{
    return database::getInstance();
}


