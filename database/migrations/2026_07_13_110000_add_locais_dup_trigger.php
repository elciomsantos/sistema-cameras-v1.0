<?php

declare(strict_types=1);

/**
 * Migration: Triggers BEFORE INSERT/UPDATE em `locais` para anti-duplicidade.
 *
 * Complementa a migration 2026_07_13_100000_add_locais_unique.php, que criou
 * o indice UNIQUE (nome, secretaria_id). O MySQL trata cada NULL como distinto
 * em indices UNIQUE, logo o caso secretaria_id NULL+NULL nao era coberto pelo
 * indice — permitindo dois locais ativos com o mesmo nome e sem secretaria.
 *
 * As triggers abaixo validam duplicidade usando o operador <=> (NULL-safe),
 * cobrindo tambem o caso NULL+NULL. Protegem contra:
 *  - race condition (duplo submit: dois POSTs simultaneos passam no checkDuplicado);
 *  - bypass da aplicacao (import direto, scripts, outros clientes).
 *
 * Ignoram linhas soft-deletadas (deleted_at IS NULL). Em UPDATE so valida
 * quando nome ou secretaria_id mudam, evitando custo desnecessario.
 *
 * Em caso de duplicata, dispara SIGNAL SQLSTATE '45000' com mensagem clara,
 * que a aplicacao deve capturar (PDOException com SQLSTATE '45000' ou 1644).
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_locais_bi");
        $pdo->exec("
            CREATE TRIGGER trg_locais_bi
            BEFORE INSERT ON locais
            FOR EACH ROW
            BEGIN
                DECLARE dup_count INT DEFAULT 0;
                SELECT COUNT(*) INTO dup_count
                FROM locais
                WHERE nome = NEW.nome
                  AND (secretaria_id <=> NEW.secretaria_id)
                  AND deleted_at IS NULL;
                IF dup_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Ja existe um local ativo com este nome nesta secretaria.';
                END IF;
            END
        ");

        $pdo->exec("DROP TRIGGER IF EXISTS trg_locais_bu");
        $pdo->exec("
            CREATE TRIGGER trg_locais_bu
            BEFORE UPDATE ON locais
            FOR EACH ROW
            BEGIN
                DECLARE dup_count INT DEFAULT 0;
                IF NEW.nome <> OLD.nome OR NOT (NEW.secretaria_id <=> OLD.secretaria_id) THEN
                    SELECT COUNT(*) INTO dup_count
                    FROM locais
                    WHERE nome = NEW.nome
                      AND (secretaria_id <=> NEW.secretaria_id)
                      AND deleted_at IS NULL
                      AND id <> OLD.id;
                    IF dup_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Ja existe um local ativo com este nome nesta secretaria.';
                    END IF;
                END IF;
            END
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_locais_bu");
        $pdo->exec("DROP TRIGGER IF EXISTS trg_locais_bi");
    },
];
