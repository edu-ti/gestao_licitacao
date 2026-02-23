<?php
require_once 'auth.php';
require_once 'Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$pregao_id = (int) $_POST['pregao_id'];
$fornecedor_id = (int) $_POST['fornecedor_id'];

// Capturar dados do formulário
$rep_nome = $_POST['rep_nome'] ?? '';
$rep_nacionalidade = $_POST['rep_nacionalidade'] ?? '';
$rep_estado_civil = $_POST['rep_estado_civil'] ?? '';
$rep_funcao = $_POST['rep_funcao'] ?? '';
$rep_endereco = $_POST['rep_endereco'] ?? '';
$rep_cpf = $_POST['rep_cpf'] ?? '';
$rep_rg = $_POST['rep_rg'] ?? '';
$rep_email = $_POST['rep_email'] ?? '';

$emp_endereco = $_POST['emp_endereco'] ?? '';
$emp_telefone = $_POST['emp_telefone'] ?? '';
$emp_email = $_POST['emp_email'] ?? '';
$banco_num = $_POST['banco_num'] ?? '';
$banco_nome = $_POST['banco_nome'] ?? '';
$agencia_num = $_POST['agencia_num'] ?? '';
$agencia_nome = $_POST['agencia_nome'] ?? '';
$conta_corrente = $_POST['conta_corrente'] ?? '';
$validade_proposta = $_POST['validade_proposta'] ?? '';
$data_assinatura = $_POST['data_assinatura'] ?? '';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Buscar Pregão
    $stmt_pregao = $pdo->prepare("SELECT * FROM pregoes WHERE id = ?");
    $stmt_pregao->execute([$pregao_id]);
    $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

    // Buscar Fornecedor
    $stmt_fornecedor = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ?");
    $stmt_fornecedor->execute([$fornecedor_id]);
    $fornecedor = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);

    // Buscar Itens
    $stmt_itens = $pdo->prepare(
        "SELECT * FROM itens_pregoes 
         WHERE pregao_id = ? AND fornecedor_id = ? 
         ORDER BY numero_lote ASC, CAST(numero_item AS UNSIGNED) ASC, numero_item ASC"
    );
    $stmt_itens->execute([$pregao_id, $fornecedor_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    if (!$pregao || !$fornecedor) {
        die("Pregão ou Fornecedor não encontrado.");
    }

    // Agrupar por Lote
    $lotes = [];
    $valor_global = 0;
    foreach ($itens as $item) {
        $lote_num = !empty($item['numero_lote']) ? $item['numero_lote'] : 'ÚNICO';
        if (!isset($lotes[$lote_num])) {
            $lotes[$lote_num] = [
                'itens' => [],
                'total_lote' => 0
            ];
        }
        $lotes[$lote_num]['itens'][] = $item;
        $total_item = $item['quantidade'] * $item['valor_unitario'];
        $lotes[$lote_num]['total_lote'] += $total_item;
        $valor_global += $total_item;
    }

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Função para escrever valores em extenso (PT-BR)
// -----------------------------------------------------------------------------
function valorPorExtenso($valor = 0, $bolExibirMoeda = true, $bolPalavraFeminina = false)
{
    $valor = str_replace(['.', ','], ['', '.'], $valor);
    $singular = null;
    $plural = null;

    if ($bolExibirMoeda) {
        $singular = ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
        $plural = ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];
    } else {
        $singular = ['', '', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
        $plural = ['', '', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];
    }

    $c = ['', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    $d = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $d10 = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];

    if ($bolPalavraFeminina) {
        if ($valor == 1)
            $u[1] = 'uma';
        if ($valor == 2)
            $u[2] = 'duas';
        $c[2] = 'duzentas';
        $c[3] = 'trezentas';
        $c[4] = 'quatrocentas';
        $c[5] = 'quinhentas';
        $c[6] = 'seiscentas';
        $c[7] = 'setecentas';
        $c[8] = 'oitocentas';
        $c[9] = 'novecentas';
    }

    $z = 0;
    $valor = number_format($valor, 2, '.', '.');
    $inteiro = explode('.', $valor);

    for ($i = 0; $i < count($inteiro); $i++) {
        for ($ii = mb_strlen($inteiro[$i]); $ii < 3; $ii++) {
            $inteiro[$i] = '0' . $inteiro[$i];
        }
    }

    $rt = null;
    $fim = count($inteiro) - ($inteiro[count($inteiro) - 1] > 0 ? 1 : 2);
    for ($i = 0; $i < count($inteiro); $i++) {
        $valor = $inteiro[$i];
        $rc = (($valor > 100) && ($valor < 200)) ? 'cento' : $c[$valor[0]];
        $rd = ($valor[1] < 2) ? '' : $d[$valor[1]];
        $ru_d10 = '';
        if ($valor > 0) {
            $ru_d10 = ($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]];
        }
        $r = $rc . (($rc && ($rd || $ru_d10)) ? ' e ' : '') . $rd . (($rd && $ru_d10) ? ' e ' : '') . $ru_d10;
        $t = count($inteiro) - 1 - $i;
        $r .= $r ? ' ' . ($valor > 1 ? $plural[$t] : $singular[$t]) : '';
        if ($valor == '000')
            $z++;
        elseif ($z > 0)
            $z--;
        if (($t == 1) && ($z > 0) && ($inteiro[0] > 0)) {
            $r .= (($z > 1) ? ' de ' : '') . $plural[$t];
        }
        if ($r) {
            $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ', ' : ' e ') : ' ') . $r;
        }
    }

    $rt = mb_substr($rt, 1);
    return $rt ? trim($rt) : 'zero';
}

function cnpjFormats($cnpj)
{
    if (!$cnpj)
        return '';
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) == 14) {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
    return $cnpj;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Proposta
        <?php echo htmlspecialchars($pregao['numero_edital']); ?>
    </title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap');

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        .page {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 16px;
            margin: 0;
            text-transform: uppercase;
        }

        .header p {
            margin: 2px 0;
            font-size: 12px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
            text-decoration: underline;
        }

        .info-section {
            margin-bottom: 20px;
            font-size: 14px;
        }

        .info-section p {
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .lote-header {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
            margin-top: 15px;
        }

        .lote-total {
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            font-size: 12px;
        }

        .global-total {
            font-weight: bold;
            margin-top: 30px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .disclaimer {
            font-size: 12px;
            margin-top: 30px;
        }

        .disclaimer ul {
            padding-left: 20px;
            margin-top: 10px;
        }

        .disclaimer li {
            margin-bottom: 10px;
        }

        .signature-block {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-line {
            width: 300px;
            border-top: 1px solid #000;
            margin-bottom: 5px;
        }

        .rep-info {
            font-size: 12px;
            margin-bottom: 30px;
        }

        .rep-info strong {
            display: inline-block;
            width: 250px;
        }

        .data-assinatura {
            margin-top: 40px;
            margin-bottom: 40px;
        }

        @media print {
            body {
                background: none;
                margin: 0;
            }

            .page {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                max-width: 100%;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="text-align: center; padding: 20px; background: #3b82f6; color: white; margin-bottom: 20px;">
        <button onclick="window.print()"
            style="padding: 10px 20px; background: white; color: #3b82f6; border: none; font-weight: bold; cursor: pointer; border-radius: 5px;">
            <svg style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 5px;"
                fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z"
                    clip-rule="evenodd"></path>
            </svg> Imprimir PDF
        </button>
        <p style="margin-top: 10px; font-size: 14px;">Use Ctrl+P e escolha "Salvar como PDF". Configure as margens para
            "Nenhuma" ou "Padrão".</p>
    </div>

    <div class="page">
        <div class="header text-center">
            <h1>
                <?php echo htmlspecialchars($fornecedor['nome']); ?>
            </h1>
            <p>CNPJ:
                <?php echo htmlspecialchars(cnpjFormats($fornecedor['cnpj'])); ?> / FONE:
                <?php echo htmlspecialchars($emp_telefone); ?>
            </p>
            <p>
                <?php echo htmlspecialchars($emp_endereco); ?>
            </p>
            <p>
                <?php echo htmlspecialchars($emp_email); ?>
            </p>
        </div>

        <div class="title">PROPOSTA DE PREÇO</div>

        <div class="info-section">
            <p><strong>PREGÃO/EDITAL Nº:</strong>
                <?php echo htmlspecialchars($pregao['numero_edital']); ?>
            </p>
            <p><strong>PROCESSO Nº:</strong>
                <?php echo htmlspecialchars($pregao['numero_processo'] ?? 'N/D'); ?>
            </p>
            <br>
            <p>Ao</p>
            <p><strong>
                    <?php echo htmlspecialchars($pregao['orgao_comprador']); ?>
                </strong></p>
            <br>
            <p><strong>OBJETO</strong></p>
            <p>
                <?php echo nl2br(htmlspecialchars($pregao['objeto'])); ?>
            </p>
        </div>

        <?php foreach ($lotes as $lote_num => $lote): ?>
            <div class="lote-header">
                <?php echo 'LOTE ' . htmlspecialchars($lote_num); ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th width="5%">ITEM</th>
                        <th width="35%">DESCRIÇÃO / ANVISA</th>
                        <th width="15%">FABRICANTE / MARCA / MODELO</th>
                        <th width="5%">UND.</th>
                        <th width="5%">QTD.</th>
                        <th width="15%">VALOR UNITÁRIO</th>
                        <th width="20%">VALOR TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lote['itens'] as $item): ?>
                        <tr>
                            <td class="text-center">
                                <?php echo htmlspecialchars($item['numero_item']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['descricao']); ?>
                            </td>
                            <td>
                                <strong>Fab:</strong>
                                <?php echo htmlspecialchars($item['fabricante'] ?? '-'); ?><br>
                                <strong>Mod:</strong>
                                <?php echo htmlspecialchars($item['modelo'] ?? '-'); ?>
                            </td>
                            <td class="text-center">UND</td> <!-- Fixo ou buscaria do BD se existisse o campo unidade -->
                            <td class="text-center">
                                <?php echo htmlspecialchars($item['quantidade']); ?>
                            </td>
                            <td class="text-right">R$
                                <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-right font-bold">R$
                                <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="lote-total">
                VALOR TOTAL DO LOTE: R$
                <?php echo number_format($lote['total_lote'], 2, ',', '.'); ?><br>
                <span>R$
                    <?php echo number_format($lote['total_lote'], 2, ',', '.'); ?> (
                    <?php echo mb_strtoupper(valorPorExtenso($lote['total_lote'], true, false), 'UTF-8'); ?>)
                </span>
            </div>
            <br>
        <?php endforeach; ?>

        <div class="global-total">
            VALOR TOTAL GLOBAL DA PROPOSTA: R$
            <?php echo number_format($valor_global, 2, ',', '.'); ?><br>
            R$
            <?php echo number_format($valor_global, 2, ',', '.'); ?> (
            <?php echo mb_strtoupper(valorPorExtenso($valor_global, true, false), 'UTF-8'); ?>)
        </div>

        <div class="disclaimer">
            <p><strong>VALIDADE DA PROPOSTA:</strong> O Prazo de Validade desta Proposta é de
                <?php echo htmlspecialchars($validade_proposta); ?>, contados a partir da data da abertura deste
                Certame.
            </p>
            <p><strong>ESPECIFICAÇÕES DOS MATERIAIS:</strong> de acordo com o Termo de Referência.</p>
            <p><strong>OBRIGAÇÕES DA CONTRATADA:</strong> de acordo com o Termo de Referência.</p>
            <br>
            <p><strong>DECLARAÇÃO:</strong></p>
            <p>Declaramos que o preço proposto contempla todos os encargos e tributos que possam ocorrer em relação aos
                materiais objeto desta licitação, bem como declarar que atender a todas as especificações exigidas neste
                edital.</p>
        </div>

        <div class="rep-info" style="margin-top: 40px; page-break-inside: avoid;">
            <p><strong>REPRESENTANTE LEGAL QUE ASSINARÁ A ATA DE REGISTRO DE PREÇOS DECORRENTE DESTA LICITAÇÃO:</strong>
            </p>
            <p>NOME:
                <?php echo htmlspecialchars($rep_nome); ?>
            </p>
            <p>NACIONALIDADE:
                <?php echo htmlspecialchars($rep_nacionalidade); ?>
            </p>
            <p>ESTADO CIVIL:
                <?php echo htmlspecialchars($rep_estado_civil); ?>
            </p>
            <p>FUNÇÃO:
                <?php echo htmlspecialchars($rep_funcao); ?>
            </p>
            <p>ENDEREÇO RESIDENCIAL:
                <?php echo htmlspecialchars($rep_endereco); ?>
            </p>
            <p>CPF (com cópia):
                <?php echo htmlspecialchars($rep_cpf); ?>
            </p>
            <p>R.G./ÓRGÃO EXPEDIDOR (com cópia):
                <?php echo htmlspecialchars($rep_rg); ?>
            </p>
            <p>E-mail:
                <?php echo htmlspecialchars($rep_email); ?>
            </p>
        </div>

        <div class="rep-info" style="page-break-inside: avoid;">
            <p><strong>DADOS DA EMPRESA:</strong></p>
            <p>RAZÃO SOCIAL:
                <?php echo htmlspecialchars($fornecedor['nome']); ?>
            </p>
            <p>CNPJ:
                <?php echo htmlspecialchars(cnpjFormats($fornecedor['cnpj'])); ?>
            </p>
            <p>TELEFONE:
                <?php echo htmlspecialchars($emp_telefone); ?>
            </p>
            <p>E-MAIL:
                <?php echo htmlspecialchars($emp_email); ?>
            </p>
            <p>ENDEREÇO:
                <?php echo htmlspecialchars($emp_endereco); ?>
            </p>
            <p>BANCO Nº:
                <?php echo htmlspecialchars($banco_num); ?>
            </p>
            <p>NOME DO BANCO:
                <?php echo htmlspecialchars($banco_nome); ?>
            </p>
            <p>AGÊNCIA Nº:
                <?php echo htmlspecialchars($agencia_num); ?>
            </p>
            <p>NOME DA AGÊNCIA:
                <?php echo htmlspecialchars($agencia_nome); ?>
            </p>
            <p>CONTA CORRENTE Nº:
                <?php echo htmlspecialchars($conta_corrente); ?>
            </p>
        </div>

        <div class="data-assinatura">
            <?php echo htmlspecialchars($data_assinatura); ?>.
        </div>

        <div class="signature-block">
            <div class="signature-line"></div>
            <strong>
                <?php echo htmlspecialchars($rep_nome); ?>
            </strong><br>
            <?php echo htmlspecialchars($fornecedor['nome']); ?><br>
            CNPJ:
            <?php echo htmlspecialchars(cnpjFormats($fornecedor['cnpj'])); ?>
        </div>

    </div>
</body>

</html>