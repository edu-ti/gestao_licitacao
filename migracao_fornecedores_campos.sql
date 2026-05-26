-- Migração: Adiciona campos de endereço e dados cadastrais à tabela fornecedores
-- Executar no banco de dados antes de usar a nova funcionalidade de auto-preenchimento por CNPJ

ALTER TABLE `fornecedores`
    ADD COLUMN `nome_fantasia` VARCHAR(255) DEFAULT NULL AFTER `nome`,
    ADD COLUMN `porte` VARCHAR(50) DEFAULT NULL AFTER `me_epp`,
    ADD COLUMN `endereco` VARCHAR(255) DEFAULT NULL AFTER `porte`,
    ADD COLUMN `bairro` VARCHAR(100) DEFAULT NULL AFTER `endereco`,
    ADD COLUMN `cidade` VARCHAR(100) DEFAULT NULL AFTER `bairro`,
    ADD COLUMN `cep` VARCHAR(10) DEFAULT NULL AFTER `cidade`;
