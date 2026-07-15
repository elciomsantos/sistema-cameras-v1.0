-- =====================================================
-- SEED PARA AMBIENTE DE DESENVOLVIMENTO APENAS
-- =====================================================
-- ATENCAO: Nao utilizar em producao!
-- Gere credenciais unicas e fortes para cada ambiente.
--
-- Senhas padrao (DEV apenas):
--   admin     -> @dmln2026! 123456
--   supervisor -> dev@super2026 123456
--   user      -> dev@user2026 123456
-- =====================================================

-- Substitua pela sua senha de banco local
-- DROP USER IF EXISTS 'gmluser'@'localhost';
-- CREATE USER 'gmluser'@'localhost' IDENTIFIED BY 'ykB]Af4|2kHPU#;1g+?J3z)P]=p|AX=R'; senha temporaria para desenvolvimento, altere para uma senha forte e unica em producao
-- GRANT ALL PRIVILEGES ON cftv_gml.* TO 'gmluser'@'localhost';
-- FLUSH PRIVILEGES;

INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, secretaria_id, senha_temporaria, ativo) VALUES
('Administrador Sistema','admin','$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK',1,NULL,0,1),
('Supervisor Sistema','supervisor','$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK',2,NULL,0,1),
('User Sistema','user','$2y$12$3.lPsNIBYzzIJ3ZqVIOtAOZ47d/qo4qf2CtBnrb9bVAIj8axwUBlK',3,NULL,0,1);
