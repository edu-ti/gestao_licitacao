<?php
// ===================================================================
// ARQUIVO: imprimir_consignado.php
// RELATÓRIO EXTRATO DE CONSIGNADO (RESUMIDO E DETALHADO)
// ===================================================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'fpdf/fpdf.php';

// Define caminho das fontes
define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');

if (!isset($_GET['pregao_id']) || !filter_var($_GET['pregao_id'], FILTER_VALIDATE_INT)) {
    die("ID do pregão inválido.");
}

$pregao_id = $_GET['pregao_id'];

class PDF_Relatorio extends FPDF {
    public $numero_edital;
    public $orgao_comprador;
    public $numero_contrato;

    function Header() {
        // Logo
        $logoPath = __DIR__ . '/imagens/LOGO-FR.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 25);
        }
        
        // Título e Informações do Pregão
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(40, 10);
        $this->Cell(0, 6, utf8_decode('EXTRATO DE GESTÃO DE CONSIGNADO'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 9);
        $this->SetX(40);
        $this->Cell(0, 5, utf8_decode("Órgão: " . $this->orgao_comprador), 0, 1, 'L');
        $this->SetX(40);
        $this->Cell(0, 5, utf8_decode("Edital: " . $this->numero_edital . "  |  Contrato: " . $this->numero_contrato), 0, 1, 'L');
        
        $this->Ln(5);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb} - Gerado em ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
    
    // Função para células com quebra de linha ajustada
    function VCell($w, $h, $x, $t, $align='L', $fill=false) {
        $this->SetXY($x, $this->GetY());
        $this->MultiCell($w, $h, $t, 0, $align, $fill);
    }
}

try {
    $db = new Database();
    $pdo = $db->connect();

    // 1. Dados do Pregão
    $sqlPregao = "SELECT p.*, c.numero_contrato 
                  FROM pregoes p 
                  LEFT JOIN consignados c ON p.id = c.pregao_id 
                  WHERE p.id = ?";
    $stmt = $pdo->prepare($sqlPregao);
    $stmt->execute([$pregao_id]);
    $pregao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pregao) die("Pregão não encontrado.");

    // 2. Dados dos Itens
    $sqlItens = "SELECT i.* FROM itens_pregoes i 
                 WHERE i.pregao_id = ? 
                 ORDER BY i.numero_lote ASC, i.numero_item ASC";
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute([$pregao_id]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // Inicia PDF
    $pdf = new PDF_Relatorio();
    $pdf->numero_edital = $pregao['numero_edital'];
    $pdf->orgao_comprador = $pregao['orgao_comprador'];
    $pdf->numero_contrato = $pregao['numero_contrato'] ?? 'N/D';
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $loteAtual = null;

    foreach ($itens as $item) {
        // --- QUEBRA POR LOTE ---
        $loteItem = !empty($item['numero_lote']) ? $item['numero_lote'] : 'ÚNICO';
        
        if ($loteItem !== $loteAtual) {
            if ($loteAtual !== null) $pdf->AddPage(); // Opcional: Quebrar página por lote se desejar, ou apenas dar espaço
            
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(50, 50, 50);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 8, utf8_decode("LOTE " . $loteItem), 1, 1, 'L', true);
            $pdf->SetTextColor(0);
            $loteAtual = $loteItem;
        }

        // --- DADOS PRINCIPAIS DO ITEM (RESUMO) ---
        // Cálculos
        $qtdLicitada = $item['quantidade'];
        $qtdEntregue = $item['qtd_entregue'] ?? 0;
        $saldoRestante = $qtdLicitada - $qtdEntregue;
        $vlrUnit = $item['valor_unitario'];
        $vlrFaturado = $qtdEntregue * $vlrUnit;
        $vlrAFaturar = $saldoRestante * $vlrUnit;

        $pdf->Ln(2);
        
        // Linha 1 do Item: Identificação e Saldos Físicos
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(15, 6, "Item " . $item['numero_item'], 1, 0, 'C', true);
        $pdf->Cell(115, 6, utf8_decode(substr($item['descricao'], 0, 70)), 1, 0, 'L', true); // Descrição truncada no título
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(20, 6, "Lic: " . $qtdLicitada, 1, 0, 'C', true);
        $pdf->Cell(20, 6, "Entr: " . $qtdEntregue, 1, 0, 'C', true);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 6, "Saldo: " . $saldoRestante, 1, 1, 'C', true);

        // Linha 2 do Item: Valores Financeiros
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(65, 5, "Vlr Unit: R$ " . number_format($vlrUnit, 2, ',', '.'), 'LNB', 0, 'L'); // Left, No Top, Bottom
        $pdf->Cell(65, 5, "Total Faturado: R$ " . number_format($vlrFaturado, 2, ',', '.'), 'NB', 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(60, 5, "A Faturar: R$ " . number_format($vlrAFaturar, 2, ',', '.'), 'RNB', 1, 'R'); // Right, No Top, Bottom

        // --- BUSCAR AFCS ---
        $stmtAFC = $pdo->prepare("SELECT * FROM afcs_consignado WHERE item_id = ? ORDER BY created_at DESC");
        $stmtAFC->execute([$item['id']]);
        $afcs = $stmtAFC->fetchAll(PDO::FETCH_ASSOC);

        if (count($afcs) > 0) {
            $pdf->Ln(1);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(190, 5, utf8_decode("--- INFORMAÇÕES DA AFC (VINCULANDO ITEM DO LOTE) ---"), 0, 1, 'L');
            
            foreach ($afcs as $afc) {
                // Cabeçalho da AFC
                $pdf->SetX(15);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(18, 5, utf8_decode("Nº AFC:"), 0, 0, 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(40, 5, utf8_decode($afc['numero_afc']), 0, 0, 'L');
                
                // Detalhe Kits inline se possível, ou bloco abaixo
                $kits = json_decode($afc['detalhes_kit'], true);
                $entregue = json_decode($afc['detalhes_entregue'], true);
                
                $pdf->Ln(5);
                
                // Tabela de Kits da AFC
                $pdf->SetX(20);
                $pdf->SetFont('Arial', 'I', 7);
                $pdf->Cell(50, 4, utf8_decode("Componente do Kit"), 'B', 0, 'L');
                $pdf->Cell(20, 4, "A Entregar", 'B', 0, 'C');
                $pdf->Cell(20, 4, "Entregue", 'B', 0, 'C');
                $pdf->Cell(20, 4, "Falta", 'B', 1, 'C');

                $componentesMap = [
                    'oxigenador' => 'Oxigenador', 'bomba' => 'Bomba Cent.', 
                    'hemoconcentrador' => 'Hemoconcentrador', 'tubos' => 'Conj. Tubos', 
                    'cardioplegia' => 'Cardioplegia'
                ];

                $hasKitInfo = false;
                foreach ($componentesMap as $key => $label) {
                    $qPlan = intval($kits[$key] ?? 0);
                    $qReal = intval($entregue[$key] ?? 0);
                    $qFalta = max(0, $qPlan - $qReal);

                    if ($qPlan > 0 || $qReal > 0) {
                        $hasKitInfo = true;
                        $pdf->SetX(20);
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell(50, 4, utf8_decode($label), 0, 0, 'L');
                        $pdf->Cell(20, 4, $qPlan, 0, 0, 'C');
                        $pdf->Cell(20, 4, $qReal, 0, 0, 'C');
                        // Destaca falta se > 0
                        $pdf->SetFont('Arial', $qFalta > 0 ? 'B' : '', 7);
                        $pdf->Cell(20, 4, $qFalta, 0, 1, 'C');
                    }
                }
                if (!$hasKitInfo) {
                    $pdf->SetX(20);
                    $pdf->Cell(0, 4, "-", 0, 1);
                }
                $pdf->Ln(1);
            }
        }

        // --- BUSCAR CIS ---
        $stmtCI = $pdo->prepare("SELECT * FROM cis_consignado WHERE item_id = ? ORDER BY created_at DESC");
        $stmtCI->execute([$item['id']]);
        $cis = $stmtCI->fetchAll(PDO::FETCH_ASSOC);

        if (count($cis) > 0) {
            $pdf->Ln(1);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(190, 5, utf8_decode("--- INFORMAÇÕES DA CI ---"), 0, 1, 'L');

            foreach ($cis as $ci) {
                // Cabeçalho CI
                $pdf->SetX(15);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(15, 5, "CI: " . utf8_decode($ci['numero_ci']), 0, 0, 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(35, 5, "Empenho: " . utf8_decode($ci['numero_empenho']), 0, 0, 'L');
                $pdf->Cell(35, 5, "Pedido: " . utf8_decode($ci['numero_pedido']), 0, 0, 'L');
                $pdf->Cell(30, 5, "NF: " . utf8_decode($ci['numero_nota_fiscal']), 0, 0, 'L');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(35, 5, "Total CI: R$ " . number_format($ci['valor_total'], 2, ',', '.'), 0, 1, 'R');

                // Detalhe Produtos
                $produtos = json_decode($ci['detalhes_produtos'], true);
                if (!empty($produtos)) {
                    $pdf->SetX(20);
                    $pdf->SetFont('Arial', 'I', 7);
                    $pdf->Cell(90, 4, "Produto Entregue", 'B', 0, 'L');
                    $pdf->Cell(30, 4, "Referencia", 'B', 0, 'L');
                    $pdf->Cell(30, 4, "Lote Fab.", 'B', 0, 'C');
                    $pdf->Cell(20, 4, "Vlr Unit (Item)", 'B', 1, 'R'); // Usando valor unitário do item como referência

                    $pdf->SetFont('Arial', '', 7);
                    foreach ($produtos as $prod) {
                        $pdf->SetX(20);
                        $nome = isset($prod['produto']) ? substr($prod['produto'], 0, 50) : '-';
                        $ref = $prod['referencia'] ?? '-';
                        $loteFab = $prod['lote_manual'] ?? ($prod['lote'] ?? '-');
                        
                        $pdf->Cell(90, 4, utf8_decode($nome), 0, 0, 'L');
                        $pdf->Cell(30, 4, utf8_decode($ref), 0, 0, 'L');
                        $pdf->Cell(30, 4, utf8_decode($loteFab), 0, 0, 'C');
                        $pdf->Cell(20, 4, number_format($vlrUnit, 2, ',', '.'), 0, 1, 'R');
                    }
                }
                $pdf->Ln(2);
            }
        }
        
        $pdf->Ln(3); // Espaço entre itens
        $pdf->SetDrawColor(200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY()); // Linha separadora de itens
        $pdf->Ln(2);
    }

    $pdf->Output('I', 'Relatorio_Consignado_Resumido.pdf');

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>