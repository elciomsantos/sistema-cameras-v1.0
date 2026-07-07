ALTER TABLE locais
    ADD COLUMN tem_camera TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se o local possui camera (0=Nao, 1=Sim)' AFTER tem_alarme;
