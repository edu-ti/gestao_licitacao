"""
pe_integrado.py — Scraper do PE Integrado (Governo de PE)
Fase 4: Playwright headless

Portal: https://www.peintegrado.pe.gov.br/Portal/
Páginas:
  - LicitacoesEmAndamento.aspx
  - LicitacoesEncerradas.aspx
  - DispensaLicitacoes.aspx

Notas:
  - Possui Google reCAPTCHA (v3, invisível)
  - Dados carregados via Kendo Grid (AJAX)
  - Se o CAPTCHA bloquear, tente:
    1. Rodar com playwright.show=True para resolver manualmente
    2. Usar proxy brasileiro
    3. Acessar diretamente o WebService SOAP (se exposto)
"""

import sys
import os
import re
import json
from datetime import datetime, timedelta
from typing import Optional

sys.path.insert(0, os.path.dirname(__file__))
from db_utils import (
    get_connection, get_or_create_boletim, log_captacao,
    inserir_licitacoes_batch, registrar_portais_boletim,
    atualizar_total_boletim,
)

PORTAL = 'PE_INTEGRADO'
BASE_URL = 'https://www.peintegrado.pe.gov.br/Portal'
PAGINAS = [
    f'{BASE_URL}/Pages/LicitacoesEmAndamento.aspx',
    f'{BASE_URL}/Pages/LicitacoesEncerradas.aspx',
    f'{BASE_URL}/Pages/DispensaLicitacoes.aspx',
]


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


def extrair_dados_kendo_grid(page) -> list:
    """
    Tenta extrair dados da Kendo Grid via JavaScript.
    A Kendo Grid armazena dados em um DataSource que pode ser acessado via JS.
    """
    licitacoes = []
    try:
        dados_js = page.evaluate('''() => {
            const grids = document.querySelectorAll('[data-role="grid"]');
            if (grids.length === 0) return null;
            const grid = grids[0];
            const ds = grid._dataSource || grid.dataSource;
            if (!ds || !ds._data) return null;
            return ds._data.map(item => ({
                orgao: item.UnidadeCompra || item.orgao || '',
                objeto: item.Objeto || item.objeto || '',
                edital: item.NumeroProcesso || item.Edital || item.edital || '',
                modalidade: item.Modalidade || item.modalidade || '',
                data_publicacao: item.DataPublicacao || item.dataPublicacao || '',
                data_abertura: item.DataAbertura || item.dataAbertura || '',
                link: item.Link || item.link || '',
                id: item.Id || item.IdProcesso || item.id || '',
            }));
        }''')
        if dados_js:
            for item in dados_js:
                lic = {
                    'orgao': item.get('orgao', '')[:500],
                    'objeto': item.get('objeto', ''),
                    'edital': item.get('edital', '')[:255],
                    'numero_processo': item.get('edital', '')[:255],
                    'estado': 'PE',
                    'cidade': None,
                    'modalidade': item.get('modalidade', '')[:100],
                    'data_publicacao': parse_data_br(item.get('data_publicacao', '')),
                    'data_abertura': parse_data_br(item.get('data_abertura', '')),
                    'valor_estimado': None,
                    'link_detalhes': item.get('link', '') or None,
                    'id_original': str(item.get('id', '')),
                }
                licitacoes.append(lic)
    except Exception as e:
        print(f"[pe_integrado] Erro ao extrair Kendo Grid: {e}")
    return licitacoes


def extrair_dados_html(page) -> list:
    """
    Fallback: extrai dados do HTML renderizado.
    Procura por tabelas com classes do Bootstrap/Kendo.
    """
    licitacoes = []
    try:
        linhas = page.query_selector_all('table tr')
        if len(linhas) <= 1:
            # Tenta outras estruturas
            linhas = page.query_selector_all('.k-grid tr, .grid tr, [class*="grid"] tr')
    except Exception as e:
        print(f"[pe_integrado] Erro ao extrair HTML: {e}")
    return licitacoes


async def coletar_pe_integrado(pagina_url: str, boletim_id: int, cursor) -> int:
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
            timezone_id='America/Recife',
        )
        # Remove vestígios de automação
        await context.add_init_script('''
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1,2,3,4,5] });
            window.chrome = { runtime: {} };
        ''')

        page = await context.new_page()

        try:
            print(f"[pe_integrado] Acessando {pagina_url}")
            await page.goto(pagina_url, wait_until='networkidle', timeout=30000)

            # Aguarda a Kendo Grid carregar (ou CAPTCHA resolver)
            await page.wait_for_timeout(5000)

            # Verifica se tem CAPTCHA visível
            captcha = await page.query_selector('[class*="recaptcha"], iframe[src*="recaptcha"], .g-recaptcha')
            if captcha:
                print("[pe_integrado] ⚠️ reCAPTCHA detectado! Tentando continuar...")
                await page.wait_for_timeout(10000)

            # Tenta extrair dados da Kendo Grid via JS
            itens = extrair_dados_kendo_grid(page)
            if not itens:
                # Fallback: HTML
                itens = extrair_dados_html(page)

            if itens:
                ins = inserir_licitacoes_batch(cursor, boletim_id, itens, PORTAL)
                total += ins
                print(f"[pe_integrado] {pagina_url}: {ins} licitações inseridas")

                # Tenta navegar pelas páginas do grid
                for tentativa in range(2, 6):
                    try:
                        btn = await page.query_selector(f'.k-pager-numbers a:has-text("{tentativa}"), .k-pager-nav[title="Ir para a página {tentativa}"]')
                        if not btn:
                            break
                        await btn.click()
                        await page.wait_for_timeout(3000)
                        itens = extrair_dados_kendo_grid(page)
                        if itens:
                            ins = inserir_licitacoes_batch(cursor, boletim_id, itens, PORTAL)
                            total += ins
                    except Exception:
                        break

        except Exception as e:
            print(f"[pe_integrado] Erro ao processar {pagina_url}: {e}")
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
    total_geral = 0
    erros = 0

    for url in PAGINAS:
        try:
            qtd = asyncio.run(coletar_pe_integrado(url, boletim_id, cursor))
            total_geral += qtd
            status = 'SUCESSO' if qtd > 0 else 'SUCESSO'
            log_captacao(cursor, PORTAL, 'SCRAPING_AVANCADO', status, qtd, qtd)
        except Exception as e:
            erros += 1
            log_captacao(cursor, PORTAL, 'SCRAPING_AVANCADO', 'ERRO', 0, 0, str(e))
            print(f"[pe_integrado] ERRO em {url}: {e}")

    if total_geral > 0:
        registrar_portais_boletim(cursor, boletim_id, PORTAL)

    atualizar_total_boletim(cursor, boletim_id)
    conn.close()
    print(f"[pe_integrado] Finalizado. {total_geral} licitações, {erros} erros.")


if __name__ == '__main__':
    main()
