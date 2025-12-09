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
$observacoes = [];
$pregoes_disponiveis = [];
$lista_vinculados = [];

// ID do pregão selecionado via GET
$pregao_id = isset($_GET['pregao_id']) ? filter_var($_GET['pregao_id'], FILTER_VALIDATE_INT) : null;

try {
    $db = new Database();
    $pdo = $db->connect();

    // ---------------------------------------------------------
    // AUTO-CONFIGURAÇÃO: Criação das tabelas necessárias
    // ---------------------------------------------------------
    
    // Tabela de Vínculos (Consignados)
    $pdo->exec("CREATE TABLE IF NOT EXISTS consignados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pregao_id INT NOT NULL,
        numero_contrato VARCHAR(50) NOT NULL,
        created_by_user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pregao_id) REFERENCES pregoes(id) ON DELETE CASCADE
    )");

    // Tabela de Produtos de Consignação (Solicitação do Print 1)
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_consignacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(50),
        lote VARCHAR(50),
        produto VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ---------------------------------------------------------
    // 1. PROCESSAMENTO DE FORMULÁRIOS (POST)
    // ---------------------------------------------------------

    // Ação: CADASTRAR PRODUTO (Novo)
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
            // Verifica se já está vinculado
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
            
            // Log opcional
            // logActivity($pdo, $u_id, 'consignados', 'VINCULAR', $p_id, "Contrato: $n_contrato");
            
            $tipo_mensagem = 'success';
            $pregao_id = null; // Volta para a lista
        } catch (Exception $e) {
            $mensagem = "Erro ao vincular: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }

    // Ação: EXCLUIR VÍNCULO (Solicitação do Print)
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

    // Lista de Pregões para o Select
    $stmt_lista = $pdo->query("SELECT id, numero_edital, orgao_comprador FROM pregoes ORDER BY created_at DESC");
    $pregoes_disponiveis = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

    // Lista de Vinculados (Tabela Principal)
    // Adicionado p.numero_processo conforme solicitado
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

    // Detalhes do Pregão Selecionado
    if ($pregao_id) {
        $stmt_pregao = $pdo->prepare("SELECT * FROM pregoes WHERE id = ?");
        $stmt_pregao->execute([$pregao_id]);
        $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

        // Busca contrato existente
        $stmt_existente = $pdo->prepare("SELECT numero_contrato FROM consignados WHERE pregao_id = ?");
        $stmt_existente->execute([$pregao_id]);
        $contrato_existente = $stmt_existente->fetchColumn();
        $valor_contrato_inicial = $contrato_existente ? $contrato_existente : 'Não Informado';

        if ($pregao) {
            // Itens
            $stmt_itens = $pdo->prepare(
                "SELECT i.*, f.nome AS fornecedor_nome 
                 FROM itens_pregoes i 
                 JOIN fornecedores f ON i.fornecedor_id = f.id 
                 WHERE i.pregao_id = ? 
                 ORDER BY f.nome ASC, i.numero_lote ASC, i.numero_item ASC"
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
    <link rel="stylesheet" href="css/style.css?v=2.29">
    <style>
        @media print { 
            body { background-color: white; padding: 0; } 
            .no-print { display: none !important; } 
            .container { box-shadow: none !important; } 
        }
        input[readonly] {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
            border-color: #d1d5db;
        }
        input:not([readonly]) {
            background-color: #ffffff;
            color: #111827;
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }
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
                <!-- Botão Cadastro de Produtos (Solicitado no Print 1) -->
                <button type="button" onclick="openModalProduto()" class="btn btn-outline border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold px-4 py-2 rounded-lg border-2">
                    CADASTRO DE PRODUTOS
                </button>

                <!-- Botão Voltar (Solicitado no Print 4 - Só aparece se estiver nos detalhes) -->
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
            <!-- VISTA DE DETALHES E VINCULAÇÃO -->
            <!-- ========================================== -->
            <form method="POST" action="consignado.php" id="form-vincular">
                <input type="hidden" name="pregao_id_hidden" value="<?php echo $pregao['id']; ?>">
                <input type="hidden" name="numero_edital_hidden" value="<?php echo htmlspecialchars($pregao['numero_edital']); ?>">

                <!-- Informações do Pregão -->
                <div class="mb-8 p-6 bg-[#f7f6f6] rounded-lg border">
                    <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">Informações do Pregão</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-gray-700 text-sm">
                        <div><strong>Edital:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_edital']); ?></span></div>
                        <div><strong>Processo:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_processo']); ?></span></div>
                        <div><strong>Modalidade:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['modalidade']); ?></span></div>
                        <div class="lg:col-span-2"><strong>Órgão Comprador:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['orgao_comprador']); ?></span></div>
                        <div><strong>UASG:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['uasg']); ?></span></div>
                        <div class="lg:col-span-3"><strong>Local da Disputa:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['local_disputa']); ?></span></div>
                        
                        <div class="flex items-center space-x-2 mt-2">
                            <strong>Status:</strong>
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($pregao['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t text-sm">
                        <strong>Objeto:</strong>
                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($pregao['objeto']); ?></p>
                    </div>
                </div>

                <!-- Itens -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-700 mb-4">Itens e Propostas</h3>
                    <?php if (empty($itens_agrupados)): ?>
                        <div class="text-center text-gray-500 p-4 border rounded-lg bg-gray-50">Nenhum item registrado neste pregão.</div>
                    <?php else: ?>
                        <?php foreach ($itens_agrupados as $fornecedor_nome => $lotes_do_fornecedor): ?>
                            <div class="mb-6 break-inside-avoid shadow-sm rounded-lg border overflow-hidden">
                                <h4 class="text-base font-bold text-gray-800 p-3 bg-gray-100 border-b">
                                    <?php echo htmlspecialchars($fornecedor_nome); ?>
                                </h4>
                                <?php foreach ($lotes_do_fornecedor as $lote_nome => $itens_do_lote): ?>
                                    <?php if ($lote_nome !== 'SEM_LOTE'): ?>
                                        <h5 class="text-sm font-semibold text-gray-600 p-2 bg-gray-50 text-center border-b"><?php echo htmlspecialchars($lote_nome); ?></h5>
                                    <?php endif; ?>
                                    <div class="overflow-x-auto bg-white">
                                        <table class="min-w-full leading-normal text-sm">
                                            <thead class="bg-white border-b">
                                                <tr>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Nº</th>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-600 w-1/3">Descrição</th>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Marca</th>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Qtd.</th>
                                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($itens_do_lote as $item): ?>
                                                    <tr class="border-b last:border-0 hover:bg-gray-50">
                                                        <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($item['numero_item']); ?></td>
                                                        <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($item['descricao']); ?></td>
                                                        <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($item['fabricante'] ?? '-'); ?></td>
                                                        <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($item['quantidade']); ?></td>
                                                        <td class="px-4 py-3 font-semibold text-gray-700">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
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
                        <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
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
                                <p class="text-xs text-gray-500 mt-2">Clique em "Editar" para inserir o número do contrato manualmente.</p>
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
            
            <!-- ========================================== -->
            <!-- LISTA DE VINCULADOS (TELA INICIAL) -->
            <!-- ========================================== -->
            <div class="mt-8">
                <div class="flex items-center gap-2 mb-6">
                    <h3 class="text-xl font-bold text-gray-700">Órgãos/Pregões já Vinculados</h3>
                    <div class="flex-grow border-t border-gray-200 ml-4"></div>
                </div>

                <?php if (empty($lista_vinculados)): ?>
                    <div class="text-center p-12 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        <p class="text-lg text-gray-500">Nenhum pregão vinculado encontrado.</p>
                        <p class="text-sm text-gray-400">Selecione um pregão acima para começar.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md border">
                        <table class="min-w-full leading-normal">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Edital</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nº Processo</th> <!-- NOVA COLUNA -->
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Órgão</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contrato</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-5 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($lista_vinculados as $v): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($v['numero_edital']); ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($v['numero_processo'] ?? 'N/D'); ?></td> <!-- DADO PROCESSO -->
                                    <td class="px-5 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($v['orgao_comprador']); ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($v['numero_contrato']); ?></td>
                                    <td class="px-5 py-4 text-sm">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Vinculado
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-center flex justify-center items-center gap-3">
                                        <a href="consignado.php?pregao_id=<?php echo $v['pregao_id']; ?>" class="text-blue-600 hover:text-blue-900 font-medium text-sm">Ver Detalhes</a>
                                        
                                        <!-- Botão de Excluir (Solicitado no Print 5) -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover este vínculo?');">
                                            <input type="hidden" name="excluir_consignado_id" value="<?php echo $v['consignado_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1" title="Excluir">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Excluir
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

    <!-- MODAL DE CADASTRO DE PRODUTO (NOVO) -->
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

    <!-- MODAL DE VINCULAÇÃO -->
    <div id="modal-vincular" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="text-2xl font-bold text-gray-800">Vincular Pregão</h3>
                <button type="button" onclick="closeModalVincular()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
            </div>
            
            <!-- Conteúdo do modal de vinculação permanece o mesmo -->
            <div class="mb-6 space-y-4">
                <!-- ... campos já existentes ... -->
                 <p class="text-sm text-gray-600 mb-2">Confirme o número do contrato para realizar a vinculação.</p>
                 <div class="flex gap-2">
                    <input type="text" id="numero_contrato_modal" name="numero_contrato" form="form-vincular" class="w-full px-3 py-2 border rounded-lg bg-gray-100 text-gray-500" readonly>
                    <button type="button" onclick="enableEditContratoModal()" class="btn btn-primary px-3">Editar</button>
                 </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModalVincular()" class="btn btn-secondary px-6">Cancelar</button>
                <button type="submit" form="form-vincular" name="vincular_consignado" class="btn btn-success px-6 shadow-md hover:shadow-lg">Salvar</button>
            </div>
        </div>
    </div>

    <script>
        // Funções para Modal de Vinculação
        function openModalVincular() {
            // Copia o valor do input da página para o modal, se houver
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
        
        function enableEditContratoModal() {
            const input = document.getElementById('numero_contrato_modal');
            input.removeAttribute('readonly');
            if(input.value === 'Não Informado') input.value = '';
            input.focus();
            input.classList.remove('bg-gray-100', 'text-gray-500');
            input.classList.add('bg-white', 'text-gray-900');
            
            // Sincroniza de volta com o input hidden ou principal se necessário
            input.addEventListener('input', function() {
                const mainInput = document.getElementById('numero_contrato');
                if(mainInput) mainInput.value = this.value;
            });
        }

        // Funções para Modal de Produto
        function openModalProduto() {
            document.getElementById('modal-produto').classList.remove('hidden');
        }

        function closeModalProduto() {
            document.getElementById('modal-produto').classList.add('hidden');
        }
    </script>
</body>
</html>