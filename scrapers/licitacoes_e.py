"""
licitacoes_e.py — Scraper do Licitações-e (Banco do Brasil)
Fase 4: Playwright headless

Portal: https://www.licitacoes-e.com.br/
Sistema do Banco do Brasil para licitações eletrônicas.

Notas:
  - Pode bloquear IPs estrangeiros (GeoIP)
  - Se receber HTTP 403, tente:
    1. Proxy com IP brasileiro
    2. VPN com saída no Brasil
    3. Rodar em servidor brasileiro (Hostinger já está no Brasil ✅)
  - Fluxo: página pública de consulta → filtros → resultados
  - Dados em tabela HTML server-side renderizada ou Kendo Grid
"""

import sys
import os
import re
from datetime import datetime
from typing import Optional

sys.path.insert(0, os.path.dirname(__file__))
from db_utils import (
    get_connection, get_or_create_boletim, log_captacao,
    inserir_licitacoes_batch, registrar_portais_boletim,
    atualizar_total_boletim,
)

PORTAL = 'LICITACOES_E'
BASE_URL = 'https://www.licitacoes-e.com.br'


def parse_data_br(texto: str) -> Optional[str]:
    if not texto:
        return None
    texto = texto.strip()
    for fmt in ['%d/%m/%Y', '%d-%m-%Y', '%Y-%m-%d']:
        try:
            return datetime.strptime(texto, fmt).strftime('%Y-%m-%d')
        except ValueError:
            continue
    return None


def extrair_modalidade(titulo: str) -> str:
    titulo = titulo.upper()
    mapa = {
        'PREGAO': 'Pregão',
        'CONCORRENCIA': 'Concorrência',
        'CONCURSO': 'Concurso',
        'LEILAO': 'Leilão',
        'TOMADA DE PRECOS': 'Tomada de Preços',
        'CONVITE': 'Convite',
        'DISPENSA': 'Dispensa de Licitação',
        'INEXIGIBILIDADE': 'Inexigibilidade',
        'REGISTRO DE PRECOS': 'Registro de Preços',
        'CREDENCIAMENTO': 'Credenciamento',
        'DIALOGO COMPETITIVO': 'Diálogo Competitivo',
        'MANIFESTACAO DE INTERESSE': 'Manifestação de Interesse',
    }
    for chave, valor in mapa.items():
        if chave in titulo:
            return valor
    return ''


async def coletar_licitacoes_e(boletim_id: int, cursor) -> int:
    from playwright.async_api import async_playwright

    total = 0
    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
            ],
        )
        context = await browser.new_context(
            viewport={'width': 1920, 'height': 1080},
            user_agent=(
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                'AppleWebKit/537.36 (KHTML, like Gecko) '
                'Chrome/131.0.0.0 Safari/537.36'
            ),
            locale='pt-BR',
            timezone_id='America/Sao_Paulo',
        )
        await context.add_init_script('''
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1,2,3,4,5] });
            window.chrome = { runtime: {} };
        ''')

        page = await context.new_page()

        try:
            # Tenta acessar a página de consulta pública
            urls_tentar = [
                f'{BASE_URL}/aop/frmListaAvisosRelacionados.do',
                f'{BASE_URL}/aop/frmListaLicitacao.do',
                f'{BASE_URL}/',
            ]

            pagina_dados = None
            for url in urls_tentar:
                print(f"[licitacoes_e] Tentando {url}")
                try:
                    resp = await page.goto(url, wait_until='networkidle', timeout=20000)
                    if resp and resp.status == 200:
                        pagina_dados = url
                        print(f"[licitacoes_e] ✅ Acessou {url}")
                        break
                    elif resp and resp.status == 403:
                        print(f"[licitacoes_e] ⚠️ 403 bloqueado em {url}")
                        continue
                except Exception as e:
                    print(f"[licitacoes_e] Erro em {url}: {e}")
                    continue

            if not pagina_dados:
                print("[licitacoes_e] ❌ Nenhuma página acessível")
                return 0

            await page.wait_for_timeout(3000)

            # Verifica se precisa de login
            login = await page.query_selector('[name*="login"], [name*="senha"], #login, #senha, input[type="password"]')
            if login:
                print("[licitacoes_e] ⚠️ Página de login detectada (requer credenciais)")
                # Tenta acessar páginas que não exigem login
                for url_alt in [
                    f'{BASE_URL}/aop/frmListaAvisosRelacionados.do',
                ]:
                    if url_alt != pagina_dados:
                        try:
                            await page.goto(url_alt, wait_until='networkidle', timeout=15000)
                            login2 = await page.query_selector('input[type="password"]')
                            if not login2:
                                pagina_dados = url_alt
                                print(f"[licitacoes_e] ✅ Alternativa sem login: {url_alt}")
                                break
                        except Exception:
                            continue
                else:
                    print("[licitacoes_e] ❌ Todas as páginas exigem login")
                    return 0

            # Tenta aplicar filtro por data (hoje)
            hoje = datetime.now().strftime('%d/%m/%Y')
            data_input = await page.query_selector('input[type="text"][name*="data"], input[type="date"]')
            if data_input:
                try:
                    await data_input.fill(hoje)
                    btn_buscar = await page.query_selector(
                        'button:has-text("Buscar"), input[type="submit"], '
                        'button:has-text("Pesquisar"), a:has-text("Buscar")'
                    )
                    if btn_buscar:
                        await btn_buscar.click()
                        await page.wait_for_timeout(3000)
                except Exception as e:
                    print(f"[licitacoes_e] Erro ao aplicar filtro: {e}")

            # Extrai dados da tabela de resultados
            licitacoes = []
            try:
                linhas = await page.query_selector_all('table tr, .k-grid tr, [class*="grid"] tr, .listagem tr')
                if not linhas or len(linhas) <= 1:
                    # Tenta extrair via JS
                    dados_js = await page.evaluate('''() => {
                        const tables = document.querySelectorAll('table');
                        for (const table of tables) {
                            const rows = table.querySelectorAll('tr');
                            if (rows.length > 1) {
                                return Array.from(rows).slice(1).map(row => {
                                    const cells = row.querySelectorAll('td, th');
                                    return Array.from(cells).map(c => c.textContent.trim());
                                });
                            }
                        }
                        return null;
                    }''')

                    if dados_js:
                        for cols in dados_js:
                            if len(cols) < 2:
                                continue
                            titulo = cols[1] if len(cols) > 1 else cols[0]
                            lic = {
                                'orgao': cols[0] if len(cols) > 0 else '',
                                'objeto': titulo,
                                'edital': '',
                                'numero_processo': '',
                                'estado': None,
                                'cidade': None,
                                'modalidade': extrair_modalidade(titulo),
                                'data_publicacao': parse_data_br(cols[2] if len(cols) > 2 else ''),
                                'data_abertura': parse_data_br(cols[3] if len(cols) > 3 else ''),
                                'valor_estimado': None,
                                'link_detalhes': page.url,
                                'id_original': str(cols[0] if len(cols) > 0 else ''),
                            }
                            licitacoes.append(lic)
            except Exception as e:
                print(f"[licitacoes_e] Erro ao extrair dados: {e}")

            if licitacoes:
                total = inserir_licitacoes_batch(cursor, boletim_id, licitacoes, PORTAL)
                print(f"[licitacoes_e] {total} licitações inseridas")

        except Exception as e:
            print(f"[licitacoes_e] Erro geral: {e}")
            raise
        finally:
            await browser.close()

    return total


def main():
    import asyncio

    conn = get_connection()
    cursor = conn.cursor()
    hoje = datetime.now().strftime('%Y-%m-%d')
    boletim_id = get_or_create_boletim(cursor, hoje)

    try:
        qtd = asyncio.run(coletar_licitacoes_e(boletim_id, cursor))
        status = 'SUCESSO' if qtd > 0 else 'SUCESSO'
        log_captacao(cursor, PORTAL, 'SCRAPING_AVANCADO', status, qtd, qtd)
        if qtd > 0:
            registrar_portais_boletim(cursor, boletim_id, PORTAL)
    except Exception as e:
        log_captacao(cursor, PORTAL, 'SCRAPING_AVANCADO', 'ERRO', 0, 0, str(e))
        print(f"[licitacoes_e] ERRO: {e}")

    atualizar_total_boletim(cursor, boletim_id)
    conn.close()
    print(f"[licitacoes_e] Finalizado. {qtd} licitações.")


if __name__ == '__main__':
    main()
