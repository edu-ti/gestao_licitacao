"""
db_utils.py — Conexão com MySQL e funções de log compartilhadas
para os scrapers da Fase 4 (Python Playwright).
"""

import os
import pymysql
from datetime import datetime
from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'))

DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'database': os.getenv('DB_NAME', 'u540193243_licitaweb_db'),
    'user': os.getenv('DB_USER', 'u540193243_licitaWeb'),
    'password': os.getenv('DB_PASS', 'gest@0licitaWeb'),
    'charset': 'utf8mb4',
    'connect_timeout': 30,
    'read_timeout': 30,
    'write_timeout': 30,
    'autocommit': True,
}


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def get_or_create_boletim(cursor, data: str = None) -> int:
    if data is None:
        data = datetime.now().strftime('%Y-%m-%d')
    cursor.execute("SELECT id FROM boletins WHERE data_boletim = %s", (data,))
    row = cursor.fetchone()
    if row:
        return row[0]
    cursor.execute(
        "INSERT INTO boletins (data_boletim, total_itens, portais_coletados) VALUES (%s, 0, '[]')",
        (data,),
    )
    return int(cursor.lastrowid)


def log_captacao(cursor, portal: str, tipo: str, status: str,
                  qtd_inserida: int, qtd_total: int, mensagem: str = None):
    agora = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    sql = """INSERT INTO log_captacao
             (portal, tipo, status, qtd_inserida, qtd_total, mensagem, iniciado_em, finalizado_em)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
    cursor.execute(sql, (portal, tipo, status, qtd_inserida,
                         qtd_total, mensagem, agora, agora))


def inserir_licitacoes_batch(cursor, boletim_id: int, licitacoes: list,
                              portal_origem: str) -> int:
    inseridas = 0
    sql = """INSERT IGNORE INTO boletim_licitacoes
             (boletim_id, orgao, objeto, edital, numero_processo, estado, cidade,
              modalidade, data_publicacao, data_abertura, valor_estimado,
              link_detalhes, status_badge, portal_origem, id_original)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'NOVA', %s, %s)"""
    for lic in licitacoes:
        try:
            cursor.execute(sql, (
                boletim_id,
                lic.get('orgao'),
                lic.get('objeto'),
                lic.get('edital'),
                lic.get('numero_processo'),
                lic.get('estado'),
                lic.get('cidade'),
                lic.get('modalidade'),
                lic.get('data_publicacao'),
                lic.get('data_abertura'),
                lic.get('valor_estimado'),
                lic.get('link_detalhes'),
                portal_origem,
                lic.get('id_original'),
            ))
            if cursor.rowcount > 0:
                inseridas += 1
        except Exception as e:
            print(f"[db_utils] Erro ao inserir: {e}")
    return inseridas


def registrar_portais_boletim(cursor, boletim_id: int, portal: str):
    cursor.execute("SELECT portais_coletados FROM boletins WHERE id = %s", (boletim_id,))
    row = cursor.fetchone()
    portais = []
    if row and row[0]:
        import json
        portais = json.loads(row[0])
    if portal not in portais:
        portais.append(portal)
        import json
        cursor.execute("UPDATE boletins SET portais_coletados = %s WHERE id = %s",
                       (json.dumps(portais), boletim_id))


def atualizar_total_boletim(cursor, boletim_id: int):
    cursor.execute("SELECT COUNT(*) FROM boletim_licitacoes WHERE boletim_id = %s", (boletim_id,))
    total = cursor.fetchone()[0]
    cursor.execute("UPDATE boletins SET total_itens = %s WHERE id = %s", (total, boletim_id))
