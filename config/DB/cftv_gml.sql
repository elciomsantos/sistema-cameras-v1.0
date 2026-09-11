
-- DROP DATABASE IF EXISTS cftv_gml;
-- O MySQL ja cria o banco MYSQL_DATABASE antes dos scripts-init; manter
-- IF NOT EXISTS mantem o script nao-destrutivo e sem erros em volume novo.
CREATE DATABASE IF NOT EXISTS cftv_gml
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE cftv_gml;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- =====================================================
-- USUARIO DO BANCO (ambiente de desenvolvimento)
-- =====================================================

CREATE TABLE niveis_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Níveis de acesso dos usuários';

INSERT INTO niveis_acesso (nome) VALUES ('admin'), ('supervisor'), ('user');

-- =====================================================
-- TIPOS DE SECRETARIA
-- =====================================================
CREATE TABLE tipos_secretaria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Tipos de entidade (secretaria, autarquia, etc)';

INSERT INTO tipos_secretaria (nome) VALUES ('secretaria'), ('autarquia'), ('fundacao'), ('orgao');

-- =====================================================
-- SECRETARIAS/ENTIDADES (UNIFICADA)
-- =====================================================
CREATE TABLE secretarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE,
    sigla VARCHAR(20) NOT NULL UNIQUE,
    tipo_id INT NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_secretarias_tipo FOREIGN KEY (tipo_id) REFERENCES tipos_secretaria(id) ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Secretarias e órgãos';

CREATE INDEX idx_secretarias_tipo ON secretarias(tipo_id);
CREATE FULLTEXT INDEX ftx_secretarias_nome ON secretarias(nome);

INSERT INTO secretarias (nome, sigla, tipo_id) VALUES
-- Secretarias
('SECRETARIA DE DEFESA SOCIAL', 'SMDS', 1),
('SECRETARIA MUNICIPAL DE EDUCAÇÃO', 'SME', 1),
('SECRETARIA MUNICIPAL DE SAÚDE', 'SMS', 1),
('SECRETARIA MUNICIPAL DE CULTURA', 'SMC', 1),
('SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL', 'SMAS', 1),
('SECRETARIA MUNICIPAL DO MEIO AMBIENTE', 'SEMA', 1),
('SECRETARIA MUNICIPAL DE OBRAS', 'SMO', 1),
-- AUTARQUIAS E FUNDAÇÕES
('ACESF', 'ACESF', 2),
('FUNDAÇÃO DO ESPORTE DE LONDRINA', 'FEL', 3),
-- ÓRGÃOS
('PREFEITURA MUNICIPAL DE LONDRINA', 'PML', 4);

-- =====================================================
-- USUÁRIOS DO SISTEMA
-- =====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel_acesso_id INT NOT NULL,
    secretaria_id INT NULL,
    senha_temporaria TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL COMMENT 'Data de exclusao logica (soft delete)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL,
    CONSTRAINT fk_usuarios_nivel_acesso FOREIGN KEY (nivel_acesso_id) REFERENCES niveis_acesso(id) ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Usuários do sistema';

CREATE INDEX idx_usuarios_nivel_acesso ON usuarios(nivel_acesso_id);
CREATE INDEX idx_usuarios_secretaria ON usuarios(secretaria_id);
CREATE INDEX idx_usuarios_deleted ON usuarios(deleted_at);

-- Senhas padrao (seed): admin / admin@123, supervisor / admin@123, usuario / admin@123
-- IMPORTANTE: senha_temporaria = 1 força a troca de senha no primeiro login.
-- Apos o primeiro acesso de cada conta, troque a senha imediatamente.
INSERT INTO usuarios (nome, usuario, email, senha, nivel_acesso_id, secretaria_id, senha_temporaria) VALUES
('Administrador', 'admin', 'admin@sistema.local', '$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK', 1, 1, 1),
('Supervisor', 'supervisor', 'supervisor@sistema.local', '$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK', 2, 1, 1),
('Usuario Padrao', 'usuario', 'usuario@sistema.local', '$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK', 3, 1, 1);

-- =====================================================
-- SESSAO UNICA POR USUARIO
-- =====================================================
CREATE TABLE user_sessions (
    usuario_id INT NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL COMMENT 'Data de expiração da sessão',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id),
    CONSTRAINT uq_user_sessions_token UNIQUE (session_token_hash),
    CONSTRAINT uq_user_sessions_session_id UNIQUE (session_id),
    CONSTRAINT ck_user_sessions_active CHECK (active IN (0,1)),
    CONSTRAINT fk_user_sessions_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Controle de sessão única por usuário';

CREATE INDEX idx_user_sessions_active ON user_sessions(active, updated_at);
CREATE INDEX idx_user_sessions_last_seen ON user_sessions(last_seen);
CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at);

-- =====================================================
-- TABELAS AUXILIARES (SEM DEPENDÊNCIAS)
-- =====================================================
CREATE TABLE status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Status dos equipamentos';

INSERT INTO status (nome) VALUES
('FUNCIONANDO'),
('PARADA'),
('DESATIVADA'),
('REMOVIDA'),
('MANUTENCAO'),
('ATIVO'),
('INATIVO'),
('DESLIGADO'),
('PENDENTE');

CREATE TABLE status_os (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Status de ordem de servico (workflow)';

INSERT INTO status_os (nome) VALUES
('CADASTRADA'),
('EXECUTANDO'),
('REALIZADA'),
('CANCELADA');

-- Cache de geocodificação (usado por api/v2/api_geocode.php)
CREATE TABLE geocode_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    query_hash CHAR(64) NOT NULL COMMENT 'SHA-256 hash da query normalizada',
    query TEXT NOT NULL COMMENT 'Query original do usuário',
    response JSON NOT NULL COMMENT 'Resposta parseada do geocodificador (lat, lon, display_name)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geocode_cache_query_hash (query_hash),
    INDEX idx_geocode_cache_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache de geocodificação (Nominatim/Photon)';

CREATE TABLE procedimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Tipos de procedimentos (instalação, remoção, etc)';

INSERT INTO procedimentos (nome) VALUES
('MANUTENCAO'),
('INSTALACAO'),
('REMOCAO'),
('ATUALIZACAO');

CREATE TABLE regioes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Regiões geográficas';

INSERT INTO regioes (nome) VALUES
('CENTRO'),
('LESTE'),
('OESTE'),
('SUL'),
('NORTE'),
('DISTRITO');

CREATE TABLE transmissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Tipos de transmissão';

INSERT INTO transmissoes (tipo) VALUES
('LINK'),
('CLOUD'),
('RADIO');

CREATE TABLE origem_link (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE,
    inscricao VARCHAR(20) NOT NULL UNIQUE COMMENT 'Inscrição ou identificador da operadora'
) ENGINE=InnoDB COMMENT='Operadoras/provedores de link';

INSERT INTO origem_link (nome, inscricao) VALUES
('SERCOMTEL', 'SERCOMTEL'),
('NG TELECOM', 'NG TELECOM'),
('COPEL', 'COPEL'),
('ALGAR', 'ALGAR'),
('VIVO', 'VIVO'),
('TIM', 'TIM'),
('CLARO', 'CLARO'),
('LINK LOCAL', 'LINK LOCAL'),
('OUTRO', 'OUTRO');

CREATE TABLE marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Marcas de equipamentos';

INSERT INTO marcas (nome) VALUES
('INTELBRAS'),
('HIKVISION'),
('MOTOROLA'),
('AXIS'),
('COMTEX'),
('SAMSUNG'),
('3S'),
('PELCO');

CREATE TABLE tipos_equipamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Tipos de equipamento';

INSERT INTO tipos_equipamento (nome) VALUES
('CAMERA'),
('LPR'),
('DVR'),
('TOTEM');

-- =====================================================
-- SUB-TIPOS DE CAMERAS
-- =====================================================
CREATE TABLE tipo_cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Sub-tipos de câmeras';

INSERT INTO tipo_cameras (nome) VALUES
('FIXA'),
('SPEED DOME'),
('LPR'),
('FACIAL');

-- =====================================================
-- CATALOGO DE MODELOS
-- =====================================================
CREATE TABLE catalogo_modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_equipamento_id INT NOT NULL,
    marca_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    CONSTRAINT uq_catalogo_modelos UNIQUE (tipo_equipamento_id, marca_id, nome),
    CONSTRAINT fk_catalogo_modelos_tipo FOREIGN KEY (tipo_equipamento_id) REFERENCES tipos_equipamento(id) ON DELETE RESTRICT,
    CONSTRAINT fk_catalogo_modelos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Catálogo de modelos por tipo e marca';

CREATE INDEX idx_catalogo_marca ON catalogo_modelos(marca_id);
CREATE INDEX idx_catalogo_tipo_marca ON catalogo_modelos(tipo_equipamento_id, marca_id);
CREATE FULLTEXT INDEX ftx_catalogo_modelo_nome ON catalogo_modelos(nome);

-- =====================================================
-- CATALOGO DE MODELOS DE ALARMES
-- =====================================================
CREATE TABLE catalogo_modelos_alarmes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_modelo_alarme_nome UNIQUE (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de modelos de alarmes';

INSERT INTO catalogo_modelos_alarmes (nome) VALUES
('INTELBRAS AMT 8000'),
('INTELBRAS AMT 8000 PRO'),
('INTELBRAS AMT 8000 LITE'),
('INTELBRAS AMT 8000 PLUS'),
('INTELBRAS AMT 2018 EG'),
('INTELBRAS AMT 2018 SMART'),
('INTELBRAS AMT 4010 RF'),
('INTELBRAS AMT 4010 SMART');

INSERT INTO catalogo_modelos (tipo_equipamento_id, marca_id, nome) VALUES
-- Intelbras (marca_id = 1) - CÂMERAS
(1, 1, 'VHD 1220 B'),
(1, 1, 'VHD 1220 C'),
(1, 1, 'VHD 3220 B'),
(1, 1, 'VHD 3220 C'),
(1, 1, 'VHD 4320'),
-- Hikvision (marca_id = 2) - CÂMERAS
(1, 2, 'DS-2CD2143G0-I'),
(1, 2, 'DS-2CD2343G0-I'),
(1, 2, 'DS-2CD2043G0-I'),
(1, 2, 'DS-2CD2083G0-I'),
-- Motorola (marca_id = 3) - CÂMERAS
(1, 3, 'MC40'),
(1, 3, 'MC50'),
-- Axis (marca_id = 4) - CÂMERAS
(1, 4, 'M3045-V'),
(1, 4, 'P3364-V'),
(1, 4, 'Q3505-V'),
-- Comtex (marca_id = 5) - CÂMERAS
(1, 5, 'CTX-1000'),
(1, 5, 'CTX-2000'),
-- Samsung (marca_id = 6) - CÂMERAS
(1, 6, 'SNV-6084'),
(1, 6, 'SNV-7084'),
-- 3S (marca_id = 7) - CÂMERAS
(1, 7, '3S-5000'),
(1, 7, '3S-6000'),
-- Pelco (marca_id = 8) - CÂMERAS
(1, 8, 'Spectra IV'),
(1, 8, 'Esprit'),
-- Intelbras DVRs (tipo_equipamento_id = 3)
(3, 1, 'DVR 1104'),
(3, 1, 'DVR 2108'),
-- Hikvision DVRs
(3, 2, 'DS-7104'),
(3, 2, 'DS-7208');

CREATE TABLE tipos_locais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Classificacao semantica do local (rua, praca, predio, etc)';

INSERT INTO tipos_locais (nome) VALUES
('ESCOLA'),
('CMEI'),
('CEI'),
('UBS'),
('UPA'),
('PRONTO ATENDIMENTO'),
('PRACA'),
('RUA'),
('AVENIDA'),
('PREDIO PUBLICO'),
('PREDIO PRIVADO'),
('PARQUE'),
('TERMINAL URBANO'),
('TERMINAL RODOVIÁRIO'),
('ESTRADA RURAL'),
('AREA RURAL'),
('OUTRO');


-- =====================================================
-- CLASSIFICAÇÃO DE ENDEREÇOS (TIPO DE LOGRADOURO)
-- =====================================================
CREATE TABLE classificacao_enderecos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB COMMENT='Tipo de logradouro do endereco (RUA/AVENIDA/etc)';

INSERT INTO classificacao_enderecos (descricao) VALUES
('RUA'),
('AVENIDA'),
('TRAVESSA'),
('RODOVIA'),
('ALAMEDA'),
('PRACA'),
('PARQUE'),
('ESTRADA RURAL'),
('CHACARA'),
('OUTRO');

-- =====================================================
-- LOCAIS
-- =====================================================
CREATE TABLE locais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    logradouro VARCHAR(255) NULL COMMENT 'Nome da via',
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    cep VARCHAR(10) NULL,
    numero VARCHAR(20) NULL,
    descricao_posicao VARCHAR(255) NULL, 
    tipo_local_id INT NULL,
    classificacao_endereco_id INT NULL COMMENT 'Tipo de logradouro (RUA/AVENIDA/etc)',
    tem_alarme TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Indica se o local possui central de alarme (0=Não, 1=Sim)',
    tem_camera TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Indica se o local possui  camera (0=Não, 1=Sim)',
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    regiao_id INT NULL COMMENT 'FK para regioes',
    horario_funcionamento VARCHAR(255) NULL COMMENT 'Horario de funcionamento do local',
    telefone VARCHAR(50) NULL COMMENT 'Telefone do local',
    ramal VARCHAR(50) NULL COMMENT 'Ramal do local',
    email VARCHAR(150) NULL COMMENT 'E-mail do local',
    secretaria_id INT NULL,
    alarme_conta INT NULL COMMENT 'Conta de alarme FK para central_alarmes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL COMMENT 'Data de exclusao logica (soft delete)',
    -- Coluna virtual que retorna o nome apenas para registros ativos (soft-delete).
    -- Em MariaDB, valores NULL em UNIQUE nao conflitam, permitindo recriar um
    -- local com mesmo nome de outro ja excluido logicamente (deleted_at NOT NULL).
    nome_ativo VARCHAR(150) AS (IF(deleted_at IS NULL, nome, NULL)) VIRTUAL,
    CONSTRAINT fk_locais_regiao FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE SET NULL,
    CONSTRAINT fk_locais_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL,
    CONSTRAINT fk_locais_tipo_local FOREIGN KEY (tipo_local_id) REFERENCES tipos_locais(id) ON DELETE SET NULL,
    CONSTRAINT fk_locais_classif_endereco FOREIGN KEY (classificacao_endereco_id) REFERENCES classificacao_enderecos(id) ON DELETE SET NULL,
    CONSTRAINT ck_locais_latitude CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90)),
    CONSTRAINT ck_locais_longitude CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180)),
    CONSTRAINT uq_locais_nome_ativo UNIQUE (nome_ativo)
) ENGINE=InnoDB;

CREATE INDEX idx_locais_regiao ON locais(regiao_id);
CREATE INDEX idx_locais_secretaria ON locais(secretaria_id);
CREATE INDEX idx_locais_tipo_local ON locais(tipo_local_id);
CREATE INDEX idx_locais_classif_endereco ON locais(classificacao_endereco_id);
CREATE INDEX idx_locais_deleted ON locais(deleted_at);
CREATE INDEX idx_locais_cep ON locais(cep);

-- =====================================================
-- CORE UNIFICADO: EQUIPAMENTOS
-- =====================================================
CREATE TABLE equipamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo_publico VARCHAR(40) NULL UNIQUE COMMENT 'Código público amigável (gerado automaticamente)',
    tipo_equipamento_id INT NOT NULL,
    tipo_camera_id INT NULL,
    status_id INT NOT NULL,
    procedimento_id INT NULL,
    regiao_id INT NULL,
    local_id INT NULL,
    secretaria_id INT NULL,
    marca_id INT NULL,
    modelo_id INT NULL,
    patrimonio VARCHAR(150) NULL COMMENT 'Número de patrimônio',
    numero_serie VARCHAR(150) NULL COMMENT 'Número de série do fabricante',
    ip VARCHAR(45) NULL,
    porta INT NULL,
    transmissao_id INT NULL,
    origem_link_id INT NULL,
    inscricao VARCHAR(20) NULL COMMENT 'Inscricao do link/operadora',
    data_instalacao DATE NULL,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL COMMENT 'Data de exclusão lógica (soft delete)',

    -- Foreign Keys
    CONSTRAINT fk_equip_tipo FOREIGN KEY (tipo_equipamento_id) REFERENCES tipos_equipamento(id) ON DELETE RESTRICT,
    CONSTRAINT fk_equip_tipo_camera FOREIGN KEY (tipo_camera_id) REFERENCES tipo_cameras(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_status FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE RESTRICT,
    CONSTRAINT fk_equip_procedimento FOREIGN KEY (procedimento_id) REFERENCES procedimentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_regiao FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_local FOREIGN KEY (local_id) REFERENCES locais(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_modelo FOREIGN KEY (modelo_id) REFERENCES catalogo_modelos(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_transmissao FOREIGN KEY (transmissao_id) REFERENCES transmissoes(id) ON DELETE SET NULL,
    CONSTRAINT fk_equip_origem FOREIGN KEY (origem_link_id) REFERENCES origem_link(id) ON DELETE SET NULL,
    CONSTRAINT ck_equip_porta CHECK (porta IS NULL OR (porta BETWEEN 1 AND 65535))
) ENGINE=InnoDB COMMENT='Tabela principal de equipamentos';

-- =====================================================
-- ÍNDICES ADICIONAIS
-- =====================================================
CREATE INDEX idx_equip_patrimonio ON equipamentos(patrimonio(50));
CREATE INDEX idx_equip_serie ON equipamentos(numero_serie(50));
CREATE INDEX idx_equip_ip ON equipamentos(ip);
CREATE INDEX idx_equip_data_instalacao ON equipamentos(data_instalacao);
CREATE INDEX idx_equip_created ON equipamentos(created_at);
CREATE INDEX idx_equip_updated ON equipamentos(updated_at);
CREATE INDEX idx_equip_composicao ON equipamentos(tipo_equipamento_id, status_id, regiao_id);
CREATE INDEX idx_equip_secretaria ON equipamentos(secretaria_id);
CREATE INDEX idx_equip_busca ON equipamentos(tipo_equipamento_id, status_id, secretaria_id);
CREATE INDEX idx_equip_local ON equipamentos(local_id, status_id);
CREATE INDEX idx_equip_ip_porta ON equipamentos(ip, porta);
CREATE INDEX idx_equip_ip_deleted ON equipamentos(ip, deleted_at); -- busca por IP ignorando soft delete
CREATE INDEX idx_equip_deleted ON equipamentos(deleted_at);
CREATE INDEX idx_equip_deleted_id ON equipamentos(deleted_at, id); -- otimiza soft delete queries
CREATE FULLTEXT INDEX ftx_equip_observacao ON equipamentos(observacao); -- busca textual em observações
CREATE INDEX idx_equip_tipo_camera ON equipamentos(tipo_camera_id);
CREATE INDEX idx_equip_procedimento ON equipamentos(procedimento_id);
CREATE INDEX idx_equip_marca ON equipamentos(marca_id);
CREATE INDEX idx_equip_modelo ON equipamentos(modelo_id);
CREATE INDEX idx_equip_transmissao ON equipamentos(transmissao_id);
CREATE INDEX idx_equip_origem_link ON equipamentos(origem_link_id);
CREATE INDEX idx_equip_deleted_status ON equipamentos(deleted_at, status_id);
CREATE INDEX idx_equip_deleted_tipo ON equipamentos(deleted_at, tipo_equipamento_id);
CREATE INDEX idx_equip_deleted_secretaria ON equipamentos(deleted_at, secretaria_id);
CREATE INDEX idx_equip_deleted_tipo_camera ON equipamentos(deleted_at, tipo_camera_id);

-- =====================================================
-- TABELAS FILHAS (ESPECÍFICAS POR TIPO)
-- =====================================================
CREATE TABLE equipamentos_camera (
    equipamento_id BIGINT UNSIGNED PRIMARY KEY,
    mosaico VARCHAR(100) NULL COMMENT 'Identificação do mosaico',
    coordenadas VARCHAR(120) NULL COMMENT 'Coordenadas geográficas',
    numero_ruas VARCHAR(120) NULL COMMENT 'Ruas monitoradas',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eq_camera FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Atributos específicos de câmeras';

CREATE TABLE equipamentos_lpr (
    equipamento_id BIGINT UNSIGNED PRIMARY KEY,
    sentido_via VARCHAR(50) NULL,
    faixa_monitorada VARCHAR(50) NULL,
    url_acesso VARCHAR(2083) NULL,
    leitura_noturna TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_eq_lpr FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
        CONSTRAINT ck_lpr_leitura_noturna CHECK (leitura_noturna IN (0,1))
    ) ENGINE=InnoDB COMMENT='Atributos específicos de LPR (Leitura de Placas)';

    CREATE TABLE equipamentos_dvr (
        equipamento_id BIGINT UNSIGNED PRIMARY KEY,
        modelo VARCHAR(80) not NULL,
        canais INT NULL,
        armazenamento_tb DECIMAL(6,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_eq_dvr FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
        CONSTRAINT ck_dvr_canais CHECK (canais IS NULL OR canais > 0),
        CONSTRAINT ck_dvr_armazenamento CHECK (armazenamento_tb IS NULL OR armazenamento_tb >= 0)
    ) ENGINE=InnoDB COMMENT='Atributos específicos de DVR';

CREATE TABLE equipamentos_totem (
    equipamento_id BIGINT UNSIGNED PRIMARY KEY,
    quantidade_cameras int not NULL,
    tem_facial TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Indica se o local possui reconhecimento facial (0=Não, 1=Sim)',
    tem_lpr TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Indica se o local possui leitor de placa (0=Não, 1=Sim)',
    altura_metros DECIMAL(6,2) NULL COMMENT 'Altura do totem em metros',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eq_totem FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT ck_totem_altura CHECK (altura_metros IS NULL OR altura_metros > 0)
) ENGINE=InnoDB COMMENT='Atributos específicos de Totem';

-- =====================================================
-- HISTÓRICO FUNCIONAL
-- =====================================================
CREATE TABLE equipamentos_status_historico (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id BIGINT UNSIGNED NOT NULL,
    status_id INT NOT NULL,
    observacao VARCHAR(255) NULL,
    changed_by INT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL, -- para possível exclusão lógica, se necessário
    CONSTRAINT fk_eq_status_hist_equip FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_status_hist_status FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE RESTRICT,
    CONSTRAINT fk_eq_status_hist_user FOREIGN KEY (changed_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Histórico de mudanças de status dos equipamentos';

CREATE INDEX idx_status_hist_busca ON equipamentos_status_historico(equipamento_id, status_id, changed_at);
CREATE INDEX idx_eq_status_hist ON equipamentos_status_historico(equipamento_id, changed_at);
CREATE INDEX idx_status_hist_deleted ON equipamentos_status_historico(deleted_at, equipamento_id); -- otimiza soft delete
CREATE INDEX idx_status_hist_changed_by ON equipamentos_status_historico(changed_by);


CREATE TABLE equipamentos_manutencoes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id BIGINT UNSIGNED NOT NULL,
    secretaria_id INT NULL,
    procedimento_id INT NULL,
    status_id INT NULL,
    numero_os VARCHAR(50) NULL,
    tecnico VARCHAR(255) NULL COMMENT 'Nome do técnico (preferir tecnico_id)',
    tecnico_id INT NULL COMMENT 'FK para usuarios(id)',
    local_servico VARCHAR(255) NULL,
    endereco_servico VARCHAR(255) NULL,
    descricao TEXT NOT NULL,
    problemas TEXT NULL,
    pecas_previstas TEXT NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_execucao DATETIME NULL,
    created_by INT NULL,
    executado_por INT NULL,
    os_status_id INT NOT NULL DEFAULT 1 COMMENT 'FK para status_os (1=cadastrada, 2=executando, 3=realizada, 4=cancelada)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT fk_eq_manut_equip FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_manut_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_manut_proc FOREIGN KEY (procedimento_id) REFERENCES procedimentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_manut_status FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_manut_user FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_manut_executado_by FOREIGN KEY (executado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_manut_os_status FOREIGN KEY (os_status_id) REFERENCES status_os(id),
    CONSTRAINT fk_eq_manut_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_eq_manut (equipamento_id, data_hora),
    INDEX idx_eq_manut_os_status (os_status_id),
    INDEX idx_eq_manut_os_status_data_hora (os_status_id, data_hora),
    INDEX idx_eq_manut_os_status_updated (os_status_id, updated_at),
    INDEX idx_eq_manut_numero_os (numero_os),
    INDEX idx_eq_manut_status_data_execucao (status_id, data_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de manutenções realizadas em equipamentos';

CREATE FULLTEXT INDEX ftx_manutencao_texto ON equipamentos_manutencoes(descricao, problemas, pecas_previstas);
CREATE INDEX idx_eq_manut_os_created ON equipamentos_manutencoes(os_status_id, created_at);
CREATE INDEX idx_eq_manut_os_execucao ON equipamentos_manutencoes(os_status_id, data_execucao, data_hora);
CREATE INDEX idx_eq_manut_created_by ON equipamentos_manutencoes(created_by);
CREATE INDEX idx_eq_manut_executado_por ON equipamentos_manutencoes(executado_por);
CREATE INDEX idx_eq_manut_tecnico ON equipamentos_manutencoes(tecnico_id);
CREATE INDEX idx_eq_manut_secretaria ON equipamentos_manutencoes(secretaria_id);
CREATE INDEX idx_eq_manut_procedimento ON equipamentos_manutencoes(procedimento_id);
CREATE INDEX idx_eq_manut_os_status_equip ON equipamentos_manutencoes(os_status_id, equipamento_id);
CREATE INDEX idx_eq_manut_historico ON equipamentos_manutencoes(os_status_id, data_execucao, data_hora, created_at);
CREATE INDEX idx_eq_manut_status ON equipamentos_manutencoes(status_id);

-- =====================================================
-- TABELA CENTRAL DE ALARMES (integração externa)
-- =====================================================
CREATE TABLE central_alarmes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    regiao VARCHAR(50),
    regiao_id INT NULL COMMENT 'FK para regioes',
    conta INT NOT NULL UNIQUE,
    status VARCHAR(20),
    status_id INT NULL COMMENT 'FK para status',
    local VARCHAR(255),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    cep VARCHAR(10) NULL,
    pgm1 VARCHAR(100),
    pgm2 VARCHAR(100),
    mac VARCHAR(50),
    ip VARCHAR(50),
    integracao DATE,
    camera_gm TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Não, 1=Sim',
    quant_camera_gm INT NULL COMMENT 'Quantidade de câmeras GM (se camera_gm=1)',
    ip_dvr VARCHAR(45),
    cameras_dvr INT,
    modelo_alarme_id INT NULL,
    alarme_local_id INT NULL COMMENT 'FK direta para locais.id (substitui JOIN via alarme_conta)',
    quant_repetidor INT,
    qtde_sensores INT,
    documentacao VARCHAR(50),
    monitorada VARCHAR(50), -- sem função deletar 
    numero_sei VARCHAR(50),
    data_atualizacao DATE,
    observacao TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL COMMENT 'Data de exclusao logica (soft delete)',
    CONSTRAINT fk_central_alarmes_modelo FOREIGN KEY (modelo_alarme_id) REFERENCES catalogo_modelos_alarmes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_central_alarmes_status FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE RESTRICT,
    CONSTRAINT fk_central_alarmes_regiao FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_central_alarmes_local FOREIGN KEY (alarme_local_id) REFERENCES locais(id) ON DELETE SET NULL,
    
    INDEX idx_alarmes_conta (conta),
    INDEX idx_alarmes_status (status),
    INDEX idx_alarmes_camera_gm (camera_gm),
    INDEX idx_alarmes_data_atualizacao (data_atualizacao),
    INDEX idx_alarmes_status_id (status_id),
    INDEX idx_alarmes_regiao_id (regiao_id),
    INDEX idx_alarmes_local_id (alarme_local_id),
    INDEX idx_alarmes_deleted (deleted_at),
    INDEX idx_alarmes_deleted_id (deleted_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_alarmes_status_regiao ON central_alarmes(status, regiao);
CREATE INDEX idx_alarmes_data_status ON central_alarmes(data_atualizacao, status);
CREATE INDEX idx_alarmes_ip ON central_alarmes(ip);
CREATE INDEX idx_alarmes_mac ON central_alarmes(mac);
CREATE INDEX idx_alarmes_numero_sei ON central_alarmes(numero_sei);
CREATE FULLTEXT INDEX ftx_alarmes_texto ON central_alarmes(local, endereco, observacao);

-- Relacionar locais com central_alarmes via conta
CREATE INDEX idx_locais_alarme_conta ON locais(alarme_conta);
ALTER TABLE locais
ADD CONSTRAINT fk_locais_alarme_conta
FOREIGN KEY (alarme_conta) REFERENCES central_alarmes(conta) ON DELETE SET NULL;

CREATE FULLTEXT INDEX ftx_locais_busca ON locais(nome, logradouro, bairro);

-- A unicidade do nome do local e garantida pela constraint uq_locais_nome_ativo
-- definida na propria CREATE TABLE locais (sobre coluna virtual nome_ativo),
-- que respeita o soft-delete (deleted_at). A antiga uq_locais_nome_secretaria
-- (nome, secretaria_id) foi removida porque, quando secretaria_id era NULL,
-- o MySQL/MariaDB permitia nomes duplicados.

CREATE TABLE alarmes_manutencoes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    alarme_id INT NOT NULL,
    tecnico VARCHAR(255) NULL COMMENT 'Nome do técnico (preferir tecnico_id)',
    tecnico_id INT NULL COMMENT 'FK para usuarios(id)',
    descricao TEXT NOT NULL, 
    numero_os VARCHAR(50) NULL,
    problemas TEXT NULL,
    procedimento_id INT NULL,
    status_id INT NULL,
    pecas_previstas TEXT NULL,
    status_anterior VARCHAR(20) NULL,
    status_novo VARCHAR(20) NULL,
    os_status_id INT NOT NULL DEFAULT 1 COMMENT 'FK para status_os (1=cadastrada, 2=executando, 3=realizada, 4=cancelada)',
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_execucao DATETIME NULL,
    created_by INT NULL,
    executado_por INT NULL,
    local_servico VARCHAR(255) NULL,
    endereco_servico VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT fk_alarmes_manutencoes_alarme FOREIGN KEY (alarme_id) REFERENCES central_alarmes(id) ON DELETE CASCADE,
    CONSTRAINT fk_alarmes_manutencoes_usuario FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_alarme_manut_procedimento FOREIGN KEY (procedimento_id) REFERENCES procedimentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_alarme_manut_status FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE SET NULL,
    CONSTRAINT fk_alarme_manut_executado_by FOREIGN KEY (executado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_alarme_manut_os_status FOREIGN KEY (os_status_id) REFERENCES status_os(id),
    CONSTRAINT fk_alarme_manut_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_alarmes_manutencoes_alarme_data (alarme_id, data_hora),
    INDEX idx_alarmes_manutencoes_created_by (created_by),
    INDEX idx_alarme_manut_os_status (os_status_id),
    INDEX idx_alarme_manut_os_status_data_hora (os_status_id, data_hora),
    INDEX idx_alarme_manut_numero_os (numero_os),
    INDEX idx_alarme_manut_status_data_execucao (status_id, data_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manutenções associadas a alarmes';

CREATE INDEX idx_alarme_manut_procedimento ON alarmes_manutencoes(procedimento_id);
CREATE INDEX idx_alarme_manut_executado_por ON alarmes_manutencoes(executado_por);
CREATE INDEX idx_alarme_manut_tecnico ON alarmes_manutencoes(tecnico_id);
CREATE FULLTEXT INDEX ftx_alarme_manut_texto ON alarmes_manutencoes(descricao, problemas, pecas_previstas);

-- =====================================================
-- CONTROLE DE TENTATIVAS DE LOGIN (FORÇA BRUTA)
-- =====================================================
CREATE TABLE login_attempts (
    username VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    first_attempt_at DATETIME NULL,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (username, ip_address),
    INDEX idx_login_attempts_locked_until (locked_until),
    INDEX idx_login_attempts_updated_at (updated_at),
    CONSTRAINT ck_login_attempts_count CHECK (attempt_count >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de tentativas de login por usuário/IP';

-- =====================================================
-- TIPOS DE OPERACAO (substitui ENUM da auditoria)
-- =====================================================
CREATE TABLE tipos_operacao (
    id TINYINT PRIMARY KEY,
    nome VARCHAR(20) UNIQUE
) ENGINE=InnoDB COMMENT='Tipos de operacao para auditoria (INSERT, UPDATE, DELETE)';

INSERT INTO tipos_operacao(id, nome) VALUES
(1,'INSERT'), (2,'UPDATE'), (3,'DELETE');

-- =====================================================
-- AUDITORIA
-- =====================================================
CREATE TABLE auditoria_eventos (
    id BIGINT AUTO_INCREMENT,
    entidade VARCHAR(80) NOT NULL COMMENT 'Nome da tabela',
    entidade_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do registro',
    operacao_id TINYINT NOT NULL COMMENT '1=INSERT, 2=UPDATE, 3=DELETE',
    dados_antes JSON NULL COMMENT 'Estado anterior (para UPDATE/DELETE)',
    dados_depois JSON NULL COMMENT 'Novo estado (para INSERT/UPDATE)',
    origem VARCHAR(100) NULL COMMENT 'Origem da ação',
    changed_by INT NULL COMMENT 'ID do usuário',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, created_at),
    INDEX idx_auditoria_operacao (operacao_id),
    INDEX idx_auditoria_usuario (changed_by),
    INDEX idx_auditoria_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoria particionada por mês'
PARTITION BY RANGE (TO_DAYS(created_at)) (
    PARTITION p2025_01 VALUES LESS THAN (TO_DAYS('2025-02-01')),
    PARTITION p2025_02 VALUES LESS THAN (TO_DAYS('2025-03-01')),
    PARTITION p2025_03 VALUES LESS THAN (TO_DAYS('2025-04-01')),
    PARTITION p2025_04 VALUES LESS THAN (TO_DAYS('2025-05-01')),
    PARTITION p2025_05 VALUES LESS THAN (TO_DAYS('2025-06-01')),
    PARTITION p2025_06 VALUES LESS THAN (TO_DAYS('2025-07-01')),
    PARTITION p2025_07 VALUES LESS THAN (TO_DAYS('2025-08-01')),
    PARTITION p2025_08 VALUES LESS THAN (TO_DAYS('2025-09-01')),
    PARTITION p2025_09 VALUES LESS THAN (TO_DAYS('2025-10-01')),
    PARTITION p2025_10 VALUES LESS THAN (TO_DAYS('2025-11-01')),
    PARTITION p2025_11 VALUES LESS THAN (TO_DAYS('2025-12-01')),
    PARTITION p2025_12 VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    PARTITION p2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')),
    PARTITION p2026_05 VALUES LESS THAN (TO_DAYS('2026-06-01')),
    PARTITION p2026_06 VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

CREATE INDEX idx_audit_entidade_data ON auditoria_eventos(entidade, created_at);
CREATE INDEX idx_audit_entidade_operacao_data ON auditoria_eventos(entidade, operacao_id, created_at);

-- =====================================================
-- BUFFER DE AUDITORIA (INSERT leve → flush periódico)
-- =====================================================
CREATE TABLE auditoria_buffer (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Buffer para inserts de auditoria — flush periódico para auditoria_eventos';

-- Stored procedure para flush do buffer → tabela principal
DELIMITER $$
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
END$$
DELIMITER ;

-- Event de flush: roda a cada 10 segundos
CREATE EVENT evt_flush_auditoria_buffer
ON SCHEDULE EVERY 10 SECOND
DO
    CALL sp_flush_auditoria_buffer();

-- =====================================================
-- CONFIGURAÇÕES DO SISTEMA
-- =====================================================
CREATE TABLE configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT,
    descricao VARCHAR(255),
    modificado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modificado_por INT NULL,
    CONSTRAINT fk_config_user FOREIGN KEY (modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Configurações gerais do sistema';

CREATE INDEX idx_config_modificado_por ON configuracoes(modificado_por);

INSERT INTO configuracoes (chave, valor, descricao) VALUES
('versao_sistema', '1.1.0', 'Versão atual do sistema'),
('manutencao', '0', 'Modo de manutenção ativo? (1=sim, 0=não)'),
('timeout_sessao', '3600', 'Tempo de sessão em segundos'),
('itens_por_pagina', '50', 'Itens padrão por página nas listagens');


-- =====================================================
-- =====================================================
-- RATE LIMITS (fallback via DB quando storage/ratelimit nao disponivel)
-- =====================================================
CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(64) PRIMARY KEY COMMENT 'SHA-256 hash da chave de rate limit',
    tokens DOUBLE NOT NULL DEFAULT 0 COMMENT 'Tokens disponiveis (token bucket)',
    updated_at INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Timestamp Unix da ultima atualizacao',
    timestamps JSON NULL COMMENT 'Array de timestamps para sliding window'
) ENGINE=InnoDB;

-- =====================================================
-- SEQUÊNCIA PARA GERAÇÃO DE CÓDIGO PÚBLICO
-- =====================================================
CREATE TABLE sequencia_codigo_publico (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB COMMENT='Sequência para geração de código público dos equipamentos';

DELIMITER $$

CREATE FUNCTION proximo_codigo_equipamento()
RETURNS VARCHAR(40)
NOT DETERMINISTIC
MODIFIES SQL DATA
BEGIN
    DECLARE seq INT;
    INSERT INTO sequencia_codigo_publico () VALUES ();
    SET seq = LAST_INSERT_ID();
    DELETE FROM sequencia_codigo_publico WHERE id <= seq - 1000;
    RETURN CONCAT('EQ-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(seq, 6, '0'));
END$$

-- =====================================================
-- TRIGGERS DE AUDITORIA (EQUIPAMENTOS)
-- IMPORTANTE: As triggers usam @app_user_id e @app_origem.
-- Antes de qualquer INSERT/UPDATE/DELETE nas tabelas monitoradas,
-- a aplicacao DEVE setar:
--   SET @app_user_id = <id_do_usuario>;
--   SET @app_origem = '<origem>';
-- Se nao forem setados, os campos changed_by/origem ficarao NULL.
-- =====================================================
CREATE TRIGGER trg_equipamentos_bi
BEFORE INSERT ON equipamentos
FOR EACH ROW
BEGIN
    IF NEW.codigo_publico IS NULL THEN
        SET NEW.codigo_publico = proximo_codigo_equipamento();
    END IF;
END$$

CREATE TRIGGER trg_equipamentos_ai
AFTER INSERT ON equipamentos
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'equipamentos', NEW.id, 1, NULL,
        JSON_OBJECT(
            'id', NEW.id,
            'codigo_publico', NEW.codigo_publico,
            'tipo_equipamento_id', NEW.tipo_equipamento_id,
            'tipo_camera_id', NEW.tipo_camera_id,
            'status_id', NEW.status_id,
            'procedimento_id', NEW.procedimento_id,
            'regiao_id', NEW.regiao_id,
            'local_id', NEW.local_id,
            'secretaria_id', NEW.secretaria_id,
            'marca_id', NEW.marca_id,
            'modelo_id', NEW.modelo_id,
            'patrimonio', NEW.patrimonio,
            'numero_serie', NEW.numero_serie,
            'ip', NEW.ip,
            'porta', NEW.porta,
            'transmissao_id', NEW.transmissao_id,
            'origem_link_id', NEW.origem_link_id,
            'data_instalacao', NEW.data_instalacao,
            'observacao', NEW.observacao
        ),
        @app_user_id, @app_origem
    );

    INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
    VALUES (NEW.id, NEW.status_id, 'Cadastro inicial', @app_user_id);
END$$

CREATE TRIGGER trg_equipamentos_au
AFTER UPDATE ON equipamentos
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'equipamentos', NEW.id, 2,
        JSON_OBJECT(
            'status_id', OLD.status_id,
            'tipo_camera_id', OLD.tipo_camera_id,
            'procedimento_id', OLD.procedimento_id,
            'regiao_id', OLD.regiao_id,
            'local_id', OLD.local_id,
            'secretaria_id', OLD.secretaria_id,
            'marca_id', OLD.marca_id,
            'modelo_id', OLD.modelo_id,
            'patrimonio', OLD.patrimonio,
            'numero_serie', OLD.numero_serie,
            'ip', OLD.ip,
            'porta', OLD.porta,
            'transmissao_id', OLD.transmissao_id,
            'origem_link_id', OLD.origem_link_id,
            'data_instalacao', OLD.data_instalacao,
            'observacao', OLD.observacao
        ),
        JSON_OBJECT(
            'status_id', NEW.status_id,
            'tipo_camera_id', NEW.tipo_camera_id,
            'procedimento_id', NEW.procedimento_id,
            'regiao_id', NEW.regiao_id,
            'local_id', NEW.local_id,
            'secretaria_id', NEW.secretaria_id,
            'marca_id', NEW.marca_id,
            'modelo_id', NEW.modelo_id,
            'patrimonio', NEW.patrimonio,
            'numero_serie', NEW.numero_serie,
            'ip', NEW.ip,
            'porta', NEW.porta,
            'transmissao_id', NEW.transmissao_id,
            'origem_link_id', NEW.origem_link_id,
            'data_instalacao', NEW.data_instalacao,
            'observacao', NEW.observacao
        ),
        @app_user_id, @app_origem
    );

    IF NOT (NEW.status_id <=> OLD.status_id) THEN
        INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
        VALUES (NEW.id, NEW.status_id, 'Alteracao de status', @app_user_id);
    END IF;
END$$

CREATE TRIGGER trg_equipamentos_bd
BEFORE DELETE ON equipamentos
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'equipamentos', OLD.id, 3,
        JSON_OBJECT(
            'id', OLD.id,
            'codigo_publico', OLD.codigo_publico,
            'tipo_equipamento_id', OLD.tipo_equipamento_id,
            'tipo_camera_id', OLD.tipo_camera_id,
            'status_id', OLD.status_id,
            'procedimento_id', OLD.procedimento_id,
            'regiao_id', OLD.regiao_id,
            'local_id', OLD.local_id,
            'secretaria_id', OLD.secretaria_id,
            'marca_id', OLD.marca_id,
            'modelo_id', OLD.modelo_id,
            'patrimonio', OLD.patrimonio,
            'numero_serie', OLD.numero_serie,
            'ip', OLD.ip,
            'porta', OLD.porta,
            'transmissao_id', OLD.transmissao_id,
            'origem_link_id', OLD.origem_link_id,
            'data_instalacao', OLD.data_instalacao,
            'observacao', OLD.observacao
        ),
        NULL,
        @app_user_id, @app_origem
    );
END$$

-- =====================================================
-- TRIGGERS DE AUDITORIA PARA USUÁRIOS
-- =====================================================
CREATE TRIGGER trg_usuarios_ai
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('usuarios', NEW.id, 1, NULL,
        JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_usuarios_au
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('usuarios', NEW.id, 2,
        JSON_OBJECT('nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
        JSON_OBJECT('nome', NEW.nome, 'usuario', NEW.usuario, 'nivel_acesso_id', NEW.nivel_acesso_id, 'ativo', NEW.ativo),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_usuarios_bd
BEFORE DELETE ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('usuarios', OLD.id, 3,
        JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'usuario', OLD.usuario, 'nivel_acesso_id', OLD.nivel_acesso_id, 'ativo', OLD.ativo),
        NULL, @app_user_id, @app_origem);
END$$

-- =====================================================
-- TRIGGERS DE AUDITORIA PARA SECRETARIAS
-- =====================================================
CREATE TRIGGER trg_secretarias_ai
AFTER INSERT ON secretarias
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('secretarias', NEW.id, 1, NULL,
        JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_secretarias_au
AFTER UPDATE ON secretarias
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('secretarias', NEW.id, 2,
        JSON_OBJECT('nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
        JSON_OBJECT('nome', NEW.nome, 'sigla', NEW.sigla, 'tipo_id', NEW.tipo_id, 'ativo', NEW.ativo),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_secretarias_bd
BEFORE DELETE ON secretarias
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('secretarias', OLD.id, 3,
        JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'sigla', OLD.sigla, 'tipo_id', OLD.tipo_id, 'ativo', OLD.ativo),
        NULL, @app_user_id, @app_origem);
END$$

-- =====================================================
-- TRIGGERS DE ANTI-DUPLICIDADE PARA LOCAIS (BEFORE INSERT/UPDATE)
-- =====================================================
-- O indice UNIQUE (nome, secretaria_id) NAO cobre o caso secretaria_id NULL,
-- pois o MySQL trata cada NULL como distinto em indices UNIQUE. Estes triggers
-- fecham essa brecha validando duplicidade incluindo NULL <=> NULL, e tambem
-- protegem contra race condition (duplo submit) e bypass da aplicacao.
-- Ignoram linhas soft-deletadas (deleted_at IS NULL).
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
END$$

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
END$$

-- =====================================================
-- TRIGGERS DE AUDITORIA PARA LOCAIS
-- =====================================================
CREATE TRIGGER trg_locais_ai
AFTER INSERT ON locais
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('locais', NEW.id, 1, NULL,
        JSON_OBJECT('id', NEW.id, 'nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_locais_au
AFTER UPDATE ON locais
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('locais', NEW.id, 2,
        JSON_OBJECT('nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
        JSON_OBJECT('nome', NEW.nome, 'logradouro', NEW.logradouro, 'bairro', NEW.bairro, 'cidade', NEW.cidade, 'uf', NEW.uf, 'cep', NEW.cep, 'numero', NEW.numero, 'descricao_posicao', NEW.descricao_posicao, 'secretaria_id', NEW.secretaria_id, 'alarme_conta', NEW.alarme_conta),
        @app_user_id, @app_origem);
END$$

CREATE TRIGGER trg_locais_bd
BEFORE DELETE ON locais
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem)
    VALUES ('locais', OLD.id, 3,
        JSON_OBJECT('id', OLD.id, 'nome', OLD.nome, 'logradouro', OLD.logradouro, 'bairro', OLD.bairro, 'cidade', OLD.cidade, 'uf', OLD.uf, 'cep', OLD.cep, 'numero', OLD.numero, 'descricao_posicao', OLD.descricao_posicao, 'secretaria_id', OLD.secretaria_id, 'alarme_conta', OLD.alarme_conta),
        NULL, @app_user_id, @app_origem);
END$$

-- =====================================================
-- TRIGGERS DE AUDITORIA (CENTRAL DE ALARME)
-- =====================================================
CREATE TRIGGER trg_central_alarmes_ai
AFTER INSERT ON central_alarmes
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'central_alarmes', NEW.id, 1, NULL,
        JSON_OBJECT(
            'id', NEW.id,
            'regiao', NEW.regiao,
            'regiao_id', NEW.regiao_id,
            'conta', NEW.conta,
            'status', NEW.status,
            'status_id', NEW.status_id,
            'local', NEW.local,
            'endereco', NEW.endereco,
            'numero', NEW.numero,
            'pgm1', NEW.pgm1,
            'pgm2', NEW.pgm2,
            'mac', NEW.mac,
            'ip', NEW.ip,
            'integracao', NEW.integracao,
            'camera_gm', NEW.camera_gm,
            'quant_camera_gm', NEW.quant_camera_gm,
            'ip_dvr', NEW.ip_dvr,
            'cameras_dvr', NEW.cameras_dvr,
            'modelo_alarme_id', NEW.modelo_alarme_id,
            'quant_repetidor', NEW.quant_repetidor,
            'qtde_sensores', NEW.qtde_sensores,
            'documentacao', NEW.documentacao,
            'monitorada', NEW.monitorada,
            'numero_sei', NEW.numero_sei,
            'data_atualizacao', NEW.data_atualizacao,
            'observacao', NEW.observacao
        ),
        @app_user_id, @app_origem
    );
END$$

CREATE TRIGGER trg_central_alarmes_au
AFTER UPDATE ON central_alarmes
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'central_alarmes', NEW.id, 2,
        JSON_OBJECT(
            'regiao', OLD.regiao,
            'regiao_id', OLD.regiao_id,
            'conta', OLD.conta,
            'status', OLD.status,
            'status_id', OLD.status_id,
            'local', OLD.local,
            'endereco', OLD.endereco,
            'numero', OLD.numero,
            'pgm1', OLD.pgm1,
            'pgm2', OLD.pgm2,
            'mac', OLD.mac,
            'ip', OLD.ip,
            'integracao', OLD.integracao,
            'camera_gm', OLD.camera_gm,
            'quant_camera_gm', OLD.quant_camera_gm,
            'ip_dvr', OLD.ip_dvr,
            'cameras_dvr', OLD.cameras_dvr,
            'modelo_alarme_id', OLD.modelo_alarme_id,
            'quant_repetidor', OLD.quant_repetidor,
            'qtde_sensores', OLD.qtde_sensores,
            'documentacao', OLD.documentacao,
            'monitorada', OLD.monitorada,
            'numero_sei', OLD.numero_sei,
            'data_atualizacao', OLD.data_atualizacao,
            'observacao', OLD.observacao
        ),
        JSON_OBJECT(
            'regiao', NEW.regiao,
            'regiao_id', NEW.regiao_id,
            'conta', NEW.conta,
            'status', NEW.status,
            'status_id', NEW.status_id,
            'local', NEW.local,
            'endereco', NEW.endereco,
            'numero', NEW.numero,
            'pgm1', NEW.pgm1,
            'pgm2', NEW.pgm2,
            'mac', NEW.mac,
            'ip', NEW.ip,
            'integracao', NEW.integracao,
            'camera_gm', NEW.camera_gm,
            'quant_camera_gm', NEW.quant_camera_gm,
            'ip_dvr', NEW.ip_dvr,
            'cameras_dvr', NEW.cameras_dvr,
            'modelo_alarme_id', NEW.modelo_alarme_id,
            'quant_repetidor', NEW.quant_repetidor,
            'qtde_sensores', NEW.qtde_sensores,
            'documentacao', NEW.documentacao,
            'monitorada', NEW.monitorada,
            'numero_sei', NEW.numero_sei,
            'data_atualizacao', NEW.data_atualizacao,
            'observacao', NEW.observacao
        ),
        @app_user_id, @app_origem
    );
END$$

CREATE TRIGGER trg_central_alarmes_bd
BEFORE DELETE ON central_alarmes
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'central_alarmes', OLD.id, 3,
        JSON_OBJECT(
            'id', OLD.id,
            'regiao', OLD.regiao,
            'regiao_id', OLD.regiao_id,
            'conta', OLD.conta,
            'status', OLD.status,
            'status_id', OLD.status_id,
            'local', OLD.local,
            'endereco', OLD.endereco,
            'numero', OLD.numero,
            'pgm1', OLD.pgm1,
            'pgm2', OLD.pgm2,
            'mac', OLD.mac,
            'ip', OLD.ip,
            'integracao', OLD.integracao,
            'camera_gm', OLD.camera_gm,
            'quant_camera_gm', OLD.quant_camera_gm,
            'ip_dvr', OLD.ip_dvr,
            'cameras_dvr', OLD.cameras_dvr,
            'modelo_alarme_id', OLD.modelo_alarme_id,
            'quant_repetidor', OLD.quant_repetidor,
            'qtde_sensores', OLD.qtde_sensores,
            'documentacao', OLD.documentacao,
            'monitorada', OLD.monitorada,
            'numero_sei', OLD.numero_sei,
            'data_atualizacao', OLD.data_atualizacao,
            'observacao', OLD.observacao
        ),
        NULL,
        @app_user_id, @app_origem
    );
END$$

-- =====================================================
-- TRIGGERS: AUDITORIA DE MANUTENCOES DE ALARMES
-- =====================================================

DELIMITER $$

CREATE TRIGGER trg_alarmes_manutencoes_ai
AFTER INSERT ON alarmes_manutencoes
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'alarmes_manutencoes', NEW.id, 1, NULL,
        JSON_OBJECT(
            'id', NEW.id,
            'alarme_id', NEW.alarme_id,
            'tecnico', NEW.tecnico,
            'tecnico_id', NEW.tecnico_id,
            'descricao', NEW.descricao,
            'numero_os', NEW.numero_os,
            'problemas', NEW.problemas,
            'procedimento_id', NEW.procedimento_id,
            'status_id', NEW.status_id,
            'os_status_id', NEW.os_status_id,
            'data_hora', NEW.data_hora,
            'data_execucao', NEW.data_execucao,
            'created_by', NEW.created_by,
            'executado_por', NEW.executado_por,
            'local_servico', NEW.local_servico,
            'endereco_servico', NEW.endereco_servico
        ),
        @app_user_id, @app_origem
    );
END$$

CREATE TRIGGER trg_alarmes_manutencoes_au
AFTER UPDATE ON alarmes_manutencoes
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_buffer (
        entidade, entidade_id, operacao_id, dados_antes, dados_depois, changed_by, origem
    ) VALUES (
        'alarmes_manutencoes', NEW.id, 2,
        JSON_OBJECT(
            'alarme_id', OLD.alarme_id,
            'tecnico', OLD.tecnico,
            'tecnico_id', OLD.tecnico_id,
            'descricao', OLD.descricao,
            'numero_os', OLD.numero_os,
            'problemas', OLD.problemas,
            'procedimento_id', OLD.procedimento_id,
            'status_id', OLD.status_id,
            'os_status_id', OLD.os_status_id,
            'data_hora', OLD.data_hora,
            'data_execucao', OLD.data_execucao,
            'created_by', OLD.created_by,
            'executado_por', OLD.executado_por,
            'local_servico', OLD.local_servico,
            'endereco_servico', OLD.endereco_servico
        ),
        JSON_OBJECT(
            'alarme_id', NEW.alarme_id,
            'tecnico', NEW.tecnico,
            'tecnico_id', NEW.tecnico_id,
            'descricao', NEW.descricao,
            'numero_os', NEW.numero_os,
            'problemas', NEW.problemas,
            'procedimento_id', NEW.procedimento_id,
            'status_id', NEW.status_id,
            'os_status_id', NEW.os_status_id,
            'data_hora', NEW.data_hora,
            'data_execucao', NEW.data_execucao,
            'created_by', NEW.created_by,
            'executado_por', NEW.executado_por,
            'local_servico', NEW.local_servico,
            'endereco_servico', NEW.endereco_servico
        ),
        @app_user_id, @app_origem
    );
END$$

-- =====================================================
-- PROCEDURE PARA LIMPEZA DE LOGS (por particionamento)
-- =====================================================
-- Para tabelas particionadas, DROP PARTITION é O(1) em vez de DELETE em loop.
-- Esta procedure identifica partições antigas e as remove.
CREATE PROCEDURE sp_limpar_auditoria_antiga(IN dias INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE part_name VARCHAR(64);
    DECLARE part_limit DATE;
    DECLARE cutoff DATE;

    -- Cursor: partições com data de corte menor que o cutoff
    -- Obs.: todos os DECLAREs devem vir antes de qualquer instrução executável.
    DECLARE cur CURSOR FOR
        SELECT PARTITION_NAME, PARTITION_DESCRIPTION
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'auditoria_eventos'
          AND PARTITION_NAME IS NOT NULL
          AND PARTITION_NAME != 'p_future'
          AND CAST(PARTITION_DESCRIPTION AS UNSIGNED) < TO_DAYS(DATE_SUB(CURDATE(), INTERVAL dias DAY));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- IMPORTANTE: no MySQL/MariaDB todas as declaracoes DECLARE (variaveis,
    -- cursores e handlers) devem vir ANTES de qualquer instrucao executavel
    -- (SET, IF, etc). Por isso o SET cutoff fica apos o CONTINUE HANDLER.

    SET cutoff = DATE_SUB(CURDATE(), INTERVAL dias DAY);

    OPEN cur;
    loop_partitions: LOOP
        FETCH cur INTO part_name, part_limit;
        IF done THEN
            LEAVE loop_partitions;
        END IF;

        -- PREPARE exige variavel de usuario (@var), nao variavel local
        SET @sql_stmt = CONCAT('ALTER TABLE auditoria_eventos DROP PARTITION `', part_name, '`');
        PREPARE stmt FROM @sql_stmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;

    SELECT CONCAT('Partições anteriores a ', cutoff, ' removidas via DROP PARTITION') AS resultado;
END$$

CREATE PROCEDURE sp_limpar_login_attempts_antigos(IN dias INT)
BEGIN
    DELETE FROM login_attempts
    WHERE updated_at < DATE_SUB(NOW(), INTERVAL dias DAY)
      AND (locked_until IS NULL OR locked_until < NOW());
END$$

-- Adiciona partições mensais futuras de auditoria_eventos (pruning eficiente).
-- Chama antes da limpeza: REORGANIZE divide p_future (MAXVALUE) criando a
-- partição do próximo mês e mantendo p_future ao final.
CREATE PROCEDURE sp_manter_particoes_auditoria(IN meses_futuros INT)
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE target DATE;
    DECLARE part_name VARCHAR(64);

    WHILE i <= meses_futuros DO
        SET target = DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL i MONTH);
        SET part_name = CONCAT('p', DATE_FORMAT(target, '%Y_%m'));

        IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.PARTITIONS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'auditoria_eventos'
              AND PARTITION_NAME = part_name
        ) THEN
            -- PREPARE exige variavel de usuario (@var), nao variavel local
            SET @sql_stmt = CONCAT(
                'ALTER TABLE auditoria_eventos REORGANIZE PARTITION p_future INTO (',
                'PARTITION `', part_name, '` VALUES LESS THAN (TO_DAYS(',
                QUOTE(DATE_FORMAT(DATE_ADD(target, INTERVAL 1 MONTH), '%Y-%m-01')),
                ')), PARTITION p_future VALUES LESS THAN MAXVALUE)'
            );
            PREPARE stmt FROM @sql_stmt;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;

        SET i = i + 1;
    END WHILE;
END$$

-- =====================================================
-- EVENTOS AGENDADOS
-- =====================================================
CREATE EVENT ev_limpeza_auditoria_mensal
ON SCHEDULE EVERY 1 MONTH
DO
BEGIN
    CALL sp_manter_particoes_auditoria(3); -- cria partições dos próximos 3 meses
    CALL sp_limpar_auditoria_antiga(730); -- remove registros com mais de 2 anos
END$$

-- OPTIMIZE TABLE removido: InnoDB moderno ja faz otimizacoes automaticas.

CREATE EVENT ev_limpeza_login_attempts
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    CALL sp_limpar_login_attempts_antigos(30);
END$$

-- Para executar os eventos agendados, o scheduler deve estar ativo no MySQL:
--   SET GLOBAL event_scheduler = ON;
-- Os docker-compose (dev e prod) ja habilitam via command: --event-scheduler=ON.

DELIMITER ;

-- =====================================================
-- VIEWS ANALÍTICAS
-- =====================================================
CREATE OR REPLACE VIEW vw_equipamentos_analitico AS
SELECT
    e.id,
    e.codigo_publico,
    te.nome AS tipo_equipamento,
    s.nome AS status,
    p.nome AS procedimento,
    r.nome AS regiao,
    sec.nome AS secretaria,
    sec.sigla AS secretaria_sigla,
    ts.nome AS secretaria_tipo,
    l.nome AS local,
    tl.nome AS tipo_local,
    ce.descricao AS classificacao_endereco,

    m.nome AS marca,
    cm.nome AS modelo,
    e.ip,
    e.porta,
    el.url_acesso,
    e.patrimonio,
    e.numero_serie,
    tr.tipo AS tipo_transmissao,
    ol.nome AS origem_link,
    e.data_instalacao,
    e.created_at,
    e.updated_at
FROM equipamentos e
LEFT JOIN tipos_equipamento te ON te.id = e.tipo_equipamento_id
LEFT JOIN status s ON s.id = e.status_id
LEFT JOIN procedimentos p ON p.id = e.procedimento_id
LEFT JOIN regioes r ON r.id = e.regiao_id
LEFT JOIN secretarias sec ON sec.id = e.secretaria_id
LEFT JOIN tipos_secretaria ts ON ts.id = sec.tipo_id
LEFT JOIN locais l ON l.id = e.local_id
LEFT JOIN tipos_locais tl ON tl.id = l.tipo_local_id
LEFT JOIN classificacao_enderecos ce ON ce.id = l.classificacao_endereco_id
LEFT JOIN marcas m ON m.id = e.marca_id
LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
LEFT JOIN equipamentos_lpr el ON el.equipamento_id = e.id
LEFT JOIN transmissoes tr ON tr.id = e.transmissao_id
LEFT JOIN origem_link ol ON ol.id = e.origem_link_id
WHERE e.deleted_at IS NULL; -- filtrar soft delete

CREATE OR REPLACE VIEW vw_equipamentos_completo AS
SELECT
    v.*,
    ec.mosaico,
    ec.coordenadas,
    ec.numero_ruas,
    el.sentido_via,
    el.faixa_monitorada,
    el.leitura_noturna,
    ed.canais,
    ed.armazenamento_tb,
    et.altura_metros
FROM vw_equipamentos_analitico v
LEFT JOIN equipamentos_camera ec ON ec.equipamento_id = v.id
LEFT JOIN equipamentos_lpr el ON el.equipamento_id = v.id
LEFT JOIN equipamentos_dvr ed ON ed.equipamento_id = v.id
LEFT JOIN equipamentos_totem et ON et.equipamento_id = v.id;

CREATE OR REPLACE VIEW vw_qtd_equipamentos_por_status AS
SELECT
    s.nome AS status,
    COUNT(e.id) AS total
FROM status s
LEFT JOIN equipamentos e ON e.status_id = s.id AND e.deleted_at IS NULL
GROUP BY s.nome;

CREATE OR REPLACE VIEW vw_equipamentos_por_tipo AS
SELECT
    te.nome AS tipo,
    COUNT(e.id) AS total
FROM tipos_equipamento te
LEFT JOIN equipamentos e ON e.tipo_equipamento_id = te.id AND e.deleted_at IS NULL
GROUP BY te.nome;

CREATE OR REPLACE VIEW vw_equipamentos_por_secretaria AS
SELECT
    sec.nome AS secretaria,
    sec.sigla,
    ts.nome AS tipo,
    COUNT(e.id) AS total_equipamentos
FROM secretarias sec
LEFT JOIN tipos_secretaria ts ON ts.id = sec.tipo_id
LEFT JOIN equipamentos e ON e.secretaria_id = sec.id AND e.deleted_at IS NULL
GROUP BY sec.id, sec.nome, sec.sigla, ts.nome;

CREATE OR REPLACE VIEW vw_equipamentos_por_tipo_local AS
SELECT
    tl.id AS tipo_local_id,
    tl.nome AS tipo_local,
    COUNT(e.id) AS total_equipamentos,
    COUNT(DISTINCT e.secretaria_id) AS total_secretarias,
    COUNT(DISTINCT e.local_id) AS total_locais
FROM tipos_locais tl
LEFT JOIN locais l ON l.tipo_local_id = tl.id
LEFT JOIN equipamentos e ON e.local_id = l.id AND e.deleted_at IS NULL
GROUP BY tl.id, tl.nome
ORDER BY tl.nome;


-- =====================================================
-- TABELA DE ANEXOS (FOTOS / DOCUMENTOS)
-- =====================================================
CREATE TABLE IF NOT EXISTS equipamentos_anexos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id BIGINT UNSIGNED NULL COMMENT 'FK para equipamentos (câmeras)',
    alarme_id INT NULL COMMENT 'FK para central_alarmes',
    manutencao_camera_id BIGINT NULL COMMENT 'FK para equipamentos_manutencoes',
    manutencao_alarme_id BIGINT NULL COMMENT 'FK para alarmes_manutencoes',
    tipo VARCHAR(50) NOT NULL DEFAULT 'foto' COMMENT 'foto, documento, anexo',
    nome_original VARCHAR(255) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL COMMENT 'Nome no disco (hash)',
    caminho VARCHAR(500) NOT NULL,
    conteudo MEDIUMBLOB NULL COMMENT 'Conteudo binario do anexo (armazenamento em BD quando FS e read-only)',
    conteudo_comprimido TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=conteudo esta gzdeflate-comprimido',
    thumbnails JSON NULL COMMENT 'Caminhos dos thumbnails gerados {"small":"...","medium":"..."}',
    mime_type VARCHAR(100) NOT NULL,
    tamanho INT UNSIGNED NOT NULL COMMENT 'Tamanho em bytes',
    descricao VARCHAR(500) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anexos_equip FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_anexos_alarme FOREIGN KEY (alarme_id) REFERENCES central_alarmes(id) ON DELETE CASCADE,
    CONSTRAINT fk_anexos_manut_camera FOREIGN KEY (manutencao_camera_id) REFERENCES equipamentos_manutencoes(id) ON DELETE SET NULL,
    CONSTRAINT fk_anexos_manut_alarme FOREIGN KEY (manutencao_alarme_id) REFERENCES alarmes_manutencoes(id) ON DELETE SET NULL,
    CONSTRAINT fk_anexos_user FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_anexos_equipamento (equipamento_id),
    INDEX idx_anexos_alarme (alarme_id),
    INDEX idx_anexos_manut_camera (manutencao_camera_id),
    INDEX idx_anexos_manut_alarme (manutencao_alarme_id),
    INDEX idx_anexos_created_at (created_at),
    INDEX idx_anexos_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anexos de equipamentos (fotos, documentos)';

-- =====================================================
-- TABELA DE NOTIFICAÇÕES
-- =====================================================
CREATE TABLE IF NOT EXISTS `notificacoes` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `tipo` VARCHAR(50) NOT NULL COMMENT 'Tipo: manutencao_atrasada, etc.',
    `titulo` VARCHAR(255) NOT NULL,
    `mensagem` TEXT NULL,
    `link` VARCHAR(500) NULL COMMENT 'URL para navegar ao contexto',
    `entidade_tipo` VARCHAR(50) NULL COMMENT 'Tipo da entidade (equipamento, alarme, local)',
    `entidade_id` BIGINT NULL COMMENT 'ID da entidade relacionada',
    `lida` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notificacoes_lida` (`lida`),
    KEY `idx_notificacoes_tipo` (`tipo`),
    KEY `idx_notificacoes_criadas` (`created_at` DESC),
    KEY `idx_notificacoes_lida_criadas` (`lida`, `created_at` DESC),
    KEY `idx_notificacoes_lookup` (`tipo`, `entidade_tipo`, `entidade_id`, `lida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA DE MIGRATIONS
-- =====================================================
-- Mantem registro das migrations aplicadas para que o runner
-- database/migrate.php (formato YYYY_MM_DD_HHMMSS_description.php)
-- nao tente reaplicar transforms que ja estao incorporados neste
-- schema (cftv_gml.sql). Em instalacao nova via este script, todas
-- as migrations ja estao aplicadas — populamos a tabela para refletir
-- isso e evitar que migrate.php reexecute DDL que quebraria em
-- duplicidade (CREATE TABLE ja existe, CREATE INDEX ja existe, etc).
--
-- A estrutura espelha a criada por database/migrate.php, para
-- coexistir com o runner em upgrades futuros.
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrations legadas (.sql) — schemas anteriores ao sistema PHP.
-- Todos os objetos ja existem no cftv_gml.sql.
INSERT INTO migrations (filename) VALUES
('001_add_tem_camera_to_locais.sql'),
('002_rename_classificacao_enderecos_nome.sql'),
('003_add_deleted_at_to_locais.sql'),
('004_create_rate_limits_table.sql'),
('005_create_notificacoes_table.sql'),
('006_add_idx_notificacoes_lookup.sql'),
('007_add_alarme_local_id.sql'),
-- Migrations PHP (somente timestamp — formato do runner):
-- A unica migration PHP existente e 2026_07_13_110000 (commit de909e9
-- removeu 2026_07_10_* .. 2026_07_13_100000, cujos objetos ja estao no schema).
('2026_07_13_110000');

-- =====================================================
-- FIM DO SCRIPT
-- =====================================================
