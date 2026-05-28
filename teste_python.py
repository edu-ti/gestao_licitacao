from fpdf import FPDF
import fitz

class TestPDF(FPDF):
    pass

pdf = TestPDF('L', 'mm', 'A4')
pdf.add_page()
pdf.set_font('Arial', 'B', 10)
pdf.cell(0, 10, 'Teste de Alinhamento', 0, 1, 'C')

line_height = 5
w_partic = 55
w_fab = 47
w_mod = 47
w_unit = 33
w_vtotal = 33
w_tipo = 20
w_st = 42

# Cabeçalho
pdf.set_font('Arial', 'B', 8)
pdf.set_fill_color(210, 210, 210)
pdf.cell(w_partic, 6, 'Participante', 1, 0, 'C', True)
pdf.cell(w_fab, 6, 'Fabricante', 1, 0, 'C', True)
pdf.cell(w_mod, 6, 'Modelo', 1, 0, 'C', True)
pdf.cell(w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', True)
pdf.cell(w_vtotal, 6, 'Vlr. Total', 1, 0, 'C', True)
pdf.cell(w_tipo, 6, 'Tipo Cota', 1, 0, 'C', True)
pdf.cell(w_st, 6, 'Status', 1, 1, 'C', True)

rows = [
    ['INSTRAMED INDUSTRIA MEDICO HISPITALAR LTDA', 'INSTRAMED', 'MODELO X', 'R$ 0,00', 'R$ 0,00', 'Ampla Concorrência', 'Classificada'],
    ['Empresa Teste Ltda', 'Fab Teste', 'Mod Y', 'R$ 1.234,56', 'R$ 12.345,60', 'Cota Exclusiva', 'Classificada'],
]

pdf.set_font('Arial', '', 8)
itemFill = False

for row in rows:
    txt_partic = row[0]
    txt_fab = row[1]
    txt_mod = row[2]
    txt_unit = row[3]
    txt_vtotal = row[4]
    txt_tipo = row[5]
    txt_status = row[6]

    # Calcula altura baseada no texto mais longo
    nb_partic = pdf.get_string_width(txt_partic) // (w_partic - 2) + 1
    nb_fab = pdf.get_string_width(txt_fab) // (w_fab - 2) + 1
    nb_mod = pdf.get_string_width(txt_mod) // (w_mod - 2) + 1
    nb_unit = pdf.get_string_width(txt_unit) // (w_unit - 2) + 1
    nb_vtotal = pdf.get_string_width(txt_vtotal) // (w_vtotal - 2) + 1
    nb_tipo = pdf.get_string_width(txt_tipo) // (w_tipo - 2) + 1
    nb_st = pdf.get_string_width(txt_status) // (w_st - 2) + 1

    max_lines = max(nb_partic, nb_fab, nb_mod, nb_unit, nb_vtotal, nb_tipo, nb_st, 1)
    rowHeight = max_lines * line_height

    startX = 10
    startY = pdf.get_y()

    fillColor = 245 if itemFill else 255
    pdf.set_fill_color(fillColor, fillColor, fillColor)

    pdf.rect(startX, startY, w_partic, rowHeight, 'DF')
    pdf.rect(startX + w_partic, startY, w_fab, rowHeight, 'DF')
    pdf.rect(startX + w_partic + w_fab, startY, w_mod, rowHeight, 'DF')
    pdf.rect(startX + w_partic + w_fab + w_mod, startY, w_unit, rowHeight, 'DF')
    pdf.rect(startX + w_partic + w_fab + w_mod + w_unit, startY, w_vtotal, rowHeight, 'DF')
    pdf.rect(startX + w_partic + w_fab + w_mod + w_unit + w_vtotal, startY, w_tipo, rowHeight, 'DF')
    pdf.rect(startX + w_partic + w_fab + w_mod + w_unit + w_vtotal + w_tipo, startY, w_st, rowHeight, 'DF')

    textStartY = startY + (rowHeight - (max_lines * line_height)) / 2

    pdf.set_xy(startX, textStartY)
    pdf.multi_cell(w_partic, line_height, txt_partic, 0, 'C', False)
    pdf.set_xy(startX + w_partic, textStartY)
    pdf.multi_cell(w_fab, line_height, txt_fab, 0, 'C', False)
    pdf.set_xy(startX + w_partic + w_fab, textStartY)
    pdf.multi_cell(w_mod, line_height, txt_mod, 0, 'C', False)
    pdf.set_xy(startX + w_partic + w_fab + w_mod, textStartY)
    pdf.multi_cell(w_unit, line_height, txt_unit, 0, 'C', False)
    pdf.set_xy(startX + w_partic + w_fab + w_mod + w_unit, textStartY)
    pdf.multi_cell(w_vtotal, line_height, txt_vtotal, 0, 'C', False)
    pdf.set_xy(startX + w_partic + w_fab + w_mod + w_unit + w_vtotal, textStartY)
    pdf.multi_cell(w_tipo, line_height, txt_tipo, 0, 'C', False)
    pdf.set_xy(startX + w_partic + w_fab + w_mod + w_unit + w_vtotal + w_tipo, textStartY)
    pdf.multi_cell(w_st, line_height, txt_status, 0, 'C', False)

    pdf.set_xy(startX, startY + rowHeight)
    itemFill = not itemFill

pdf.output('teste_python.pdf')

# Renderiza PDF como imagem
doc = fitz.open('teste_python.pdf')
page = doc.load_page(0)
pix = page.get_pixmap(dpi=150)
pix.save('teste_python.png')
doc.close()
print('Imagem gerada: teste_python.png')
