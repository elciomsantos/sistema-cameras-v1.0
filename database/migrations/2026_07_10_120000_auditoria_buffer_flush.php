<?php

declare(strict_types=1);

/**
 * Migration: Auditoria buffer + EVENT flush.
 *
 * Cria tabela buffer leve, stored procedure de flush, EVENT periódico,
 * e recria triggers para INSERT no buffer (INSERT leve → flush em batch).
 */
return [
    'up' => function (PDO $pdo): void {
        // Criar tabela buffer (sem colunas GENERATED, sem FKs)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS auditoria_buffer (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entidade VARCHAR(80) NOT NULL,
                entidade_id BIGINT UNSIGNED NOT NULL,
                operacao_id TINYINT NOT NULL,
                dados_antes JSON NULL,
                dados_depois JSON NULL,
                origem VARCHAR(100) NULL,
                changed_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_buffer_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Buffer para inserts de auditoria — flush periódico'
        ");

        // Stored procedure de flush
        $pdo->exec("DROP PROCEDURE IF EXISTS sp_flush_auditoria_buffer");
        $pdo->exec("
            CREATE PROCEDURE sp_flush_auditoria_buffer()
            BEGIN
                DECLARE batch_size INT DEFAULT 500;
                DECLARE rows_moved INT DEFAULT 1;

                WHILE rows_moved > 0 DO
                    INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem, created_at)
                    SELECT entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem, created_at
                    FROM auditoria_buffer
                    ORDER BY id ASC
                    LIMIT batch_size;

                    SET rows_moved = ROW_COUNT();

                    DELETE b FROM auditoria_buffer b
                    INNER JOIN (
                        SELECT id FROM auditoria_buffer ORDER BY id ASC LIMIT batch_size
                    ) del ON b.id = del.id;
                END WHILE;
            END
        ");

        // Event de flush (10s)
        $pdo->exec("DROP EVENT IF EXISTS evt_flush_auditoria_buffer");
        $pdo->exec("
            CREATE EVENT evt_flush_auditoria_buffer
            ON SCHEDULE EVERY 10 SECOND
            DO
                CALL sp_flush_auditoria_buffer()
        ");

        // Recriar triggers para INSERT no buffer
        $triggers = [
            // equipamentos AI
            "DROP TRIGGER IF EXISTS trg_equipamentos_ai",
            "CREATE TRIGGER trg_equipamentos_ai AFTER INSERT ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'codigo_publico', NEW.codigo_publico, 'tipo_equipamento_id', NEW.tipo_equipamento_id, 'tipo_camera_id', NEW.tipo_camera_id, 'status_id', NEW.status_id, 'procedimento_id', NEW.procedimento_id, 'regiao_id', NEW.regiao_id, 'local_id', NEW.local_id, 'secretaria_id', NEW.secretaria_id, 'marca_id', NEW.marca_id, 'modelo_id', NEW.modelo_id, 'patrimonio', NEW.patrimonio, 'numero_serie', NEW.numero_serie, 'ip', NEW.ip, 'porta', NEW.porta, 'transmissao_id', NEW.transmissao_id, 'origem_link_id', NEW.origem_link_id, 'data_instalacao', NEW.data_instalacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
                INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
                VALUES (NEW.id, NEW.status_id, 'Cadastro inicial', @app_user_id);
            END",

            // equipamentos AU
            "DROP TRIGGER IF EXISTS trg_equipamentos_au",
            "CREATE TRIGGER trg_equipamentos_au AFTER UPDATE ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', NEW.id, 2,
                    JSON_OBJECT('status_id', OLD.status_id, 'tipo_camera_id', OLD.tipo_camera_id, 'procedimento_id', OLD.procedimento_id, 'regiao_id', OLD.regiao_id, 'local_id', OLD.local_id, 'secretaria_id', OLD.secretaria_id, 'marca_id', OLD.marca_id, 'modelo_id', OLD.modelo_id, 'patrimonio', OLD.patrimonio, 'numero_serie', OLD.numero_serie, 'ip', OLD.ip, 'porta', OLD.porta, 'transmissao_id', OLD.transmissao_id, 'origem_link_id', OLD.origem_link_id, 'data_instalacao', OLD.data_instalacao, 'observacao', OLD.observacao),
                    JSON_OBJECT('status_id', NEW.status_id, 'tipo_camera_id', NEW.tipo_camera_id, 'procedimento_id', NEW.procedimento_id, 'regiao_id', NEW.regiao_id, 'local_id', NEW.local_id, 'secretaria_id', NEW.secretaria_id, 'marca_id', NEW.marca_id, 'modelo_id', NEW.modelo_id, 'patrimonio', NEW.patrimonio, 'numero_serie', NEW.numero_serie, 'ip', NEW.ip, 'porta', NEW.porta, 'transmissao_id', NEW.transmissao_id, 'origem_link_id', NEW.origem_link_id, 'data_instalacao', NEW.data_instalacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
                IF NOT (NEW.status_id <=> OLD.status_id) THEN
                    INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
                    VALUES (NEW.id, NEW.status_id, 'Alteracao de status', @app_user_id);
                END IF;
            END",

            // equipamentos BD
            "DROP TRIGGER IF EXISTS trg_equipamentos_bd",
            "CREATE TRIGGER trg_equipamentos_bd BEFORE DELETE ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'codigo_publico', OLD.codigo_publico, 'tipo_equipamento_id', OLD.tipo_equipamento_id, 'tipo_camera_id', OLD.tipo_camera_id, 'status_id', OLD.status_id, 'procedimento_id', OLD.procedimento_id, 'regiao_id', OLD.regiao_id, 'local_id', OLD.local_id, 'secretaria_id', OLD.secretaria_id, 'marca_id', OLD.marca_id, 'modelo_id', OLD.modelo_id, 'patrimonio', OLD.patrimonio, 'numero_serie', OLD.numero_serie, 'ip', OLD.ip, 'porta', OLD.porta, 'transmissao_id', OLD.transmissao_id, 'origem_link_id', OLD.origem_link_id, 'data_instalacao', OLD.data_instalacao, 'observacao', OLD.observacao),
                    NULL, @app_user_id, @app_origem);
            END",

            // usuarios
            "DROP TRIGGER IF EXISTS trg_usuarios_ai",
            "CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_usuarios_au",
            "CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
                    JSON_OBJECT('nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_usuarios_bd",
            "CREATE TRIGGER trg_usuarios_bd BEFORE DELETE ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
                    NULL, @app_user_id, @app_origem);
            END",

            // secretarias
            "DROP TRIGGER IF EXISTS trg_secretarias_ai",
            "CREATE TRIGGER trg_secretarias_ai AFTER INSERT ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_secretarias_au",
            "CREATE TRIGGER trg_secretarias_au AFTER UPDATE ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
                    JSON_OBJECT('nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_secretarias_bd",
            "CREATE TRIGGER trg_secretarias_bd BEFORE DELETE ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
                    NULL, @app_user_id, @app_origem);
            END",

            // locais
            "DROP TRIGGER IF EXISTS trg_locais_ai",
            "CREATE TRIGGER trg_locais_ai AFTER INSERT ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_locais_au",
            "CREATE TRIGGER trg_locais_au AFTER UPDATE ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
                    JSON_OBJECT('nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_locais_bd",
            "CREATE TRIGGER trg_locais_bd BEFORE DELETE ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
                    NULL, @app_user_id, @app_origem);
            END",

            // central_alarmes
            "DROP TRIGGER IF EXISTS trg_central_alarmes_ai",
            "CREATE TRIGGER trg_central_alarmes_ai AFTER INSERT ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'regiao', NEW.regiao, 'regiao_id', NEW.regiao_id, 'conta', NEW.conta, 'status', NEW.status, 'status_id', NEW.status_id, 'local', NEW.local, 'endereco', NEW.endereco, 'numero', NEW.numero, 'pgm1', NEW.pgm1, 'pgm2', NEW.pgm2, 'mac', NEW.mac, 'ip', NEW.ip, 'integracao', NEW.integracao, 'camera_gm', NEW.camera_gm, 'quant_camera_gm', NEW.quant_camera_gm, 'ip_dvr', NEW.ip_dvr, 'cameras_dvr', NEW.cameras_dvr, 'modelo_alarme_id', NEW.modelo_alarme_id, 'quant_repetidor', NEW.quant_repetidor, 'qtde_sensores', NEW.qtde_sensores, 'documentacao', NEW.documentacao, 'monitorada', NEW.monitorada, 'numero_sei', NEW.numero_sei, 'data_atualizacao', NEW.data_atualizacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_central_alarmes_au",
            "CREATE TRIGGER trg_central_alarmes_au AFTER UPDATE ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', NEW.id, 2,
                    JSON_OBJECT('regiao', OLD.regiao, 'regiao_id', OLD.regiao_id, 'conta', OLD.conta, 'status', OLD.status, 'status_id', OLD.status_id, 'local', OLD.local, 'endereco', OLD.endereco, 'numero', OLD.numero, 'pgm1', OLD.pgm1, 'pgm2', OLD.pgm2, 'mac', OLD.mac, 'ip', OLD.ip, 'integracao', OLD.integracao, 'camera_gm', OLD.camera_gm, 'quant_camera_gm', OLD.quant_camera_gm, 'ip_dvr', OLD.ip_dvr, 'cameras_dvr', OLD.cameras_dvr, 'modelo_alarme_id', OLD.modelo_alarme_id, 'quant_repetidor', OLD.quant_repetidor, 'qtde_sensores', OLD.qtde_sensores, 'documentacao', OLD.documentacao, 'monitorada', OLD.monitorada, 'numero_sei', OLD.numero_sei, 'data_atualizacao', OLD.data_atualizacao, 'observacao', OLD.observacao),
                    JSON_OBJECT('regiao', NEW.regiao, 'regiao_id', NEW.regiao_id, 'conta', NEW.conta, 'status', NEW.status, 'status_id', NEW.status_id, 'local', NEW.local, 'endereco', NEW.endereco, 'numero', NEW.numero, 'pgm1', NEW.pgm1, 'pgm2', NEW.pgm2, 'mac', NEW.mac, 'ip', NEW.ip, 'integracao', NEW.integracao, 'camera_gm', NEW.camera_gm, 'quant_camera_gm', NEW.quant_camera_gm, 'ip_dvr', NEW.ip_dvr, 'cameras_dvr', NEW.cameras_dvr, 'modelo_alarme_id', NEW.modelo_alarme_id, 'quant_repetidor', NEW.quant_repetidor, 'qtde_sensores', NEW.qtde_sensores, 'documentacao', NEW.documentacao, 'monitorada', NEW.monitorada, 'numero_sei', NEW.numero_sei, 'data_atualizacao', NEW.data_atualizacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_central_alarmes_bd",
            "CREATE TRIGGER trg_central_alarmes_bd BEFORE DELETE ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'regiao', OLD.regiao, 'regiao_id', OLD.regiao_id, 'conta', OLD.conta, 'status', OLD.status, 'status_id', OLD.status_id, 'local', OLD.local, 'endereco', OLD.endereco, 'numero', OLD.numero, 'pgm1', OLD.pgm1, 'pgm2', OLD.pgm2, 'mac', OLD.mac, 'ip', OLD.ip, 'integracao', OLD.integracao, 'camera_gm', OLD.camera_gm, 'quant_camera_gm', OLD.quant_camera_gm, 'ip_dvr', OLD.ip_dvr, 'cameras_dvr', OLD.cameras_dvr, 'modelo_alarme_id', OLD.modelo_alarme_id, 'quant_repetidor', OLD.quant_repetidor, 'qtde_sensores', OLD.qtde_sensores, 'documentacao', OLD.documentacao, 'monitorada', OLD.monitorada, 'numero_sei', OLD.numero_sei, 'data_atualizacao', OLD.data_atualizacao, 'observacao', OLD.observacao),
                    NULL, @app_user_id, @app_origem);
            END",
        ];

        foreach ($triggers as $sql) {
            $pdo->exec($sql);
        }
    },

    'down' => function (PDO $pdo): void {
        // Droppar event e procedure
        $pdo->exec("DROP EVENT IF EXISTS evt_flush_auditoria_buffer");
        $pdo->exec("DROP PROCEDURE IF EXISTS sp_flush_auditoria_buffer");

        // Recriar triggers apontando para auditoria_eventos diretamente
        $triggers = [
            "DROP TRIGGER IF EXISTS trg_equipamentos_ai",
            "CREATE TRIGGER trg_equipamentos_ai AFTER INSERT ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'codigo_publico', NEW.codigo_publico, 'tipo_equipamento_id', NEW.tipo_equipamento_id, 'tipo_camera_id', NEW.tipo_camera_id, 'status_id', NEW.status_id, 'procedimento_id', NEW.procedimento_id, 'regiao_id', NEW.regiao_id, 'local_id', NEW.local_id, 'secretaria_id', NEW.secretaria_id, 'marca_id', NEW.marca_id, 'modelo_id', NEW.modelo_id, 'patrimonio', NEW.patrimonio, 'numero_serie', NEW.numero_serie, 'ip', NEW.ip, 'porta', NEW.porta, 'transmissao_id', NEW.transmissao_id, 'origem_link_id', NEW.origem_link_id, 'data_instalacao', NEW.data_instalacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
                INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
                VALUES (NEW.id, NEW.status_id, 'Cadastro inicial', @app_user_id);
            END",
            "DROP TRIGGER IF EXISTS trg_equipamentos_au",
            "CREATE TRIGGER trg_equipamentos_au AFTER UPDATE ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', NEW.id, 2,
                    JSON_OBJECT('status_id', OLD.status_id, 'tipo_camera_id', OLD.tipo_camera_id, 'procedimento_id', OLD.procedimento_id, 'regiao_id', OLD.regiao_id, 'local_id', OLD.local_id, 'secretaria_id', OLD.secretaria_id, 'marca_id', OLD.marca_id, 'modelo_id', OLD.modelo_id, 'patrimonio', OLD.patrimonio, 'numero_serie', OLD.numero_serie, 'ip', OLD.ip, 'porta', OLD.porta, 'transmissao_id', OLD.transmissao_id, 'origem_link_id', OLD.origem_link_id, 'data_instalacao', OLD.data_instalacao, 'observacao', OLD.observacao),
                    JSON_OBJECT('status_id', NEW.status_id, 'tipo_camera_id', NEW.tipo_camera_id, 'procedimento_id', NEW.procedimento_id, 'regiao_id', NEW.regiao_id, 'local_id', NEW.local_id, 'secretaria_id', NEW.secretaria_id, 'marca_id', NEW.marca_id, 'modelo_id', NEW.modelo_id, 'patrimonio', NEW.patrimonio, 'numero_serie', NEW.numero_serie, 'ip', NEW.ip, 'porta', NEW.porta, 'transmissao_id', NEW.transmissao_id, 'origem_link_id', NEW.origem_link_id, 'data_instalacao', NEW.data_instalacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
                IF NOT (NEW.status_id <=> OLD.status_id) THEN
                    INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
                    VALUES (NEW.id, NEW.status_id, 'Alteracao de status', @app_user_id);
                END IF;
            END",
            "DROP TRIGGER IF EXISTS trg_equipamentos_bd",
            "CREATE TRIGGER trg_equipamentos_bd BEFORE DELETE ON equipamentos FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('equipamentos', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'codigo_publico', OLD.codigo_publico, 'tipo_equipamento_id', OLD.tipo_equipamento_id, 'tipo_camera_id', OLD.tipo_camera_id, 'status_id', OLD.status_id, 'procedimento_id', OLD.procedimento_id, 'regiao_id', OLD.regiao_id, 'local_id', OLD.local_id, 'secretaria_id', OLD.secretaria_id, 'marca_id', OLD.marca_id, 'modelo_id', OLD.modelo_id, 'patrimonio', OLD.patrimonio, 'numero_serie', OLD.numero_serie, 'ip', OLD.ip, 'porta', OLD.porta, 'transmissao_id', OLD.transmissao_id, 'origem_link_id', OLD.origem_link_id, 'data_instalacao', OLD.data_instalacao, 'observacao', OLD.observacao),
                    NULL, @app_user_id, @app_origem);
            END",

            "DROP TRIGGER IF EXISTS trg_usuarios_ai",
            "CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_usuarios_au",
            "CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
                    JSON_OBJECT('nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_usuarios_bd",
            "CREATE TRIGGER trg_usuarios_bd BEFORE DELETE ON usuarios FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('usuarios', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
                    NULL, @app_user_id, @app_origem);
            END",

            "DROP TRIGGER IF EXISTS trg_secretarias_ai",
            "CREATE TRIGGER trg_secretarias_ai AFTER INSERT ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_secretarias_au",
            "CREATE TRIGGER trg_secretarias_au AFTER UPDATE ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
                    JSON_OBJECT('nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_secretarias_bd",
            "CREATE TRIGGER trg_secretarias_bd BEFORE DELETE ON secretarias FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('secretarias', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
                    NULL, @app_user_id, @app_origem);
            END",

            "DROP TRIGGER IF EXISTS trg_locais_ai",
            "CREATE TRIGGER trg_locais_ai AFTER INSERT ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_locais_au",
            "CREATE TRIGGER trg_locais_au AFTER UPDATE ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', NEW.id, 2,
                    JSON_OBJECT('nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
                    JSON_OBJECT('nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_locais_bd",
            "CREATE TRIGGER trg_locais_bd BEFORE DELETE ON locais FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('locais', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
                    NULL, @app_user_id, @app_origem);
            END",

            "DROP TRIGGER IF EXISTS trg_central_alarmes_ai",
            "CREATE TRIGGER trg_central_alarmes_ai AFTER INSERT ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', NEW.id, 1, NULL,
                    JSON_OBJECT('id', NEW.id, 'regiao', NEW.regiao, 'regiao_id', NEW.regiao_id, 'conta', NEW.conta, 'status', NEW.status, 'status_id', NEW.status_id, 'local', NEW.local, 'endereco', NEW.endereco, 'numero', NEW.numero, 'pgm1', NEW.pgm1, 'pgm2', NEW.pgm2, 'mac', NEW.mac, 'ip', NEW.ip, 'integracao', NEW.integracao, 'camera_gm', NEW.camera_gm, 'quant_camera_gm', NEW.quant_camera_gm, 'ip_dvr', NEW.ip_dvr, 'cameras_dvr', NEW.cameras_dvr, 'modelo_alarme_id', NEW.modelo_alarme_id, 'quant_repetidor', NEW.quant_repetidor, 'qtde_sensores', NEW.qtde_sensores, 'documentacao', NEW.documentacao, 'monitorada', NEW.monitorada, 'numero_sei', NEW.numero_sei, 'data_atualizacao', NEW.data_atualizacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_central_alarmes_au",
            "CREATE TRIGGER trg_central_alarmes_au AFTER UPDATE ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', NEW.id, 2,
                    JSON_OBJECT('regiao', OLD.regiao, 'regiao_id', OLD.regiao_id, 'conta', OLD.conta, 'status', OLD.status, 'status_id', OLD.status_id, 'local', OLD.local, 'endereco', OLD.endereco, 'numero', OLD.numero, 'pgm1', OLD.pgm1, 'pgm2', OLD.pgm2, 'mac', OLD.mac, 'ip', OLD.ip, 'integracao', OLD.integracao, 'camera_gm', OLD.camera_gm, 'quant_camera_gm', OLD.quant_camera_gm, 'ip_dvr', OLD.ip_dvr, 'cameras_dvr', OLD.cameras_dvr, 'modelo_alarme_id', OLD.modelo_alarme_id, 'quant_repetidor', OLD.quant_repetidor, 'qtde_sensores', OLD.qtde_sensores, 'documentacao', OLD.documentacao, 'monitorada', OLD.monitorada, 'numero_sei', OLD.numero_sei, 'data_atualizacao', OLD.data_atualizacao, 'observacao', OLD.observacao),
                    JSON_OBJECT('regiao', NEW.regiao, 'regiao_id', NEW.regiao_id, 'conta', NEW.conta, 'status', NEW.status, 'status_id', NEW.status_id, 'local', NEW.local, 'endereco', NEW.endereco, 'numero', NEW.numero, 'pgm1', NEW.pgm1, 'pgm2', NEW.pgm2, 'mac', NEW.mac, 'ip', NEW.ip, 'integracao', NEW.integracao, 'camera_gm', NEW.camera_gm, 'quant_camera_gm', NEW.quant_camera_gm, 'ip_dvr', NEW.ip_dvr, 'cameras_dvr', NEW.cameras_dvr, 'modelo_alarme_id', NEW.modelo_alarme_id, 'quant_repetidor', NEW.quant_repetidor, 'qtde_sensores', NEW.qtde_sensores, 'documentacao', NEW.documentacao, 'monitorada', NEW.monitorada, 'numero_sei', NEW.numero_sei, 'data_atualizacao', NEW.data_atualizacao, 'observacao', NEW.observacao),
                    @app_user_id, @app_origem);
            END",
            "DROP TRIGGER IF EXISTS trg_central_alarmes_bd",
            "CREATE TRIGGER trg_central_alarmes_bd BEFORE DELETE ON central_alarmes FOR EACH ROW BEGIN
                INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
                VALUES ('central_alarmes', OLD.id, 3,
                    JSON_OBJECT('id', OLD.id, 'regiao', OLD.regiao, 'regiao_id', OLD.regiao_id, 'conta', OLD.conta, 'status', OLD.status, 'status_id', OLD.status_id, 'local', OLD.local, 'endereco', OLD.endereco, 'numero', OLD.numero, 'pgm1', OLD.pgm1, 'pgm2', OLD.pgm2, 'mac', OLD.mac, 'ip', OLD.ip, 'integracao', OLD.integracao, 'camera_gm', OLD.camera_gm, 'quant_camera_gm', OLD.quant_camera_gm, 'ip_dvr', OLD.ip_dvr, 'cameras_dvr', OLD.cameras_dvr, 'modelo_alarme_id', OLD.modelo_alarme_id, 'quant_repetidor', OLD.quant_repetidor, 'qtde_sensores', OLD.qtde_sensores, 'documentacao', OLD.documentacao, 'monitorada', OLD.monitorada, 'numero_sei', OLD.numero_sei, 'data_atualizacao', OLD.data_atualizacao, 'observacao', OLD.observacao),
                    NULL, @app_user_id, @app_origem);
            END",
        ];

        foreach ($triggers as $sql) {
            $pdo->exec($sql);
        }

        // Drop buffer table
        $pdo->exec("DROP TABLE IF EXISTS auditoria_buffer");
    },
];
