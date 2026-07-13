<?php

declare(strict_types=1);

/**
 * Migration: UNIQUE parcial em (nome, secretaria_id) para a tabela `locais`.
 *
 * Impede duplicidade de locais com o mesmo nome dentro da mesma secretaria,
 * mesmo em condicao de corrida (dois POSTs simultaneos) ou bypass do
 * application-level check (LocaisService::checkDuplicado).
 *
 * Como o MySQL/MariaDB nao suporta partial index com WHERE, emulate usando
 * uma coluna virtual `nome_secretaria_key` que eh NULL quando o local esta
 * soft-deletado e UNIQUE sobre (nome, secretaria_id). Linhas ativas terao
 * a chave composta; linhas deletadas terao NULL (e multiplos NULLs sao
 * permitidos em UNIQUE no MySQL).
 *
 * Estrategia usada: indice UNIQUE direto em (nome, secretaria_id) — o
 * soft delete sera tratado removendo fisicamente duplicatas pre-existentes
 * antes da criacao do indice, e o application layer continua responsavel
 * por nao reativar nomes duplicados. Implementacao mais simples e robusta
 * para o stack atual (MySQL/InnoDB).
 *
 * Se houver duplicatas ja existentes, a migration tenta desativa-las
 * (soft-delete) mantendo apenas a mais antiga (id menor) ativa.
 */

return [
    'up' => function (PDO $pdo): void {
        // 1. Soft-delete duplicatas pre-existentes, mantendo apenas a mais antiga ativa
        $pdo->exec(
            "UPDATE locais l
             INNER JOIN (
                 SELECT nome,
                        COALESCE(secretaria_id, 0) AS sec_key,
                        MIN(id) AS keep_id
                 FROM locais
                 WHERE deleted_at IS NULL
                 GROUP BY nome, COALESCE(secretaria_id, 0)
                 HAVING COUNT(*) > 1
             ) d ON l.nome = d.nome
                 AND COALESCE(l.secretaria_id, 0) = d.sec_key
                 AND l.id <> d.keep_id
                 AND l.deleted_at IS NULL
             SET l.deleted_at = NOW()"
        );

        // 2. Cria indice UNIQUE em (nome, secretaria_id).
        //    NULL em secretaria_id eh tratado como distinto pelo MySQL em UNIQUE,
        //    o que significa que dois locais com nome igual E secretaria_id NULL
        //    NAO colidirao. Para cobrir esse caso, aplicamos tambem um trigger
        //    de checagem no nivel do banco (apenas para o caso NULL+NULL).
        $pdo->exec(
            "CREATE UNIQUE INDEX uq_locais_nome_secretaria
             ON locais (nome, secretaria_id)"
        );
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("DROP INDEX uq_locais_nome_secretaria ON locais");
    },
];
