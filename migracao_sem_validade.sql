-- ==============================================
-- MIGRAÇÃO: Adiciona sem_validade para marcar
-- documentos que nunca expiram
-- ==============================================
ALTER TABLE licencas_certidoes ADD COLUMN sem_validade TINYINT(1) NOT NULL DEFAULT 0 AFTER notificado_vencido;
