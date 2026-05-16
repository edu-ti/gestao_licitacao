ALTER TABLE boletim_licitacoes
  ADD COLUMN link_edital varchar(1000) DEFAULT NULL COMMENT 'Link direto para download do PDF do edital' AFTER link_detalhes;
