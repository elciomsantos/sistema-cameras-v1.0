-- Migration 006: Adiciona indice composto em notificacoes para otimizar jaExiste()
-- Query alvo: WHERE tipo = ? AND entidade_tipo = ? AND entidade_id = ? AND lida = 0
-- Consultada a cada renderizacao da badge de notificacoes no navbar.

CREATE INDEX `idx_notificacoes_lookup`
    ON `notificacoes` (`tipo`, `entidade_tipo`, `entidade_id`, `lida`);
