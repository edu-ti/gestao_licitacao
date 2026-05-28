<?php
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/Database.php');
require_once(__DIR__ . '/fpdf/fpdf.php');

define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');

class PDF extends FPDF
{
    function Header() {
        $logoPath = __DIR__ . '/imagens/LOGO-FR.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 30);
        }
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10, toISO('Relatório de Pregões'),0,1,'C');
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, toISO('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    function GetNbLines($w, $txt)
    {
        if(!isset($this->CurrentFont))
            $this->Error('No font has been set');
        $cw = $this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i<$nb)
        {
            $c = $s[$i];
            if($c=="\n")
            {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += $cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i = $sep+1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }

    function CheckPageBreak($h)
    {
        if (($this->y + $h) > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter) {
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            return true;
        }
        return false;
    }
}

function toISO($string) {
    if ($string === null || $string === '') {
        return '';
    }
    $string = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    if ($string === false) {
        $string = '';
    }
    $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $string);
    if ($result === false) {
        $result = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $string);
    }
    return $result;
}

try {
    $db = new Database();
    $pdo = $db->connect();

    $filtro_status = isset($_GET['filtro_status']) && is_array($_GET['filtro_status']) ? $_GET['filtro_status'] : [];
    $filtro_fornecedor = isset($_GET['filtro_fornecedor']) && is_array($_GET['filtro_fornecedor']) ? $_GET['filtro_fornecedor'] : [];
    $filtro_orgao = $_GET['filtro_orgao'] ?? '';
    $filtro_data_inicio = $_GET['filtro_data_inicio'] ?? '';
    $filtro_data_fim = $_GET['filtro_data_fim'] ?? '';
    $filtro_pregao = $_GET['filtro_pregao'] ?? '';

    $sql_pregoes = "SELECT id, numero_edital, numero_processo, modalidade, orgao_comprador, orgao_cnpj, orgao_nome_fantasia, orgao_endereco, orgao_bairro, orgao_cidade, orgao_estado, orgao_cep, data_sessao, hora_sessao, status, objeto FROM pregoes";
    $where_clauses = [];
    $params = [];

    if (!empty($filtro_pregao)) {
        $where_clauses[] = "id = :pregao_id";
        $params[':pregao_id'] = $filtro_pregao;
    }
    if (!empty($filtro_status)) {
        $placeholders = [];
        foreach ($filtro_status as $i => $s) {
            $key = ':status_' . $i;
            $placeholders[] = $key;
            $params[$key] = $s;
        }
        $where_clauses[] = "status IN (" . implode(',', $placeholders) . ")";
    }
    if (!empty($filtro_orgao)) {
        $where_clauses[] = "orgao_comprador = :orgao";
        $params[':orgao'] = $filtro_orgao;
    }
    if (!empty($filtro_data_inicio)) {
        $where_clauses[] = "data_sessao >= :data_inicio";
        $params[':data_inicio'] = $filtro_data_inicio;
    }
    if (!empty($filtro_data_fim)) {
        $where_clauses[] = "data_sessao <= :data_fim";
        $params[':data_fim'] = $filtro_data_fim;
    }
    if (!empty($filtro_fornecedor)) {
        $placeholders = [];
        foreach ($filtro_fornecedor as $i => $f) {
            $key = ':forn_' . $i;
            $placeholders[] = $key;
            $params[$key] = $f;
        }
        $where_clauses[] = "EXISTS (SELECT 1 FROM itens_pregoes ip WHERE ip.pregao_id = pregoes.id AND ip.fornecedor_id IN (" . implode(',', $placeholders) . "))";
    }

    if (!empty($where_clauses)) {
        $sql_pregoes .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql_pregoes .= " ORDER BY data_sessao DESC";

    $stmt = $pdo->prepare($sql_pregoes);
    $stmt->execute($params);
    $pregoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new PDF('L','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();

    if (empty($pregoes)) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10, toISO('Nenhum resultado encontrado para os filtros aplicados.'),0,1,'C');
    } else {
        $pdf->SetFont('Arial','',8);
        $filtros_txt = 'Filtros: ';
        if (!empty($filtro_pregao)) $filtros_txt .= 'Pregão Específico | ';
        if (!empty($filtro_status)) $filtros_txt .= 'Status: ' . implode(', ', $filtro_status) . ' | ';
        if (!empty($filtro_orgao)) $filtros_txt .= 'Órgão: ' . $filtro_orgao . ' | ';
        if (!empty($filtro_data_inicio) || !empty($filtro_data_fim))
            $filtros_txt .= 'Período: ' . ($filtro_data_inicio ?: '...') . ' a ' . ($filtro_data_fim ?: '...') . ' | ';
        if (!empty($filtro_fornecedor)) {
            $nomes_forn = [];
            foreach ($filtro_fornecedor as $fid) {
                $stmt_f = $pdo->prepare("SELECT nome FROM fornecedores WHERE id = ?");
                $stmt_f->execute([$fid]);
                $nf = $stmt_f->fetchColumn();
                if ($nf) $nomes_forn[] = $nf;
            }
            if (!empty($nomes_forn)) $filtros_txt .= 'Fornecedor(es): ' . implode(', ', $nomes_forn);
        }
        $pdf->Cell(0, 5, toISO($filtros_txt), 0, 1, 'L');
        $pdf->Ln(4);

        foreach($pregoes as $pregao) {
            $sql_itens = "SELECT i.*, f.nome as fornecedor_nome
                          FROM itens_pregoes i
                          JOIN fornecedores f ON i.fornecedor_id = f.id
                          WHERE i.pregao_id = :pregao_id
                          ORDER BY i.numero_lote ASC, CAST(i.numero_item AS UNSIGNED) ASC, i.numero_item ASC, i.valor_unitario ASC";
            $stmt_itens = $pdo->prepare($sql_itens);
            $stmt_itens->execute([':pregao_id' => $pregao['id']]);
            $todos_itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

            $itens_agrupados = [];
            foreach ($todos_itens as $item) {
                $lote_key = !empty($item['numero_lote']) ? $item['numero_lote'] : 'SEM_LOTE';
                $item_key = $item['numero_item'];
                $itens_agrupados[$lote_key][$item_key][] = $item;
            }

            // --- Cabeçalho do Pregão ---
            $pdf->SetFont('Arial','B',10);
            $pdf->SetFillColor(230, 230, 230);
            if ($pdf->CheckPageBreak(30)) { }

            $pdf->Cell(40,7, 'Edital',1,0,'C',true);
            $pdf->Cell(117,7, toISO('Órgão Comprador'),1,0,'C',true);
            $pdf->Cell(50,7, 'Data da Disputa',1,0,'C',true);
            $pdf->Cell(70,7, 'Status',1,1,'C',true);

            $pdf->SetFont('Arial','',10);
            $pdf->SetFillColor(245, 245, 245);

            $lineHeightPreg = 6;
            $w_edital = 40; $w_orgao = 117; $w_data = 50; $w_status = 70;

            $txt_orgao = toISO($pregao['orgao_comprador']);
            $nb_orgao = $pdf->GetNbLines($w_orgao, $txt_orgao);
            $nb_edital = $pdf->GetNbLines($w_edital, toISO($pregao['numero_edital']));
            $data_sessao_str = !empty($pregao['data_sessao']) ? date('d/m/Y', strtotime($pregao['data_sessao'])) : 'N/D';
            $nb_data = $pdf->GetNbLines($w_data, $data_sessao_str);
            $nb_status = $pdf->GetNbLines($w_status, toISO($pregao['status']));

            $max_lines_preg = max($nb_orgao, $nb_edital, $nb_data, $nb_status, 1);
            $rowHeightPreg = $max_lines_preg * $lineHeightPreg;

            $startX_preg = 10;
            $startY_preg = $pdf->GetY();

            if ($pdf->CheckPageBreak($rowHeightPreg)) {
                $startY_preg = $pdf->GetY();
            }

            $pdf->Rect($startX_preg, $startY_preg, $w_edital, $rowHeightPreg, 'DF');
            $pdf->Rect($startX_preg + $w_edital, $startY_preg, $w_orgao, $rowHeightPreg, 'DF');
            $pdf->Rect($startX_preg + $w_edital + $w_orgao, $startY_preg, $w_data, $rowHeightPreg, 'DF');
            $pdf->Rect($startX_preg + $w_edital + $w_orgao + $w_data, $startY_preg, $w_status, $rowHeightPreg, 'DF');

            $textStartY_preg = $startY_preg + ($rowHeightPreg - ($max_lines_preg * $lineHeightPreg)) / 2;

            $pdf->SetXY($startX_preg, $textStartY_preg);
            $pdf->MultiCell($w_edital, $lineHeightPreg, toISO($pregao['numero_edital']), 0, 'C', false);
            $pdf->SetXY($startX_preg + $w_edital, $textStartY_preg);
            $pdf->MultiCell($w_orgao, $lineHeightPreg, $txt_orgao, 0, 'C', false);
            $pdf->SetXY($startX_preg + $w_edital + $w_orgao, $textStartY_preg);
            $pdf->MultiCell($w_data, $lineHeightPreg, $data_sessao_str, 0, 'C', false);
            $pdf->SetXY($startX_preg + $w_edital + $w_orgao + $w_data, $textStartY_preg);
            $pdf->MultiCell($w_status, $lineHeightPreg, toISO($pregao['status']), 0, 'C', false);

            $pdf->SetXY($startX_preg, $startY_preg + $rowHeightPreg);

            // --- Dados do Órgão Comprador (apenas para relatório específico) ---
            if (!empty($filtro_pregao)) {
                if ($pdf->CheckPageBreak(55)) { }

                $pdf->SetFont('Arial','B',9);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->Cell(277, 6, toISO('DADOS DO ÓRGÃO COMPRADOR'), 1, 1, 'C', true);
                $pdf->SetFont('Arial','',8);
                $pdf->SetFillColor(255, 255, 255);

                // CNPJ
                if (!empty($pregao['orgao_cnpj'])) {
                    $pdf->Cell(40, 5, toISO('CNPJ:'), 'LR', 0, 'L', false);
                    $pdf->Cell(237, 5, toISO($pregao['orgao_cnpj']), 'R', 1, 'L', false);
                }
                // Razão Social
                $pdf->Cell(40, 5, toISO('Razão Social:'), 'LR', 0, 'L', false);
                $pdf->Cell(237, 5, toISO($pregao['orgao_comprador']), 'R', 1, 'L', false);
                // Nome Fantasia
                if (!empty($pregao['orgao_nome_fantasia'])) {
                    $pdf->Cell(40, 5, toISO('Nome Fantasia:'), 'LR', 0, 'L', false);
                    $pdf->Cell(237, 5, toISO($pregao['orgao_nome_fantasia']), 'R', 1, 'L', false);
                }
                // Endereço
                $endereco_linha = '';
                if (!empty($pregao['orgao_endereco'])) $endereco_linha .= $pregao['orgao_endereco'];
                if (!empty($pregao['orgao_bairro'])) $endereco_linha .= ($endereco_linha ? ' - ' : '') . $pregao['orgao_bairro'];
                if (!empty($pregao['orgao_cidade'])) $endereco_linha .= ($endereco_linha ? ' - ' : '') . $pregao['orgao_cidade'];
                if (!empty($pregao['orgao_estado'])) $endereco_linha .= ($endereco_linha ? '/' : '') . $pregao['orgao_estado'];
                if (!empty($pregao['orgao_cep'])) $endereco_linha .= ($endereco_linha ? ' - CEP: ' : 'CEP: ') . $pregao['orgao_cep'];
                if (!empty($endereco_linha)) {
                    $pdf->Cell(40, 5, toISO('Endereço:'), 'LR', 0, 'L', false);
                    $pdf->Cell(237, 5, toISO($endereco_linha), 'R', 1, 'L', false);
                }
                // Fecha bloco
                $pdf->Cell(277, 1, '', 'T', 1, 'C', false);
                $pdf->Ln(2);

                // --- Dados da Licitação ---
                $pdf->SetFont('Arial','B',9);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->Cell(277, 6, toISO('DADOS DA LICITAÇÃO'), 1, 1, 'C', true);
                $pdf->SetFont('Arial','',8);

                if (!empty($pregao['numero_processo'])) {
                    $pdf->Cell(40, 5, toISO('Processo:'), 'LR', 0, 'L', false);
                    $pdf->Cell(237, 5, toISO($pregao['numero_processo']), 'R', 1, 'L', false);
                }
                if (!empty($pregao['modalidade'])) {
                    $pdf->Cell(40, 5, toISO('Modalidade:'), 'LR', 0, 'L', false);
                    $pdf->Cell(237, 5, toISO($pregao['modalidade']), 'R', 1, 'L', false);
                }
                $pdf->Cell(40, 5, toISO('Data Disputa:'), 'LR', 0, 'L', false);
                $pdf->Cell(237, 5, toISO($data_sessao_str . (!empty($pregao['hora_sessao']) ? ' às ' . substr($pregao['hora_sessao'], 0, 5) : '')), 'R', 1, 'L', false);
                if (!empty($pregao['objeto'])) {
                    $pdf->Cell(40, 5, toISO('Objeto:'), 'LR', 0, 'L', false);
                    $pdf->MultiCell(237, 5, toISO($pregao['objeto']), 'R', 'L', false);
                }
                // Fecha bloco
                $pdf->Cell(277, 1, '', 'T', 1, 'C', false);
                $pdf->Ln(2);
            }

            if (empty($itens_agrupados)) {
                $pdf->SetFont('Arial','I',9);
                $pdf->Cell(0,7, toISO('Nenhum item encontrado para este pregão.'), 0, 1, 'C');
                $pdf->Ln(5);
                continue;
            }

            $pdf->Ln(3);

            // ===================================================================
            // LOOP POR LOTE -> ITEM -> PARTICIPANTES
            // ===================================================================
            $lineHeight = 5;

            // Larguras originais + Tipo Cota no espaço vazio
            // Original: 55+47+47+33+33+42 = 257
            // Com Tipo Cota (20): 55+47+47+33+33+20+42 = 277
            $w_partic = 55; $w_fab = 47; $w_mod = 47; $w_unit = 33; $w_vtotal = 33; $w_tipo = 20; $w_st = 42;

            foreach ($itens_agrupados as $lote_nome => $itens_do_lote) {
                if ($lote_nome !== 'SEM_LOTE') {
                    if ($pdf->CheckPageBreak(10)) { }
                    $pdf->SetFont('Arial','B',9);
                    $pdf->SetFillColor(200, 220, 255);
                    $pdf->Cell(277, 6, toISO($lote_nome), 0, 1, 'C', true);
                    $pdf->Ln(2);
                }

                foreach ($itens_do_lote as $item_key => $participantes) {
                    $item_ref = $participantes[0];

                    if ($pdf->CheckPageBreak(15)) { }

                    $descricao = toISO($item_ref['descricao']);
                    $qtd = $item_ref['quantidade'];
                    $vref = $item_ref['valor_unitario_ref'] ?? 0;
                    $vtotal_ref = $qtd * $vref;
                    $linha_ref = toISO('Item ' . $item_ref['numero_item'] . ' | Qtd: ' . $qtd .
                              ' | Ref: R$ ' . number_format($vref, 2, ',', '.') .
                              ' | Total Ref: R$ ' . number_format($vtotal_ref, 2, ',', '.'));

                    $pdf->SetFont('Arial','B',9);
                    $pdf->SetFillColor(235, 245, 255);
                    $pdf->Cell(277, 6, $linha_ref, 0, 1, 'L', true);
                    $pdf->Ln(1);

                    // Descrição completa do item
                    $pdf->SetFont('Arial','B',8);
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Cell(277, 5, toISO('Descrição:'), 0, 1, 'L', false);
                    $pdf->SetFont('Arial','',8);
                    $pdf->MultiCell(277, 5, $descricao, 0, 'L', false);
                    $pdf->Ln(1);

                    // --- Cabeçalho da Tabela de Participantes ---
                    if ($pdf->CheckPageBreak(8)) { }
                    $pdf->SetFont('Arial','B',8);
                    $pdf->SetFillColor(210, 210, 210);
                    $pdf->Cell($w_partic, 6, 'Participante', 1, 0, 'C', true);
                    $pdf->Cell($w_fab, 6, 'Fabricante', 1, 0, 'C', true);
                    $pdf->Cell($w_mod, 6, 'Modelo', 1, 0, 'C', true);
                    $pdf->Cell($w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', true);
                    $pdf->Cell($w_vtotal, 6, 'Vlr. Total', 1, 0, 'C', true);
                    $pdf->Cell($w_tipo, 6, 'Tipo Cota', 1, 0, 'C', true);
                    $pdf->Cell($w_st, 6, 'Status', 1, 1, 'C', true);

                    // --- Linhas dos Participantes ---
                    $pdf->SetFont('Arial','',8);
                    $itemFill = false;

                    foreach ($participantes as $item) {
                        $txt_partic = toISO($item['fornecedor_nome']);
                        $txt_fab = toISO($item['fabricante']);
                        $txt_mod = toISO($item['modelo']);
                        $valor_unit_str = 'R$ ' . number_format($item['valor_unitario'], 2, ',', '.');
                        $valor_total = $item['quantidade'] * $item['valor_unitario'];
                        $valor_total_str = 'R$ ' . number_format($valor_total, 2, ',', '.');
                        $txt_tipo = toISO($item['tipo_cota'] ?? '');
                        $txt_status = toISO($item['status_item'] ?? 'Classificada');

                        $nb_partic = $pdf->GetNbLines($w_partic, $txt_partic);
                        $nb_fab = $pdf->GetNbLines($w_fab, $txt_fab);
                        $nb_mod = $pdf->GetNbLines($w_mod, $txt_mod);
                        $nb_unit = $pdf->GetNbLines($w_unit, $valor_unit_str);
                        $nb_vtotal = $pdf->GetNbLines($w_vtotal, $valor_total_str);
                        $nb_tipo = $pdf->GetNbLines($w_tipo, $txt_tipo);
                        $nb_st = $pdf->GetNbLines($w_st, $txt_status);

                        $max_lines = max($nb_partic, $nb_fab, $nb_mod, $nb_unit, $nb_vtotal, $nb_tipo, $nb_st, 1);
                        $rowHeight = $max_lines * $lineHeight;

                        $startX = 10;
                        $startY = $pdf->GetY();

                        if ($pdf->CheckPageBreak($rowHeight)) {
                            $startY = $pdf->GetY();
                            // Reimprime cabeçalho
                            $pdf->SetFont('Arial','B',8);
                            $pdf->SetFillColor(210, 210, 210);
                            $pdf->Cell($w_partic, 6, 'Participante', 1, 0, 'C', true);
                            $pdf->Cell($w_fab, 6, 'Fabricante', 1, 0, 'C', true);
                            $pdf->Cell($w_mod, 6, 'Modelo', 1, 0, 'C', true);
                            $pdf->Cell($w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', true);
                            $pdf->Cell($w_vtotal, 6, 'Vlr. Total', 1, 0, 'C', true);
                            $pdf->Cell($w_tipo, 6, 'Tipo Cota', 1, 0, 'C', true);
                            $pdf->Cell($w_st, 6, 'Status', 1, 1, 'C', true);
                            $pdf->SetFont('Arial','',8);
                            $startY = $pdf->GetY();
                        }

                        $style = $itemFill ? 'DF' : 'D';
                        $pdf->Rect($startX, $startY, $w_partic, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic, $startY, $w_fab, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic + $w_fab, $startY, $w_mod, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic + $w_fab + $w_mod, $startY, $w_unit, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit, $startY, $w_vtotal, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal, $startY, $w_tipo, $rowHeight, $style);
                        $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal + $w_tipo, $startY, $w_st, $rowHeight, $style);

                        $pdf->SetXY($startX, $startY);
                        $pdf->MultiCell($w_partic, $lineHeight, $txt_partic, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic, $startY);
                        $pdf->MultiCell($w_fab, $lineHeight, $txt_fab, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic + $w_fab, $startY);
                        $pdf->MultiCell($w_mod, $lineHeight, $txt_mod, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod, $startY);
                        $pdf->MultiCell($w_unit, $lineHeight, $valor_unit_str, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit, $startY);
                        $pdf->MultiCell($w_vtotal, $lineHeight, $valor_total_str, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal, $startY);
                        $pdf->MultiCell($w_tipo, $lineHeight, $txt_tipo, 0, 'C', false);
                        $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal + $w_tipo, $startY);
                        $pdf->MultiCell($w_st, $lineHeight, $txt_status, 0, 'C', false);

                        $pdf->SetXY($startX, $startY + $rowHeight);
                        $itemFill = !$itemFill;
                    }

                    // --- Classificação ---
                    $ranking = $participantes;
                    usort($ranking, function ($a, $b) {
                        return $a['valor_unitario'] <=> $b['valor_unitario'];
                    });

                    if ($pdf->CheckPageBreak(6)) { }
                    $pdf->SetFont('Arial','B',8);
                    $pdf->SetFillColor(230, 250, 230);
                    $classificacao = 'Classificação: ';
                    $pos = 1;
                    foreach ($ranking as $p) {
                        if ($pos > 1) $classificacao .= ' | ';
                        $classificacao .= $pos . 'º ' . $p['fornecedor_nome'] . ' (R$ ' . number_format($p['valor_unitario'], 2, ',', '.') . ')';
                        $pos++;
                    }
                    $pdf->MultiCell(277, 5, toISO($classificacao), 0, 'L', true);
                    $pdf->Ln(3);
                }
            }

            $pdf->Ln(6);
        }
    }

    $pdf->Output('I', 'relatorio_pregoes_detalhado.pdf');

} catch (Exception $e) {
    die("Erro ao gerar PDF: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine());
}
?>
