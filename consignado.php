<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';

$mensagem = '';
$tipo_mensagem = ''; // 'success' ou 'error'
$pregao = null;
$itens_agrupados = [];
$pregoes_disponiveis = [];
$lista_vinculados = [];

// ID do pregão selecionado via GET
$pregao_id = isset($_GET['pregao_id']) ? filter_var($_GET['pregao_id'], FILTER_VALIDATE_INT) : null;

try {
    $db = new Database();
    $pdo = $db->connect();

    // ---------------------------------------------------------
    // AUTO-CONFIGURAÇÃO E MIGRAÇÃO DE COLUNAS
    // ---------------------------------------------------------
    
    // 1. Tabela de Vínculos (Consignados)
    $pdo->exec("CREATE TABLE IF NOT EXISTS consignados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pregao_id INT NOT NULL,
        numero_contrato VARCHAR(50) NOT NULL,
        created_by_user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pregao_id) REFERENCES pregoes(id) ON DELETE CASCADE
    )");

    // 2. Tabela de Produtos de Consignação
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_consignacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(50),
        lote VARCHAR(50),
        produto VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Adicionar colunas novas na tabela itens_pregoes se não existirem
    $colunas_novas = [
        'codigo_catmat' => 'VARCHAR(50)',
        'qtd_entregue' => 'INT DEFAULT 0',
        'saldo_hospital' => 'INT DEFAULT 0',
        'qtd_faturada' => 'INT DEFAULT 0',
        'cons_a_faturar' => 'INT DEFAULT 0',
        'observacao_item' => 'TEXT'
    ];

    foreach ($colunas_novas as $coluna => $tipo) {
        try {
            $pdo->query("SELECT $coluna FROM itens_pregoes LIMIT 1");
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE itens_pregoes ADD COLUMN $coluna $tipo");
        }
    }

    // ---------------------------------------------------------
    // 1. PROCESSAMENTO DE FORMULÁRIOS (POST)
    // ---------------------------------------------------------

    // Ação: ATUALIZAR INFORMAÇÕES DO ITEM (Via Modal Mais Info)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_item_consignado'])) {
        $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $catmat = $_POST['codigo_catmat'] ?? '';
        $q_entregue = intval($_POST['qtd_entregue']);
        $s_hospital = intval($_POST['saldo_hospital']);
        $q_faturada = intval($_POST['qtd_faturada']);
        $c_a_faturar = intval($_POST['cons_a_faturar']);
        $obs_item = $_POST['observacao_item'] ?? '';

        if ($item_id) {
            try {
                $sql_update_item = "UPDATE itens_pregoes SET 
                                    codigo_catmat = ?, 
                                    qtd_entregue = ?, 
                                    saldo_hospital = ?, 
                                    qtd_faturada = ?, 
                                    cons_a_faturar = ?, 
                                    observacao_item = ? 
                                    WHERE id = ?";
                $stmt_upd = $pdo->prepare($sql_update_item);
                $stmt_upd->execute([$catmat, $q_entregue, $s_hospital, $q_faturada, $c_a_faturar, $obs_item, $item_id]);
                
                $mensagem = "Informações do item atualizadas com sucesso!";
                $tipo_mensagem = 'success';
                // Mantém o ID do pregão para recarregar a página corretamente
                $pregao_id = $_POST['pregao_id_redirect'] ?? $pregao_id;
            } catch (Exception $e) {
                $mensagem = "Erro ao atualizar item: " . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        }
    }

    // Ação: CADASTRAR PRODUTO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_produto'])) {
        $ref = $_POST['ref_produto'] ?? '';
        $lote = $_POST['lote_produto'] ?? '';
        $prod = $_POST['nome_produto'] ?? '';

        if ($prod) {
            try {
                $sql_prod = "INSERT INTO produtos_consignacao (referencia, lote, produto) VALUES (?, ?, ?)";
                $pdo->prepare($sql_prod)->execute([$ref, $lote, $prod]);
                $mensagem = "Produto cadastrado com sucesso!";
                $tipo_mensagem = 'success';
            } catch (Exception $e) {
                $mensagem = "Erro ao cadastrar produto: " . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        }
    }

    // Ação: VINCULAR PREGÃO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['vincular_consignado'])) {
        $p_id = $_POST['pregao_id_hidden'];
        $n_contrato = $_POST['numero_contrato'];
        $u_id = $_SESSION['user_id'];

        try {
            $stmt_check = $pdo->prepare("SELECT id FROM consignados WHERE pregao_id = ?");
            $stmt_check->execute([$p_id]);
            
            if ($stmt_check->rowCount() > 0) {
                $sql_update = "UPDATE consignados SET numero_contrato = ? WHERE pregao_id = ?";
                $pdo->prepare($sql_update)->execute([$n_contrato, $p_id]);
                $mensagem = "Vínculo atualizado com sucesso! Contrato: " . htmlspecialchars($n_contrato);
            } else {
                $sql_insert = "INSERT INTO consignados (pregao_id, numero_contrato, created_by_user_id) VALUES (?, ?, ?)";
                $pdo->prepare($sql_insert)->execute([$p_id, $n_contrato, $u_id]);
                $mensagem = "Pregão vinculado com sucesso ao Contrato Nº " . htmlspecialchars($n_contrato);
            }
            $tipo_mensagem = 'success';
            $pregao_id = null; // Volta para a lista
        } catch (Exception $e) {
            $mensagem = "Erro ao vincular: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }

    // Ação: EXCLUIR VÍNCULO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['excluir_consignado_id'])) {
        $id_excluir = intval($_POST['excluir_consignado_id']);
        try {
            $pdo->prepare("DELETE FROM consignados WHERE id = ?")->execute([$id_excluir]);
            $mensagem = "Vínculo excluído com sucesso!";
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }

    // ---------------------------------------------------------
    // 2. BUSCAR DADOS
    // ---------------------------------------------------------

    $stmt_lista = $pdo->query("SELECT id, numero_edital, orgao_comprador FROM pregoes ORDER BY created_at DESC");
    $pregoes_disponiveis = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

    $sql_vinculados = "SELECT 
                        c.id as consignado_id, 
                        c.numero_contrato, 
                        c.created_at as data_vinculo,
                        p.id as pregao_id,
                        p.numero_edital, 
                        p.numero_processo, 
                        p.orgao_comprador, 
                        p.status
                       FROM consignados c 
                       JOIN pregoes p ON c.pregao_id = p.id 
                       ORDER BY c.created_at DESC";
    $lista_vinculados = $pdo->query($sql_vinculados)->fetchAll(PDO::FETCH_ASSOC);

    if ($pregao_id) {
        $stmt_pregao = $pdo->prepare("SELECT * FROM pregoes WHERE id = ?");
        $stmt_pregao->execute([$pregao_id]);
        $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

        $stmt_existente = $pdo->prepare("SELECT numero_contrato FROM consignados WHERE pregao_id = ?");
        $stmt_existente->execute([$pregao_id]);
        $contrato_existente = $stmt_existente->fetchColumn();
        $valor_contrato_inicial = $contrato_existente ? $contrato_existente : 'Não Informado';

        if ($pregao) {
            $stmt_itens = $pdo->prepare(
                "SELECT i.*, f.nome AS fornecedor_nome 
                 FROM itens_pregoes i 
                 JOIN fornecedores f ON i.fornecedor_id = f.id 
                 WHERE i.pregao_id = ? 
                 ORDER BY f.nome ASC, i.numero_lote ASC, CAST(i.numero_item AS UNSIGNED) ASC"
            );
            $stmt_itens->execute([$pregao_id]);
            foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $lote_key = !empty($item['numero_lote']) ? $item['numero_lote'] : 'SEM_LOTE';
                $itens_agrupados[$item['fornecedor_nome']][$lote_key][] = $item;
            }
        }
    }

} catch (Exception $e) {
    $mensagem = "Erro de conexão: " . $e->getMessage();
    $tipo_mensagem = 'error';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Consignado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.30">
    <!-- FontAwesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print { 
            body { background-color: white; padding: 0; } 
            .no-print { display: none !important; } 
            .container { box-shadow: none !important; } 
        }
        /* Estilos personalizados para a tabela consignado */
        .table-header-custom th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px 4px;
            border-bottom: 2px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
        }
        .table-row-custom td {
            padding: 8px 4px;
            font-size: 0.8rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            text-align: center;
        }
        .table-row-custom td.text-left { text-align: left; }
        .table-row-custom td.text-right { text-align: right; }
        
        /* Estilo para a linha de observação */
        .row-observacao td {
            background-color: #fafafa;
            padding: 8px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .obs-label {
            font-weight: bold;
            font-size: 0.75rem;
            color: #4b5563;
            text-transform: uppercase;
            margin-right: 8px;
        }
        
        .obs-content {
            font-size: 0.85rem;
            color: #1f2937;
            font-style: italic;
        }

        .btn-mais-info {
            color: #2563eb;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .btn-mais-info:hover { text-decoration: underline; }
    </style>
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php 
            $page_title = 'Gestão de Consignado';
            include 'header.php'; 
        ?>
        
        <!-- Cabeçalho Principal -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4 no-print gap-4">
            <h2 class="text-2xl font-bold text-gray-800">Cadastrar Pregão Consignado</h2>
            
            <div class="flex flex-wrap gap-2 justify-end">
                <button type="button" onclick="openModalProduto()" class="btn btn-outline border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold px-4 py-2 rounded-lg border-2">
                    <i class="fas fa-plus-circle mr-2"></i> CADASTRO DE PRODUTOS
                </button>

                <?php if ($pregao): ?>
                    <a href="consignado.php" class="btn btn-secondary border-red-500 text-red-600 hover:bg-red-50 hover:text-red-700 bg-white">
                        Voltar
                    </a>
                <?php endif; ?>
                
                <a href="dashboard.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">
                    &larr; Voltar ao Painel
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="<?php echo $tipo_mensagem == 'success' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200'; ?> p-4 mb-6 rounded-md border flex justify-between items-center">
                <span><?php echo htmlspecialchars($mensagem); ?></span>
                <button onclick="this.parentElement.style.display='none'" class="text-xl font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Área de Seleção (Busca) -->
        <div class="bg-gray-50 p-6 rounded-lg border mb-8 no-print shadow-sm">
            <label class="block text-sm font-semibold text-gray-600 mb-2">Selecione o Pregão para Vincular:</label>
            <form method="GET" action="consignado.php" class="flex flex-col md:flex-row gap-4 items-center">
                <div class="flex-grow w-full">
                    <select name="pregao_id" id="pregao_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white h-[42px] focus:ring-2 focus:ring-blue-500 outline-none" onchange="this.form.submit()">
                        <option value="">-- Selecione um Pregão --</option>
                        <?php foreach ($pregoes_disponiveis as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($pregao_id == $p['id']) ? 'selected' : ''; ?>>
                                Edital: <?php echo htmlspecialchars($p['numero_edital']); ?> - <?php echo htmlspecialchars($p['orgao_comprador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary w-full md:w-auto h-[42px] bg-[#2f84bd] text-white hover:bg-[#256a9e] border-none">
                    Carregar Informações
                </button>
            </form>
        </div>

        <?php if ($pregao): ?>
            <!-- ========================================== -->
            <!-- VISTA DE DETALHES E TABELA COMPLETA -->
            <!-- ========================================== -->
            <form method="POST" action="consignado.php" id="form-vincular">
                <input type="hidden" name="pregao_id_hidden" value="<?php echo $pregao['id']; ?>">

                <!-- Informações do Pregão -->
                <div class="mb-8 p-6 bg-[#f7f6f6] rounded-lg border">
                    <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">Informações do Pregão</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-gray-700 text-sm">
                        <div><strong>Edital:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_edital']); ?></span></div>
                        <div><strong>Processo:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_processo']); ?></span></div>
                        <div class="lg:col-span-2"><strong>Órgão Comprador:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['orgao_comprador']); ?></span></div>
                        
                        <div class="flex items-center space-x-2 mt-2">
                            <strong>Status:</strong>
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($pregao['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- TABELA DE ITENS ATUALIZADA -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-700 mb-4">Itens e Propostas</h3>
                    <?php if (empty($itens_agrupados)): ?>
                        <div class="text-center text-gray-500 p-4 border rounded-lg bg-gray-50">Nenhum item registrado neste pregão.</div>
                    <?php else: ?>
                        <?php foreach ($itens_agrupados as $fornecedor_nome => $lotes_do_fornecedor): ?>
                            <div class="mb-8 break-inside-avoid shadow-sm rounded-lg border overflow-hidden bg-white">
                                <h4 class="text-lg font-bold text-white p-3 bg-gray-800 border-b">
                                    <?php echo htmlspecialchars($fornecedor_nome); ?>
                                </h4>
                                <?php foreach ($lotes_do_fornecedor as $lote_nome => $itens_do_lote): ?>
                                    <?php if ($lote_nome !== 'SEM_LOTE'): ?>
                                        <div class="bg-blue-50 p-2 text-center border-b border-blue-100">
                                            <span class="text-sm font-bold text-blue-800 uppercase tracking-wide">
                                                <i class="fas fa-box-open mr-1"></i> <?php echo htmlspecialchars($lote_nome); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="table-header-custom">
                                                    <th class="w-10">Nº</th>
                                                    <th class="w-24">E-fisco<br>CATMAT</th>
                                                    <th class="text-left w-64">Descrição</th>
                                                    <th class="w-16">QTD<br>TOTAL<br>LICITADO</th>
                                                    <th class="w-16">CONS<br>ENTREGUE</th>
                                                    <th class="w-16">SALDO<br>REST.<br>LICITADO</th>
                                                    <th class="w-16">SALDO CONS.<br>HOSPITAL</th>
                                                    <th class="w-24">VALOR UNIT<br>NA PROPOSTA</th>
                                                    <th class="w-16">CONS<br>FATURADO</th>
                                                    <th class="w-16">CONS<br>A FATURAR</th>
                                                    <th class="w-24">VALOR TOTAL<br>NA PROPOSTA</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($itens_do_lote as $item): 
                                                    // Cálculos
                                                    $qtd_total = $item['quantidade'];
                                                    $qtd_entregue = $item['qtd_entregue'] ?? 0;
                                                    $saldo_rest_licitado = $qtd_total - $qtd_entregue;
                                                    $saldo_hospital = $item['saldo_hospital'] ?? 0;
                                                    $valor_unit = $item['valor_unitario'];
                                                    $qtd_faturada = $item['qtd_faturada'] ?? 0;
                                                    $cons_a_faturar = $item['cons_a_faturar'] ?? 0;
                                                    $valor_total = $qtd_total * $valor_unit;
                                                ?>
                                                    <!-- Linha do Item -->
                                                    <tr class="table-row-custom hover:bg-gray-50">
                                                        <td class="font-bold"><?php echo htmlspecialchars($item['numero_item']); ?></td>
                                                        <td class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($item['codigo_catmat'] ?? '-'); ?></td>
                                                        <td class="text-left text-gray-700 leading-tight py-3">
                                                            <div class="line-clamp-2" title="<?php echo htmlspecialchars($item['descricao']); ?>">
                                                                <?php echo htmlspecialchars($item['descricao']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-400 mt-1">Marca: <?php echo htmlspecialchars($item['fabricante'] ?? '-'); ?></div>
                                                        </td>
                                                        <td class="font-bold bg-gray-50"><?php echo $qtd_total; ?></td>
                                                        <td><?php echo $qtd_entregue; ?></td>
                                                        <td class="font-bold text-blue-700 bg-blue-50"><?php echo $saldo_rest_licitado; ?></td>
                                                        <td class="bg-yellow-50 font-semibold text-yellow-700"><?php echo $saldo_hospital; ?></td>
                                                        <td class="text-right whitespace-nowrap">R$ <?php echo number_format($valor_unit, 2, ',', '.'); ?></td>
                                                        <td class="text-green-700"><?php echo $qtd_faturada; ?></td>
                                                        <td class="text-red-600 font-bold"><?php echo $cons_a_faturar; ?></td>
                                                        <td class="text-right whitespace-nowrap font-bold bg-gray-50">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                                                    </tr>
                                                    
                                                    <!-- Linha de Observação e Ação -->
                                                    <tr class="row-observacao">
                                                        <td colspan="11">
                                                            <div class="flex justify-between items-center w-full">
                                                                <div class="flex items-start flex-grow mr-4">
                                                                    
                                                                    <span class="obs-label">OBSERVAÇÃO:</span>
                                                                    <span class="obs-content">
                                                                        <?php echo !empty($item['observacao_item']) ? htmlspecialchars($item['observacao_item']) : '<span class="text-gray-400">Nenhuma observação registrada.</span>'; ?>
                                                                    </span>
                                                                </div>
                                                                <button type="button" class="btn-mais-info whitespace-nowrap" 
                                                                        onclick='openModalItemInfo(<?php echo json_encode($item); ?>)'>
                                                                    MAIS INFO 
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Botão de Ação -->
                <div class="flex justify-end pt-4 no-print">
                    <button type="button" onclick="openModalVincular()" class="btn btn-success text-lg px-8 py-3 shadow-lg transform hover:scale-105 transition-transform duration-200 flex items-center gap-2">
                        <i class="fas fa-file-contract"></i>
                        Vincular para Consignação
                    </button>
                </div>

                <!-- MODAL DE VINCULAÇÃO -->
                <div id="modal-vincular" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm">
                    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg">
                        <div class="flex justify-between items-center mb-6 border-b pb-4">
                            <h3 class="text-2xl font-bold text-gray-800">Vincular Pregão</h3>
                            <button type="button" onclick="closeModalVincular()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
                        </div>
                        
                        <div class="mb-6 space-y-4">
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                <p class="text-sm text-blue-800 font-medium">Vinculando Edital:</p>
                                <p class="text-xl font-bold text-blue-900"><?php echo htmlspecialchars($pregao['numero_edital']); ?></p>
                            </div>

                            <div>
                                <label for="numero_contrato" class="block text-sm font-bold text-gray-700 mb-2">Número do Contrato *</label>
                                <div class="flex gap-2">
                                    <div class="relative w-full">
                                        <input type="text" name="numero_contrato" id="numero_contrato" 
                                               class="w-full pl-4 pr-10 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                               value="<?php echo htmlspecialchars($valor_contrato_inicial); ?>"
                                               readonly
                                               required>
                                    </div>
                                    
                                    <button type="button" onclick="enableEditContrato()" class="btn btn-primary px-4 py-3" title="Editar manualmente">
                                        Editar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button type="button" onclick="closeModalVincular()" class="btn btn-secondary px-6">Cancelar</button>
                            <button type="submit" name="vincular_consignado" class="btn btn-success px-6 shadow-md hover:shadow-lg">Salvar</button>
                        </div>
                    </div>
                </div>
            </form>

        <?php else: ?>
            
            <!-- LISTA DE VINCULADOS (TELA INICIAL) -->
            <div class="mt-8">
                <div class="flex items-center gap-2 mb-6">
                    <h3 class="text-xl font-bold text-gray-700">Órgãos/Pregões já Vinculados</h3>
                    <div class="flex-grow border-t border-gray-200 ml-4"></div>
                </div>

                <?php if (empty($lista_vinculados)): ?>
                    <div class="text-center p-12 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                        <i class="fas fa-file-contract text-4xl text-gray-300 mb-3"></i>
                        <p class="text-lg text-gray-500">Nenhum pregão vinculado encontrado.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md border">
                        <table class="min-w-full leading-normal">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Edital</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nº Processo</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Órgão</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Contrato</th>
                                    <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($lista_vinculados as $v): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($v['numero_edital']); ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($v['numero_processo'] ?? 'N/D'); ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($v['orgao_comprador']); ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($v['numero_contrato']); ?></td>
                                    <td class="px-5 py-4 text-center flex justify-center items-center gap-3">
                                        <a href="consignado.php?pregao_id=<?php echo $v['pregao_id']; ?>" class="text-blue-600 hover:text-blue-900 font-medium text-sm">Ver Detalhes</a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover este vínculo?');">
                                            <input type="hidden" name="excluir_consignado_id" value="<?php echo $v['consignado_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1" title="Excluir">
                                                <i class="fas fa-trash-alt"></i> Excluir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <!-- MODAL DE CADASTRO DE PRODUTO -->
    <div id="modal-produto" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="text-xl font-bold text-gray-800">Cadastro de Produto</h3>
                <button type="button" onclick="closeModalProduto()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
            </div>
            
            <form method="POST" action="consignado.php">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">REF.</label>
                        <input type="text" name="ref_produto" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LOTE</label>
                        <input type="text" name="lote_produto" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PRODUTO</label>
                        <input type="text" name="nome_produto" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Descrição do produto" required>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                    <button type="button" onclick="closeModalProduto()" class="btn btn-secondary px-4">Cancelar</button>
                    <button type="submit" name="cadastrar_produto" class="btn btn-primary px-4 bg-blue-600 hover:bg-blue-700 text-white">Salvar Produto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- NOVO MODAL: MAIS INFO / EDIÇÃO DO ITEM -->
    <div id="modal-item-info" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-2xl my-10">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="text-xl font-bold text-gray-800">Detalhes e Saldos do Item</h3>
                <button type="button" onclick="closeModalItemInfo()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
            </div>
            
            <form method="POST" action="consignado.php" id="form-item-info">
                <input type="hidden" name="atualizar_item_consignado" value="1">
                <input type="hidden" name="item_id" id="modal_item_id">
                <input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">

                <div class="bg-gray-50 p-4 rounded-lg border mb-6">
                    <p class="text-sm font-bold text-gray-600">ITEM <span id="modal_item_num"></span></p>
                    <p class="text-md text-gray-900 font-medium mt-1" id="modal_item_desc"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">E-fisco / CATMAT</label>
                        <input type="text" name="codigo_catmat" id="modal_catmat" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">QTD Total Licitado</label>
                        <input type="text" id="modal_qtd_licitado" class="w-full px-3 py-2 border rounded-lg bg-gray-100 text-gray-500" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">CONS Entregue</label>
                        <input type="number" name="qtd_entregue" id="modal_qtd_entregue" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Saldo CONS Hospital</label>
                        <input type="number" name="saldo_hospital" id="modal_saldo_hospital" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">CONS Faturado</label>
                        <input type="number" name="qtd_faturada" id="modal_qtd_faturada" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">CONS A Faturar</label>
                        <input type="number" name="cons_a_faturar" id="modal_cons_a_faturar" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Observação</label>
                    <textarea name="observacao_item" id="modal_observacao" rows="3" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações relevantes sobre o item..."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModalItemInfo()" class="btn btn-secondary px-6">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funções para Modal de Vinculação
        function openModalVincular() {
            const inputPage = document.getElementById('numero_contrato');
            const inputModal = document.getElementById('numero_contrato_modal');
            if(inputPage && inputModal) {
                inputModal.value = inputPage.value;
            }
            document.getElementById('modal-vincular').classList.remove('hidden');
        }

        function closeModalVincular() {
            document.getElementById('modal-vincular').classList.add('hidden');
        }

        function enableEditContrato() {
            const input = document.getElementById('numero_contrato');
            input.removeAttribute('readonly');
            if(input.value === 'Não Informado') input.value = '';
            input.focus();
            input.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
            input.classList.add('bg-white', 'text-gray-900');
        }

        // Funções para Modal de Produto
        function openModalProduto() {
            document.getElementById('modal-produto').classList.remove('hidden');
        }

        function closeModalProduto() {
            document.getElementById('modal-produto').classList.add('hidden');
        }

        // Funções para Modal Mais Info (Item)
        function openModalItemInfo(item) {
            document.getElementById('modal_item_id').value = item.id;
            document.getElementById('modal_item_num').textContent = item.numero_item;
            document.getElementById('modal_item_desc').textContent = item.descricao;
            document.getElementById('modal_catmat').value = item.codigo_catmat || '';
            document.getElementById('modal_qtd_licitado').value = item.quantidade;
            document.getElementById('modal_qtd_entregue').value = item.qtd_entregue || 0;
            document.getElementById('modal_saldo_hospital').value = item.saldo_hospital || 0;
            document.getElementById('modal_qtd_faturada').value = item.qtd_faturada || 0;
            document.getElementById('modal_cons_a_faturar').value = item.cons_a_faturar || 0;
            document.getElementById('modal_observacao').value = item.observacao_item || '';
            
            document.getElementById('modal-item-info').classList.remove('hidden');
        }

        function closeModalItemInfo() {
            document.getElementById('modal-item-info').classList.add('hidden');
        }
    </script>
</body>
</html>