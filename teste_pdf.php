<?php
// Teste simples de geração de PDF com tabela para verificar alinhamento
require_once(__DIR__ . '/fpdf/fpdf.php');

define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');

function toISO($string) {
    if ($string === null || $string === '') return '';
    $string = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    if ($string === false) $string = '';
    $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $string);
    if ($result === false) $result = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $string);
    return $result;
}

class TestPDF extends FPDF {
    function GetNbLines($w, $txt) {
        if(!isset($this->CurrentFont)) $this->Error('No font has been set');
        $cw = $this->CurrentFont['cw'];
        if($w==0) $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i<$nb) {
            $c = $s[$i];
            if($c=="\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c==' ') $sep = $i;
            $l += $cw[$c];
            if($l>$wmax) {
                if($sep==-1) { if($i==$j) $i++; } else $i = $sep+1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$pdf = new TestPDF('L','mm','A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,10, toISO('Teste de Alinhamento de Tabela'),0,1,'C');

$lineHeight = 5;
$w_partic = 55; $w_fab = 47; $w_mod = 47; $w_unit = 33; $w_vtotal = 33; $w_tipo = 20; $w_st = 42;

// Cabeçalho
$pdf->SetFont('Arial','B',8);
$pdf->SetFillColor(210, 210, 210);
$pdf->Cell($w_partic, 6, 'Participante', 1, 0, 'C', true);
$pdf->Cell($w_fab, 6, 'Fabricante', 1, 0, 'C', true);
$pdf->Cell($w_mod, 6, 'Modelo', 1, 0, 'C', true);
$pdf->Cell($w_unit, 6, 'Vlr. Unit.', 1, 0, 'C', true);
$pdf->Cell($w_vtotal, 6, 'Vlr. Total', 1, 0, 'C', true);
$pdf->Cell($w_tipo, 6, 'Tipo Cota', 1, 0, 'C', true);
$pdf->Cell($w_st, 6, 'Status', 1, 1, 'C', true);

// Dados de teste
$rows = [
    ['INSTRAMED INDUSTRIA MEDICO HISPITALAR LTDA', 'INSTRAMED', 'MODELO X', 'R$ 0,00', 'R$ 0,00', 'Ampla Concorrência', 'Classificada'],
    ['Empresa Teste Ltda', 'Fab Teste', 'Mod Y', 'R$ 1.234,56', 'R$ 12.345,60', 'Cota Exclusiva', 'Classificada'],
];

$pdf->SetFont('Arial','',8);
$itemFill = false;

foreach ($rows as $row) {
    $txt_partic = toISO($row[0]);
    $txt_fab = toISO($row[1]);
    $txt_mod = toISO($row[2]);
    $txt_unit = toISO($row[3]);
    $txt_vtotal = toISO($row[4]);
    $txt_tipo = toISO($row[5]);
    $txt_status = toISO($row[6]);

    $nb_partic = $pdf->GetNbLines($w_partic, $txt_partic);
    $nb_fab = $pdf->GetNbLines($w_fab, $txt_fab);
    $nb_mod = $pdf->GetNbLines($w_mod, $txt_mod);
    $nb_unit = $pdf->GetNbLines($w_unit, $txt_unit);
    $nb_vtotal = $pdf->GetNbLines($w_vtotal, $txt_vtotal);
    $nb_tipo = $pdf->GetNbLines($w_tipo, $txt_tipo);
    $nb_st = $pdf->GetNbLines($w_st, $txt_status);

    $max_lines = max($nb_partic, $nb_fab, $nb_mod, $nb_unit, $nb_vtotal, $nb_tipo, $nb_st, 1);
    $rowHeight = $max_lines * $lineHeight;

    $startX = 10;
    $startY = $pdf->GetY();

    $style = $itemFill ? 'DF' : 'D';
    $pdf->Rect($startX, $startY, $w_partic, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic, $startY, $w_fab, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic + $w_fab, $startY, $w_mod, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic + $w_fab + $w_mod, $startY, $w_unit, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit, $startY, $w_vtotal, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal, $startY, $w_tipo, $rowHeight, $style);
    $pdf->Rect($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal + $w_tipo, $startY, $w_st, $rowHeight, $style);

    $textStartY = $startY + ($rowHeight - ($max_lines * $lineHeight)) / 2;

    $pdf->SetXY($startX, $textStartY);
    $pdf->MultiCell($w_partic, $lineHeight, $txt_partic, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic, $textStartY);
    $pdf->MultiCell($w_fab, $lineHeight, $txt_fab, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic + $w_fab, $textStartY);
    $pdf->MultiCell($w_mod, $lineHeight, $txt_mod, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod, $textStartY);
    $pdf->MultiCell($w_unit, $lineHeight, $txt_unit, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit, $textStartY);
    $pdf->MultiCell($w_vtotal, $lineHeight, $txt_vtotal, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal, $textStartY);
    $pdf->MultiCell($w_tipo, $lineHeight, $txt_tipo, 0, 'C', false);
    $pdf->SetXY($startX + $w_partic + $w_fab + $w_mod + $w_unit + $w_vtotal + $w_tipo, $textStartY);
    $pdf->MultiCell($w_st, $lineHeight, $txt_status, 0, 'C', false);

    $pdf->SetXY($startX, $startY + $rowHeight);
    $itemFill = !$itemFill;
}

$pdf->Output('I', 'teste_alinhamento.pdf');
?>
