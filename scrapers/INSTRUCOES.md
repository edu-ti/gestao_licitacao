# Fase 3 + Fase 4 — Instruções de Deploy

## FASE 3: cron_scraping_simples.php

### Upload
1. Envie `cron_scraping_simples.php` para a raiz do projeto na Hostinger
2. (Opcional) Rode `upgrade_link_edital.sql` no phpMyAdmin se ainda não fez

### Scrapers incluídos

| Scraper | Portal | Status | Observação |
|---------|--------|--------|------------|
| Licitanet | licitanet.com.br | ✅ Funcional | Extrai SSR JSON da homepage (10+ avisos) |
| BEC-SP | bec.sp.gov.br | ⚠️ Experimental | ASP.NET ViewState; pode precisar de ajuste no POST |

### Execução
```bash
php cron_scraping_simples.php
```
Pode ser agendado no cPanel (cron job) junto com `cron_apis.php`.

### Para adicionar novo scraper
1. Crie a função `coletarNovoPortal()` no `cron_scraping_simples.php`
2. Adicione a entrada no array `$scrapers` no final do arquivo
3. Siga o padrão: recebe `(PDO $pdo, int $boletim_id): int`
4. Use `inserirLicitacoesBatch()` para inserir os dados

---

## FASE 4: Scrapers Python

### Pré-requisitos na Hostinger
Verifique se o Python 3 está disponível:
```bash
python3 --version
pip3 --version
```

### Instalação
```bash
# 1. Acessar a pasta do projeto
cd ~/domains/seudominio/public_html

# 2. Instalar dependências Python
pip3 install -r scrapers/requirements.txt

# 3. Instalar Playwright e seus browsers
python3 -m playwright install chromium
```

### Execução
```bash
# PE Integrado
python3 scrapers/pe_integrado.py

# Licitações-e
python3 scrapers/licitacoes_e.py
```

### Scrapers incluídos

| Scraper | Portal | Status | Observação |
|---------|--------|--------|------------|
| PE Integrado | peintegrado.pe.gov.br | ⚠️ Experimental | reCAPTCHA v3 pode exigir ajuste no seletor do grid |
| Licitações-e | licitacoes-e.com.br | ⚠️ Experimental | Pode bloquear IPs estrangeiros (GeoIP); Hostinger é Brasil ✅ |

### Agendamento (cron job)
No cPanel, adicione algo como:
```
# Fase 3 - Scraping simples (30 min após APIs)
30 6 * * * /usr/bin/php /home/u540193243/domains/frpe.app.br/public_html/cron_scraping_simples.php

# Fase 4 - Scraping avançado (1h após Fase 3)
0 7 * * * cd /home/u540193243/domains/frpe.app.br/public_html && python3 scrapers/pe_integrado.py >> scrapers/log_pe.txt 2>&1
30 7 * * * cd /home/u540193243/domains/frpe.app.br/public_html && python3 scrapers/licitacoes_e.py >> scrapers/log_lic_e.txt 2>&1
```

---

## Observações sobre ajustes finos

### Licitanet
- **Funciona sem alterações** ✅
- Se o seletor `data-page` mudar no futuro, ajuste o regex em `coletarLicitanet()`
- O campo `type` retorna: SUSPENSÃO, REVOGAÇÃO, REABERTURA, CANCELAMENTO — filtro implementado no normalizador

### BEC-SP
- ASP.NET WebForms exige ViewState/EventValidation para navegação
- O POST com `__EVENTTARGET=lnkPregao` retornou mesma página nos testes locais
- **Pode funcionar da Hostinger** devido a diferenças de IP/sessão
- Se não funcionar, remova do array `$scrapers` ou marque `'ativa' => false`

### PE Integrado
- Kendo Grid + ASP.NET — seletor `[data-role="grid"]` pode variar
- reCAPTCHA v3 (invisível): script usa `add_init_script` para esconder automação
- Se CAPTCHA bloquear: troque `headless=True` por `headless=False` e resolva manualmente na primeira execução

### Licitações-e
- Bloqueio por GeoIP: **Hostinger tem IP brasileiro**, deve funcionar ✅
- Selecionei `frmListaAvisosRelacionados.do` como URL primária; se mudar, atualize a lista
- Se exigir login, o script tenta URLs alternativas automaticamente
- Tabela de resultados: seletores `<table tr>` — pode precisar ajustar índice das colunas

### mysql-connector vs pymysql
O `requirements.txt` usa `pymysql`. Se a Hostinger tiver `mysql-connector-python` instalado, você pode trocar. O `db_utils.py` usa `pymysql`.
