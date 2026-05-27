-- ==============================================
-- MIGRAÇÃO: Adiciona notificado_vencido para
-- controlar alertas de licencas ja vencidas
-- ==============================================
ALTER TABLE licencas_certidoes ADD COLUMN notificado_vencido TINYINT(1) NOT NULL DEFAULT 0 AFTER notificado;
