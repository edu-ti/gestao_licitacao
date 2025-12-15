<?php
// ===================================================================
// MODO DE DIAGNÓSTICO: Habilitando a exibição de todos os erros
// ===================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Usar caminhos absolutos para garantir que os ficheiros são encontrados
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/Database.php');
require_once(__DIR__ . '/fpdf/fpdf.php');

// Define o caminho absoluto para a pasta de fontes
define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');

class PDF extends FPDF
{
    // Cabeçalho da página
    function Header() {
        $logoPath = __DIR__ . '/imagens/LOGO-FR.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 30);
        }
        
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10, toISO('Relatório de Pregões'),0,1,'C');
        $this->Ln(10);
    }

    // Rodapé da página
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, toISO('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    // ===================================================================
    // NOVA FUNÇÃO ADICIONADA
    // Função para calcular o número de linhas de um MultiCell
    // ===================================================================
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
    // ===================================================================
    // FIM DA NOVA FUNÇÃO
    // ===================================================================

    // ===================================================================
    // NOVA FUNÇÃO PARA VERIFICAR QUEBRA DE PÁGINA
    // ===================================================================
    function CheckPageBreak($h)
    {
        // Verifica se a altura 'h' da linha vai ultrapassar o limite
        // Usamos $this->y (propriedade interna) em vez de GetY()
        if (($this->y + $h) > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter) {
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            return true; // Indica que uma página foi adicionada
        }
        return false; // Nenhuma página nova
    }
    // ===================================================================
    // FIM DA NOVA FUNÇÃO
    // ===================================================================
}

// Função para converter texto para a codificação correta do PDF
function toISO($string) {
    if ($string === null || $string === '') {
        return '';
    }
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
}

try {
    $db = new Database();
    $pdo = $db->connect();

    // LÓGICA DE FILTRO
    $filtro_status = $_GET['filtro_status'] ?? '';
    $filtro_orgao = $_GET['filtro_orgao'] ?? '';
    $filtro_data_inicio = $_GET['filtro_data_inicio'] ?? '';
    $filtro_data_fim = $_GET['filtro_data_fim'] ?? '';
    // **NOVO: Capturar filtro de fornecedor**
    $filtro_fornecedor = $_GET['filtro_fornecedor'] ?? '';

    $sql_pregoes = "SELECT id, numero_edital, orgao_comprador, data_sessao, status FROM pregoes";
    $where_clauses = [];
    $params = [];

    if (!empty($filtro_status)) {
        $where_clauses[] = "status = :status";
        $params[':status'] = $filtro_status;
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
    // **NOVO: Adicionar lógica do filtro de fornecedor à consulta**
    if (!empty($filtro_fornecedor)) {
        $where_clauses[] = "EXISTS (SELECT 1 FROM itens_pregoes ip WHERE ip.pregao_id = pregoes.id AND ip.fornecedor_id = :fornecedor_id)";
        $params[':fornecedor_id'] = $filtro_fornecedor;
    }


    if (!empty($where_clauses)) {
        $sql_pregoes .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql_pregoes .= " ORDER BY data_sessao DESC";

    $stmt = $pdo->prepare($sql_pregoes);
    $stmt->execute($params);
    $pregoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GERAÇÃO DO PDF
    $pdf = new PDF('L','mm','A4'); 
    
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    if (empty($pregoes)) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10, toISO('Nenhum resultado encontrado para os filtros aplicados.'),0,1,'C');
    } else {
        foreach($pregoes as $pregao) {
            // Cabeçalho do Pregão
            $pdf->SetFont('Arial','B',10);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(40,7, 'Edital',1,0,'C',true);
            $pdf->Cell(117,7, toISO('Órgão Comprador'),1,0,'C',true);
            $pdf->Cell(50,7, 'Data da Disputa',1,0,'C',true);
            $pdf->Cell(70,7, 'Status',1,1,'C',true);

            // Dados do Pregão
            $pdf->SetFont('Arial','',10);
            $pdf->SetFillColor(245, 245, 245);
            
            // --- INÍCIO DA ALTERAÇÃO: Centralização e Quebra de Linha (MultiCell) ---
            $lineHeightPreg = 6; // Altura base da linha
            $w_edital = 40; $w_orgao = 117; $w_data = 50; $w_status = 70;

            // Calcular altura máxima (baseado em todas as colunas)
            $txt_orgao = toISO($pregao['orgao_comprador']);
            $nb_orgao = $pdf->GetNbLines($w_orgao, $txt_orgao);
            
            $nb_edital = $pdf->GetNbLines($w_edital, toISO($pregao['numero_edital']));
            $data_sessao_str = !empty($pregao['data_sessao']) ? date('d/m/Y', strtotime($pregao['data_sessao'])) : 'N/D';
            $nb_data = $pdf->GetNbLines($w_data, $data_sessao_str);
            $nb_status = $pdf->GetNbLines($w_status, toISO($pregao['status']));

            $max_lines_preg = max($nb_orgao, $nb_edital, $nb_data, $nb_status, 1); // Garante pelo menos 1 linha
            $rowHeightPreg = $max_lines_preg * $lineHeightPreg;
            
            // Salvar posições
            $startX_preg = $pdf->GetX();
            $startY_preg = $pdf->GetY();
            
            // **Verificar quebra de página**
            if ($pdf->CheckPageBreak($rowHeightPreg)) {
                $startY_preg = $pdf->GetY();
                $startX_preg = $pdf->GetX();
            }

            // --- INÍCIO DA CORREÇÃO: Desenho manual da linha do Pregão ---
            $stylePreg = true ? 'DF' : 'D'; // true = $fill (sempre preenchido)

            // 1. Desenhar os retângulos (bordas) manualmente
            $pdf->Rect($startX_preg, $startY_preg, $w_edital, $rowHeightPreg, $stylePreg);
            $pdf->Rect($startX_preg + $w_edital, $startY_preg, $w_orgao, $rowHeightPreg, $stylePreg);
            $pdf->Rect($startX_preg + $w_edital + $w_orgao, $startY_preg, $w_data, $rowHeightPreg, $stylePreg);
            $pdf->Rect($startX_preg + $w_edital + $w_orgao + $w_data, $startY_preg, $w_status, $rowHeightPreg, $stylePreg);
            
            // 2. Calcular o Y do texto (para centralização vertical)
            $textStartY_preg = $startY_preg + ($rowHeightPreg - ($max_lines_preg * $lineHeightPreg)) / 2;

            // 3. Desenhar o texto (sem borda, sem fill)
            $pdf->SetXY($startX_preg, $textStartY_preg);
            $pdf->MultiCell($w_edital, $lineHeightPreg, toISO($pregao['numero_edital']), 0, 'C', false);
            $pdf->SetXY($startX_preg + $w_edital, $textStartY_preg);
            
            $pdf->MultiCell($w_orgao, $lineHeightPreg, $txt_orgao, 0, 'C', false); // Centralizado
            $pdf->SetXY($startX_preg + $w_edital + $w_orgao, $textStartY_preg);

            $pdf->MultiCell($w_data, $lineHeightPreg, $data_sessao_str, 0, 'C', false);
            $pdf->SetXY($startX_preg + $w_edital + $w_orgao + $w_data, $textStartY_preg);

            $pdf->MultiCell($w_status, $lineHeightPreg, toISO($pregao['status']), 0, 'C', false); // Centralizado
            // --- FIM DA CORREÇÃO ---

            // Mover cursor para a próxima linha
            $pdf->SetXY($startX_preg, $startY_preg + $rowHeightPreg);
            // --- FIM DA ALTERAÇÃO ---

            // Buscar itens para este pregão
            $sql_itens = "SELECT
                            i.numero_item, i.quantidade, i.descricao, i.fabricante,
                            i.modelo, i.valor_unitario, f.nome as fornecedor_nome
                          FROM itens_pregoes i
                          JOIN fornecedores f ON i.fornecedor_id = f.id
                          WHERE i.pregao_id = :pregao_id
                          ORDER BY i.valor_unitario ASC";
            
            $stmt_itens = $pdo->prepare($sql_itens);
            $stmt_itens->execute([':pregao_id' => $pregao['id']]);
            $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($itens)) {
                $pdf->Ln(2);
                // Cabeçalho dos Itens
                $pdf->SetFont('Arial','B',9);
                $pdf->SetFillColor(200, 220, 255);
                
                // Definir larguras das colunas
                $w_item = 15; $w_desc = 70; $w_fab = 40; $w_mod = 40;
                $w_forn = 42; $w_qtd = 15; $w_unit = 25; $w_total = 30;
                
                $pdf->Cell($w_item, 6, 'Item', 1, 0, 'C', true);
                $pdf->Cell($w_desc, 6, toISO('Descrição'), 1, 0, 'C', true);
                $pdf->Cell($w_fab, 6, 'Fabricante/Marca', 1, 0, 'C', true);
                $pdf->Cell($w_mod, 6, 'Modelo', 1, 0, 'C', true);
                $pdf->Cell($w_forn, 6, 'Fornecedor', 1, 0, 'C', true);
                $pdf->Cell($w_qtd, 6, 'Qtd.', 1, 0, 'C', true);
                $pdf->Cell($w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', true);
                $pdf->Cell($w_total, 6, 'Vlr. Total', 1, 1, 'C', true);

                // ===================================================================
                // INÍCIO DA LÓGICA DE LINHA CORRIGIDA
                // ===================================================================
                $pdf->SetFont('Arial','',8);
                $itemFill = false;
                $lineHeight = 5; // Altura da linha base

                foreach($itens as $item) {
                    // Definir cor de fundo da linha
                    $pdf->SetFillColor($itemFill ? 245 : 255, $itemFill ? 245 : 255, $itemFill ? 245 : 255);
                    $itemFill = !$itemFill;

                    // 1. Calcular a altura máxima da linha
                    $txt_desc = toISO($item['descricao']);
                    $txt_fab = toISO($item['fabricante']);
                    $txt_mod = toISO($item['modelo']);
                    $txt_forn = toISO($item['fornecedor_nome']);

                    $nb_desc = $pdf->GetNbLines($w_desc, $txt_desc);
                    $nb_fab = $pdf->GetNbLines($w_fab, $txt_fab);
                    $nb_mod = $pdf->GetNbLines($w_mod, $txt_mod);
                    $nb_forn = $pdf->GetNbLines($w_forn, $txt_forn);
                    
                    // Considerar também as colunas de linha única para altura
                    $nb_item = $pdf->GetNbLines($w_item, toISO($item['numero_item']));
                    $nb_qtd = $pdf->GetNbLines($w_qtd, $item['quantidade']);
                    $valor_unit_str = 'R$ ' . number_format($item['valor_unitario'], 2, ',', '.');
                    $nb_unit = $pdf->GetNbLines($w_unit, $valor_unit_str);
                    $valor_total = $item['quantidade'] * $item['valor_unitario'];
                    $valor_total_str = 'R$ ' . number_format($valor_total, 2, ',', '.');
                    $nb_total = $pdf->GetNbLines($w_total, $valor_total_str);

                    $max_lines = max($nb_desc, $nb_fab, $nb_mod, $nb_forn, $nb_item, $nb_qtd, $nb_unit, $nb_total, 1); // Mínimo de 1 linha
                    $rowHeight = $max_lines * $lineHeight;

                    // 2. Armazenar posições X e Y
                    $startX = $pdf->GetX();
                    $startY = $pdf->GetY();

                    // ** ADICIONAL: Verificar quebra de página ANTES de desenhar
                    if ($pdf->CheckPageBreak($rowHeight)) {
                        $startY = $pdf->GetY(); // Resetar $startY para o topo da nova página
                        $startX = $pdf->GetX(); // Obter o X (que foi resetado para lMargin)
                        
                        // Redesenhar cabeçalho na nova página
                        $pdf->SetFont('Arial','B',9);
                        $pdf->SetFillColor(200, 220, 255);
                        $pdf->Cell($w_item, 6, 'Item', 1, 0, 'C', true);
                        $pdf->Cell($w_desc, 6, toISO('Descrição'), 1, 0, 'C', true);
                        $pdf->Cell($w_fab, 6, 'Fabricante/Marca', 1, 0, 'C', true);
                        $pdf->Cell($w_mod, 6, 'Modelo', 1, 0, 'C', true);
                        $pdf->Cell($w_forn, 6, 'Fornecedor', 1, 0, 'C', true);
                        $pdf->Cell($w_qtd, 6, 'Qtd.', 1, 0, 'C', true);
                        $pdf->Cell($w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', true);
                        $pdf->Cell($w_total, 6, 'Vlr. Total', 1, 1, 'C', true);
                        $pdf->SetFont('Arial','',8);
                        
                        // Recalcular Y e X após o novo cabeçalho
                        $startY = $pdf->GetY();
                        $startX = $pdf->GetX();
                    }
                    
                    // --- INÍCIO DA NOVA LÓGICA DE DESENHO MANUAL ---
                    $style = $itemFill ? 'DF' : 'D'; // D = Draw border, DF = Draw + Fill

                    // 3. Desenhar os retângulos (bordas) manualmente
                    $pdf->Rect($startX, $startY, $w_item, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item, $startY, $w_desc, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc, $startY, $w_fab, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc + $w_fab, $startY, $w_mod, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc + $w_fab + $w_mod, $startY, $w_forn, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn, $startY, $w_qtd, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn + $w_qtd, $startY, $w_unit, $rowHeight, $style);
                    $pdf->Rect($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn + $w_qtd + $w_unit, $startY, $w_total, $rowHeight, $style);

                    // 4. Desenhar o texto (sem borda, sem fill)
                    // --- INÍCIO DA CORREÇÃO DE ALINHAMENTO VERTICAL ---
                    
                    // Col 1: Item
                    $textHeight = $nb_item * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX, $textStartY);
                    $pdf->MultiCell($w_item, $lineHeight, toISO($item['numero_item']), 0, 'C', false);
                    
                    // Col 2: Descrição
                    $textHeight = $nb_desc * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item, $textStartY);
                    $pdf->MultiCell($w_desc, $lineHeight, $txt_desc, 0, 'C', false);
                    
                    // Col 3: Fabricante
                    $textHeight = $nb_fab * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc, $textStartY);
                    $pdf->MultiCell($w_fab, $lineHeight, $txt_fab, 0, 'C', false);

                    // Col 4: Modelo
                    $textHeight = $nb_mod * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc + $w_fab, $textStartY);
                    $pdf->MultiCell($w_mod, $lineHeight, $txt_mod, 0, 'C', false);

                    // Col 5: Fornecedor
                    $textHeight = $nb_forn * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc + $w_fab + $w_mod, $textStartY);
                    $pdf->MultiCell($w_forn, $lineHeight, $txt_forn, 0, 'C', false);
                    
                    // Col 6: Qtd.
                    $textHeight = $nb_qtd * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn, $textStartY);
                    $pdf->MultiCell($w_qtd, $lineHeight, $item['quantidade'], 0, 'C', false);
                    
                    // Col 7: Vlr. Unit.
                    $textHeight = $nb_unit * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn + $w_qtd, $textStartY);
                    $pdf->MultiCell($w_unit, $lineHeight, $valor_unit_str, 0, 'C', false);

                    // Col 8: Vlr. Total
                    $textHeight = $nb_total * $lineHeight;
                    $textStartY = $startY + ($rowHeight - $textHeight) / 2; // Y correto para esta célula
                    $pdf->SetXY($startX + $w_item + $w_desc + $w_fab + $w_mod + $w_forn + $w_qtd + $w_unit, $textStartY);
                    $pdf->MultiCell($w_total, $lineHeight, $valor_total_str, 0, 'C', false);
                    
                    // --- FIM DA CORREÇÃO DE ALINHAMENTO VERTICAL ---

                    // 5. Mover o cursor para o início da próxima linha
                    $pdf->SetXY($startX, $startY + $rowHeight);
                    // --- FIM DA NOVA LÓGICA DE DESENHO MANUAL ---
                }
                // ===================================================================
                // FIM DA LÓGICA DE LINHA CORRIGIDA
                // ===================================================================

            } else {
                 $pdf->SetFont('Arial','I',9);
                 $pdf->Cell(0,7, toISO('Nenhum item encontrado para este pregão.'), 0, 1, 'C');
            }
             $pdf->Ln(10); 
        }
    }

    $pdf->Output('I', 'relatorio_pregoes_detalhado.pdf');

} catch (Exception $e) {
    die("Erro ao gerar PDF: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine());
}
?>