-- ==============================================
-- MIGRAÇÃO: Adiciona fornecedor_id à tabela licencas_certidoes
-- para vincular documentos/arquivos a uma empresa (fornecedor)
-- ==============================================
ALTER TABLE licencas_certidoes ADD COLUMN fornecedor_id INT(11) DEFAULT NULL AFTER id;
ALTER TABLE licencas_certidoes ADD INDEX idx_fornecedor (fornecedor_id);
