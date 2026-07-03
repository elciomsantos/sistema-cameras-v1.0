-- =====================================================
-- SEED PARA AMBIENTE DE DESENVOLVIMENTO APENAS
-- =====================================================
-- ATENCAO: Nao utilizar em producao!
-- Gere credenciais unicas e fortes para cada ambiente.
--
-- Senhas padrao (DEV apenas):
--   admin     -> @dmln2026!
--   supervisor -> dev@super2026
--   user      -> dev@user2026
-- =====================================================

-- Substitua pela sua senha de banco local
-- CREATE USER 'gmluser'@'localhost' IDENTIFIED BY 'SUA_SENHA_AQUI';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON cftv_gml.* TO 'gmluser'@'localhost';
-- FLUSH PRIVILEGES;

INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, secretaria_id, senha_temporaria, ativo) VALUES
('Administrador Sistema','admin','$2y$12$9rNpsCjXcFS1K81fFIyG6O4gNNPo54d.KOo5BEEkJUwn1rcVrPSwq',1,NULL,0,1),
('Supervisor Sistema','supervisor','$2y$12$Ypao7hOAXwEK1snB8TqyzOgET/0iw1IS5NR/Z4nF09EutDqLh0Nuy',2,NULL,0,1),
('User Sistema','user','$2y$12$/JrROdnUNp4yzngrSutfLOU5NtCX7ypNyZAM5wzny3dgFxq2tbU3.',3,NULL,0,1);
