-- ==============================================
-- MIGRAÇÃO: Adiciona coluna lembrete_enviado à tabela pregoes
-- para controlar o envio de lembretes 30 minutos antes da sessão
-- ==============================================
ALTER TABLE pregoes ADD COLUMN lembrete_enviado TINYINT(1) NOT NULL DEFAULT 0;
