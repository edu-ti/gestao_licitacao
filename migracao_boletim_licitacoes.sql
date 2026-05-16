-- ============================================
-- FASE 1: Estrutura do Boletim de Licitações
-- Motor de Captação Automática
-- ============================================

-- 1. TABELA: boletins
-- Agrupa as licitações capturadas em um boletim diário
CREATE TABLE IF NOT EXISTS `boletins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_boletim` date NOT NULL,
  `total_itens` int(11) DEFAULT 0,
  `portais_coletados` varchar(255) DEFAULT NULL COMMENT 'JSON array com portais processados',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_data_boletim` (`data_boletim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELA: boletim_licitacoes
-- Cada licitação individual capturada dos portais
CREATE TABLE IF NOT EXISTS `boletim_licitacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boletim_id` int(11) NOT NULL,
  `orgao` varchar(500) DEFAULT NULL,
  `objeto` text DEFAULT NULL,
  `edital` varchar(255) DEFAULT NULL,
  `numero_processo` varchar(255) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `cidade` varchar(150) DEFAULT NULL,
  `modalidade` varchar(100) DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL,
  `data_abertura` date DEFAULT NULL,
  `valor_estimado` decimal(15,2) DEFAULT NULL,
  `link_detalhes` varchar(1000) DEFAULT NULL,
  `link_edital` varchar(1000) DEFAULT NULL COMMENT 'Link direto para download do PDF do edital',
  `status_badge` varchar(50) DEFAULT 'NOVA',
  `portal_origem` varchar(50) NOT NULL COMMENT 'PNCP, COMPRAS_GOV, PORTAL_COMPRAS_PUBLICAS, BNC, LICITAR_DIGITAL, BLL, LICITANET, PE_INTEGRADO, LICITACOES_E',
  `id_original` varchar(255) DEFAULT NULL COMMENT 'ID da licitação no portal de origem',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_licitacao_origem` (`orgao`(255), `edital`(191), `portal_origem`),
  KEY `idx_boletim` (`boletim_id`),
  KEY `idx_portal_origem` (`portal_origem`),
  KEY `idx_estado` (`estado`),
  KEY `idx_status` (`status_badge`),
  KEY `idx_data_abertura` (`data_abertura`),
  CONSTRAINT `fk_boletim` FOREIGN KEY (`boletim_id`) REFERENCES `boletins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABELA: log_captacao
-- Log das execuções dos crons para auditoria
CREATE TABLE IF NOT EXISTS `log_captacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `portal` varchar(50) NOT NULL,
  `tipo` enum('API','SCRAPING_SIMPLES','SCRAPING_AVANCADO') NOT NULL,
  `status` enum('SUCESSO','ERRO','PARCIAL') NOT NULL,
  `qtd_inserida` int(11) DEFAULT 0,
  `qtd_total` int(11) DEFAULT 0,
  `mensagem` text DEFAULT NULL,
  `iniciado_em` datetime DEFAULT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_portal` (`portal`),
  KEY `idx_log_data` (`iniciado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
