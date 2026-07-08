-- Migration 007: Adiciona coluna alarme_local_id + FK em central_alarmes
-- Objetivo: Estabelecer integridade referencial direta com locais.id
-- Historico: a relacao era apenas via locais.alarme_conta = central_alarmes.conta
-- (coluna INT nao-PK). Agora temos FK explicita para locais(id) ON DELETE SET NULL.
--
-- IMPORTANTE: Para instalar em ambiente existente, executar na ordem:
--   1. ALTER TABLE central_alarmes ADD COLUMN alarme_local_id INT NULL
--      AFTER modelo_alarme_id;
--   2. UPDATE central_alarmes ca
--        JOIN locais l ON l.alarme_conta = ca.conta
--        SET ca.alarme_local_id = l.id
--        WHERE ca.alarme_local_id IS NULL;
--   3. ALTER TABLE central_alarmes
--        ADD CONSTRAINT fk_central_alarmes_local
--        FOREIGN KEY (alarme_local_id) REFERENCES locais(id)
--        ON DELETE SET NULL;
--   4. CREATE INDEX idx_alarmes_local_id ON central_alarmes(alarme_local_id);
--
-- Em instalacoes novas (schema cftv_gml.sql), isso ja vem aplicado.

ALTER TABLE `central_alarmes`
    ADD COLUMN `alarme_local_id` INT NULL
    COMMENT 'FK direta para locais.id (substitui JOIN via alarme_conta)'
    AFTER `modelo_alarme_id`;

UPDATE `central_alarmes` `ca`
    LEFT JOIN `locais` `l` ON `l`.`alarme_conta` = `ca`.`conta`
    SET `ca`.`alarme_local_id` = `l`.`id`
    WHERE `ca`.`alarme_local_id` IS NULL;

ALTER TABLE `central_alarmes`
    ADD CONSTRAINT `fk_central_alarmes_local`
    FOREIGN KEY (`alarme_local_id`) REFERENCES `locais` (`id`)
    ON DELETE SET NULL;

CREATE INDEX `idx_alarmes_local_id` ON `central_alarmes`(`alarme_local_id`);
