<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Interface para operações de banco de dados.
 * Permite testing com mocks e substituição de implementação.
 *
 * NOTA: Assinaturas usam mixed/sem tipos para compatibilidade com a classe
 * global `database` que não declara tipos nos parâmetros.
 */
interface DatabaseInterface
{
    /**
     * Executa uma query preparada.
     *
     * @param string $sql   SQL com placeholders (? ou :name)
     * @param array  $params Parâmetros para binding
     * @return array{status: string, data?: array, count?: int, affected_rows?: int}
     */
    public function query($sql, $params = []): array;

    /**
     * Cursor generator para streaming de grandes resultados.
     *
     * @return \Generator<int, object>
     */
    public function queryCursor(string $sql, array $params = []): \Generator;

    /**
     * Retorna o último ID inserido.
     */
    public function lastInsertId(): string|false;

    /**
     * Insere registros em uma tabela.
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @return array{status: string, affected_rows?: int}
     */
    public function insert($table, $data): array;

    // Transaction methods
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    public function inTransaction(): bool;

    // Connection context
    public function setUserContext(?int $userId, ?string $origem = 'web'): void;

    /**
     * Retorna a conexão PDO bruta (quando necessário para operações avançadas).
     */
    public function getConnection(): \PDO;
}
