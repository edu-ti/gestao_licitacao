-- Adiciona coluna tipo_cota na tabela itens_pregoes
ALTER TABLE `itens_pregoes`
    ADD COLUMN `tipo_cota` VARCHAR(30) DEFAULT NULL AFTER `status_motivo`;
