<?php
ob_start(); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';

// 1. GERENCIAMENTO DE SESSÃO E MENSAGENS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensagem = $_SESSION['flash_message'] ?? '';
$tipo_mensagem = $_SESSION['flash_type'] ?? '';

unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

$pregao = null;
$itens_agrupados = [];
$pregoes_disponiveis = [];
$lista_vinculados = [];
$produtos_disponiveis_para_modal = [];

$pregao_id = isset($_GET['pregao_id']) ? filter_var($_GET['pregao_id'], FILTER_VALIDATE_INT) : null;

try {
    $db = new Database();
    $pdo = $db->connect();

    // --- CRIAÇÃO DE TABELAS ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS consignados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pregao_id INT NOT NULL,
        numero_contrato VARCHAR(50) NOT NULL,
        created_by_user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pregao_id) REFERENCES pregoes(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_consignacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(50),
        lote VARCHAR(50),
        produto VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS afcs_consignado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        numero_afc VARCHAR(50) NOT NULL,
        qtd_solicitada INT DEFAULT 0,
        qtd_entregue INT DEFAULT 0,
        valor_total DECIMAL(15,2) DEFAULT 0,
        detalhes_kit TEXT,
        detalhes_entregue TEXT,
        observacao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES itens_pregoes(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cis_consignado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        numero_ci VARCHAR(50) NOT NULL,
        numero_empenho VARCHAR(50),
        numero_pedido VARCHAR(50), 
        numero_nota_fiscal VARCHAR(50),
        qtd_solicitada INT DEFAULT 0,
        valor_total DECIMAL(15,2) DEFAULT 0,
        detalhes_produtos TEXT,
        observacao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES itens_pregoes(id) ON DELETE CASCADE
    )");

    // --- MIGRAÇÃO DE COLUNAS (Garante que os campos novos existam) ---
    $colunas_ci = [
        'numero_empenho' => 'VARCHAR(50)',
        'numero_pedido' => 'VARCHAR(50)',
        'numero_nota_fiscal' => 'VARCHAR(50)',
        'qtd_solicitada' => 'INT DEFAULT 0',
        'valor_total' => 'DECIMAL(15,2) DEFAULT 0',
        'detalhes_produtos' => 'TEXT',
        'observacao' => 'TEXT'
    ];
    foreach ($colunas_ci as $coluna => $tipo) {
        try { $pdo->query("SELECT $coluna FROM cis_consignado LIMIT 1"); } 
        catch (Exception $e) { $pdo->exec("ALTER TABLE cis_consignado ADD COLUMN $coluna $tipo"); }
    }

    $colunas_afc = [
        'qtd_solicitada' => 'INT DEFAULT 0',
        'qtd_entregue' => 'INT DEFAULT 0',
        'valor_total' => 'DECIMAL(15,2) DEFAULT 0',
        'detalhes_kit' => 'TEXT',
        'detalhes_entregue' => 'TEXT',
        'observacao' => 'TEXT'
    ];
    foreach ($colunas_afc as $coluna => $tipo) {
        try { $pdo->query("SELECT $coluna FROM afcs_consignado LIMIT 1"); } 
        catch (Exception $e) { $pdo->exec("ALTER TABLE afcs_consignado ADD COLUMN $coluna $tipo"); }
    }

    $colunas_novas = [
        'codigo_catmat' => 'VARCHAR(50)',
        'qtd_entregue' => 'INT DEFAULT 0',
        'observacao_item' => 'TEXT',
        'qtd_faturada' => 'INT DEFAULT 0'
    ];
    foreach ($colunas_novas as $coluna => $tipo) {
        try { $pdo->query("SELECT $coluna FROM itens_pregoes LIMIT 1"); } 
        catch (Exception $e) { $pdo->exec("ALTER TABLE itens_pregoes ADD COLUMN $coluna $tipo"); }
    }

    // =========================================================
    // PROCESSAMENTO DE FORMULÁRIOS
    // =========================================================

    // 1. SALVAR PRODUTO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_produto'])) {
        $ref = $_POST['ref_produto'] ?? '';
        $lote = $_POST['lote_produto'] ?? '';
        $prod = $_POST['nome_produto'] ?? '';
        $redirect_id = isset($_GET['pregao_id']) ? $_GET['pregao_id'] : null;
        
        if ($prod) {
            try {
                $sql = "INSERT INTO produtos_consignacao (referencia, lote, produto) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$ref, $lote, $prod]);
                $_SESSION['flash_message'] = "Produto cadastrado com sucesso!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro ao cadastrar: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        $url = "consignado.php" . ($redirect_id ? "?pregao_id=$redirect_id" : "");
        header("Location: " . $url);
        exit;
    }

    // 2. SALVAR AFC
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_afc'])) {
        $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $num_afc = $_POST['numero_afc'];
        $qtd = intval($_POST['qtd_solicitada']);
        $valor_raw = $_POST['valor_total_hidden'];
        $valor_limpo = str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $valor_raw);
        $valor_tot = floatval($valor_limpo);
        $redirect_id = $_POST['pregao_id_redirect'];
        $obs = $_POST['observacao'];
        
        if ($valor_tot <= 0 && $item_id) {
             $stmt = $pdo->prepare("SELECT valor_unitario FROM itens_pregoes WHERE id = ?");
             $stmt->execute([$item_id]);
             $valor_tot = $qtd * $stmt->fetchColumn();
        }

        $kits = [
            'oxigenador' => $_POST['qtd_oxigenador'] ?? 0,
            'bomba' => $_POST['qtd_bomba'] ?? 0,
            'hemoconcentrador' => $_POST['qtd_hemoconcentrador'] ?? 0,
            'tubos' => $_POST['qtd_tubos'] ?? 0,
            'cardioplegia' => $_POST['qtd_cardioplegia'] ?? 0
        ];
        
        if ($item_id && $num_afc) {
            try {
                $sql = "INSERT INTO afcs_consignado (item_id, numero_afc, qtd_solicitada, valor_total, detalhes_kit, observacao) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$item_id, $num_afc, $qtd, $valor_tot, json_encode($kits), $obs]);
                $_SESSION['flash_message'] = "AFC salva!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        header("Location: consignado.php?pregao_id=" . $redirect_id);
        exit;
    }

    // 3. ATUALIZAR DETALHES AFC
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_detalhes_afc'])) {
        $afc_id = filter_var($_POST['afc_id'], FILTER_VALIDATE_INT);
        $qtd_entregue = intval($_POST['qtd_entregue']);
        $obs = $_POST['observacao'];
        $redirect_id = $_POST['pregao_id_redirect'];
        
        $kits = [
            'oxigenador' => $_POST['kit_entregue_oxigenador'] ?? 0,
            'bomba' => $_POST['kit_entregue_bomba'] ?? 0,
            'hemoconcentrador' => $_POST['kit_entregue_hemoconcentrador'] ?? 0,
            'tubos' => $_POST['kit_entregue_tubos'] ?? 0,
            'cardioplegia' => $_POST['kit_entregue_cardioplegia'] ?? 0
        ];
        
        if ($afc_id) {
            try {
                $sql = "UPDATE afcs_consignado SET qtd_entregue = ?, detalhes_entregue = ?, observacao = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$qtd_entregue, json_encode($kits), $obs, $afc_id]);
                $_SESSION['flash_message'] = "AFC atualizada!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        header("Location: consignado.php?pregao_id=" . $redirect_id);
        exit;
    }

    // 4. SALVAR CI
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_ci'])) {
        $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $num_ci = $_POST['numero_ci'];
        $num_empenho = $_POST['numero_empenho'];
        $num_pedido = $_POST['numero_pedido'] ?? '';
        $num_nf = $_POST['numero_nota_fiscal'] ?? '';
        $qtd = intval($_POST['qtd_solicitada']);
        $valor_raw = $_POST['valor_total_hidden'];
        $valor_limpo = str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $valor_raw);
        $valor_tot = floatval($valor_limpo);
        $obs = $_POST['observacao'];
        $detalhes_produtos = $_POST['detalhes_produtos'] ?? '';
        $redirect_id = $_POST['pregao_id_redirect'];
        
        if ($item_id && $num_ci) {
            try {
                $sql = "INSERT INTO cis_consignado (item_id, numero_ci, numero_empenho, numero_pedido, numero_nota_fiscal, qtd_solicitada, valor_total, observacao, detalhes_produtos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$item_id, $num_ci, $num_empenho, $num_pedido, $num_nf, $qtd, $valor_tot, $obs, $detalhes_produtos]);
                $_SESSION['flash_message'] = "CI salva com sucesso!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro na CI: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        header("Location: consignado.php?pregao_id=" . $redirect_id);
        exit;
    }

    // 5. ATUALIZAR DETALHES CI (ATUALIZADO PARA INCLUIR PEDIDO E NF)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_detalhes_ci'])) {
        $ci_id = filter_var($_POST['ci_id'], FILTER_VALIDATE_INT);
        $num_empenho = $_POST['numero_empenho'];
        $num_pedido = $_POST['numero_pedido'];
        $num_nf = $_POST['numero_nota_fiscal'];
        $obs = $_POST['observacao'];
        $detalhes_produtos = $_POST['detalhes_produtos'] ?? '';
        $redirect_id = $_POST['pregao_id_redirect'];

        if ($ci_id) {
            try {
                $sql = "UPDATE cis_consignado SET numero_empenho = ?, numero_pedido = ?, numero_nota_fiscal = ?, observacao = ?, detalhes_produtos = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$num_empenho, $num_pedido, $num_nf, $obs, $detalhes_produtos, $ci_id]);
                $_SESSION['flash_message'] = "CI atualizada!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        header("Location: consignado.php?pregao_id=" . $redirect_id);
        exit;
    }

    // 6. ATUALIZAR ITEM
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_item_consignado'])) {
        $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $catmat = $_POST['codigo_catmat'] ?? '';
        $q_entregue = intval($_POST['qtd_entregue']);
        $q_faturada = intval($_POST['qtd_faturada']);
        $obs_item = $_POST['observacao_item'] ?? '';
        $redirect_id = $_POST['pregao_id_redirect'];

        if ($item_id) {
            try {
                $sql = "UPDATE itens_pregoes SET codigo_catmat = ?, qtd_entregue = ?, qtd_faturada = ?, observacao_item = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$catmat, $q_entregue, $q_faturada, $obs_item, $item_id]);
                $_SESSION['flash_message'] = "Item atualizado!";
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        header("Location: consignado.php?pregao_id=" . $redirect_id);
        exit;
    }

    // 7. VINCULAR PREGÃO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['vincular_consignado'])) {
        $p_id = $_POST['pregao_id_hidden'];
        $n_contrato = $_POST['numero_contrato'];
        $u_id = $_SESSION['user_id'];

        try {
            $check = $pdo->prepare("SELECT id FROM consignados WHERE pregao_id = ?");
            $check->execute([$p_id]);
            
            if ($check->rowCount() > 0) {
                $pdo->prepare("UPDATE consignados SET numero_contrato = ? WHERE pregao_id = ?")->execute([$n_contrato, $p_id]);
                $_SESSION['flash_message'] = "Contrato atualizado!";
            } else {
                $pdo->prepare("INSERT INTO consignados (pregao_id, numero_contrato, created_by_user_id) VALUES (?, ?, ?)")->execute([$p_id, $n_contrato, $u_id]);
                $_SESSION['flash_message'] = "Pregão vinculado!";
            }
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: consignado.php?pregao_id=" . $p_id); 
        exit;
    }

    // 8. EXCLUIR VÍNCULO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['excluir_consignado_id'])) {
        $id_excluir = intval($_POST['excluir_consignado_id']);
        try {
            $pdo->prepare("DELETE FROM consignados WHERE id = ?")->execute([$id_excluir]);
            $_SESSION['flash_message'] = "Vínculo excluído!";
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Erro: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: consignado.php");
        exit;
    }

    // --- LEITURA DE DADOS ---
    $produtos_disponiveis_para_modal = $pdo->query("SELECT * FROM produtos_consignacao ORDER BY produto ASC")->fetchAll(PDO::FETCH_ASSOC);
    $pregoes_disponiveis = $pdo->query("SELECT id, numero_edital, orgao_comprador FROM pregoes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $sql_vinculados = "SELECT c.id as consignado_id, c.numero_contrato, c.created_at as data_vinculo, p.id as pregao_id, p.numero_edital, p.numero_processo, p.orgao_comprador, p.status FROM consignados c JOIN pregoes p ON c.pregao_id = p.id ORDER BY c.created_at DESC";
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
                $stmt_afc = $pdo->prepare("SELECT * FROM afcs_consignado WHERE item_id = ? ORDER BY created_at DESC");
                $stmt_afc->execute([$item['id']]);
                $item['afcs'] = $stmt_afc->fetchAll(PDO::FETCH_ASSOC);

                $stmt_ci = $pdo->prepare("SELECT * FROM cis_consignado WHERE item_id = ? ORDER BY created_at DESC");
                $stmt_ci->execute([$item['id']]);
                $item['cis'] = $stmt_ci->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0"> 
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php 
            $page_title = 'Gestão de Consignado';
            include 'header.php'; 
        ?>
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4 no-print gap-4">
            <h2 class="text-2xl font-bold text-gray-800">Cadastrar Pregão Consignado</h2>
            <div class="flex flex-wrap gap-2 justify-end">
                <?php if ($pregao_id): ?>
                    <a href="imprimir_consignado.php?pregao_id=<?php echo $pregao_id; ?>" target="_blank" class="btn btn-primary bg-gray-600 hover:bg-gray-700">
                        <i class="fas fa-print mr-2"></i> IMPRIMIR RELATÓRIO
                    </a>
                <?php endif; ?>
                <button type="button" onclick="openModalProduto()" class="btn btn-outline border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold px-4 py-2 rounded-lg border-2">
                    <i class="fas fa-plus-circle mr-2"></i> CADASTRO DE PRODUTOS
                </button>
                <?php if ($pregao): ?>
                    <a href="consignado.php" class="btn btn-secondary border-red-500 text-red-600 hover:bg-red-50 hover:text-red-700 bg-white">Voltar</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">&larr; Voltar ao Painel</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="<?php echo $tipo_mensagem == 'success' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200'; ?> p-4 mb-6 rounded-md border flex justify-between items-center">
                <span><?php echo htmlspecialchars($mensagem); ?></span>
                <button onclick="this.parentElement.style.display='none'" class="text-xl font-bold">&times;</button>
            </div>
        <?php endif; ?>

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
                <button type="submit" class="btn btn-secondary w-full md:w-auto h-[42px] bg-[#2f84bd] text-white hover:bg-[#256a9e] border-none">Carregar Informações</button>
            </form>
        </div>

        <?php if ($pregao): ?>
            <form method="POST" action="consignado.php" id="form-vincular">
                <input type="hidden" name="pregao_id_hidden" value="<?php echo $pregao['id']; ?>">

                <div class="mb-8 p-6 bg-[#f7f6f6] rounded-lg border">
                    <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">Informações do Pregão</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-gray-700 text-sm">
                        <div><strong>Edital:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_edital']); ?></span></div>
                        <div><strong>Processo:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['numero_processo']); ?></span></div>
                        <div class="lg:col-span-2"><strong>Órgão Comprador:</strong> <span class="text-gray-900"><?php echo htmlspecialchars($pregao['orgao_comprador']); ?></span></div>
                        <div class="flex items-center space-x-2 mt-2">
                            <strong>Status:</strong>
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800"><?php echo htmlspecialchars($pregao['status']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- TABELA DE ITENS -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-700 mb-4">Itens e Propostas</h3>
                    <?php if (empty($itens_agrupados)): ?>
                        <div class="text-center text-gray-500 p-4 border rounded-lg bg-gray-50">Nenhum item registrado neste pregão.</div>
                    <?php else: ?>
                        <?php foreach ($itens_agrupados as $fornecedor_nome => $lotes_do_fornecedor): ?>
                            <div class="mb-8 break-inside-avoid shadow-sm rounded-lg border overflow-hidden bg-white">
                                <h4 class="text-lg font-bold text-white p-3 bg-gray-800 border-b"><?php echo htmlspecialchars($fornecedor_nome); ?></h4>
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
                                                    <th class="w-24">VALOR UNIT<br>NA PROPOSTA</th>
                                                    <th class="w-24">CONS<br>FATURADO<br>(R$)</th>
                                                    <th class="w-24">CONS<br>A FATURAR<br>(R$)</th>
                                                    <th class="w-24">VALOR TOTAL<br>NA PROPOSTA</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($itens_do_lote as $item): 
                                                    $qtd_total = $item['quantidade'];
                                                    $qtd_entregue = $item['qtd_entregue'] ?? 0;
                                                    $valor_unit = $item['valor_unitario'];
                                                    $saldo_rest_licitado = $qtd_total - $qtd_entregue;
                                                    $valor_cons_faturado = $valor_unit * $qtd_entregue;
                                                    $valor_cons_a_faturar = $valor_unit * $saldo_rest_licitado;
                                                    $valor_total = $qtd_total * $valor_unit;
                                                ?>
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
                                                        <td class="text-right whitespace-nowrap">R$ <?php echo number_format($valor_unit, 2, ',', '.'); ?></td>
                                                        <td class="text-green-700 font-semibold text-right whitespace-nowrap">R$ <?php echo number_format($valor_cons_faturado, 2, ',', '.'); ?></td>
                                                        <td class="text-red-600 font-bold text-right whitespace-nowrap">R$ <?php echo number_format($valor_cons_a_faturar, 2, ',', '.'); ?></td>
                                                        <td class="text-right whitespace-nowrap font-bold bg-gray-50">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></td>
                                                    </tr>
                                                    
                                                    <tr class="row-action">
                                                        <td colspan="10">
                                                            <div class="flex justify-between items-center w-full">
                                                                <div class="flex items-start flex-grow mr-4 text-left">
                                                                    <span class="obs-label">OBSERVAÇÃO:</span>
                                                                    <span class="obs-content">
                                                                        <?php echo !empty($item['observacao_item']) ? htmlspecialchars($item['observacao_item']) : '<span class="text-gray-400">Nenhuma observação registrada.</span>'; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="whitespace-nowrap">
                                                                    <span class="action-link" onclick='openModalItemInfo(<?php echo json_encode($item); ?>)'>EDITAR</span>
                                                                    <span class="action-link action-link-add" onclick='openModalAddAFC(<?php echo json_encode($item); ?>, "<?php echo $lote_nome; ?>")'>+ AFC</span>
                                                                    <span class="action-link action-link-add" onclick='openModalAddCI(<?php echo json_encode($item); ?>, "<?php echo $lote_nome; ?>")'>+ CI</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <tr class="row-details">
                                                        <td colspan="10" class="text-left">
                                                            <span class="detail-label">AFC:</span>
                                                            <?php if (!empty($item['afcs'])): ?>
                                                                <?php foreach ($item['afcs'] as $afc): ?>
                                                                    <span class="badge-afc" onclick='openModalDetalhesAFC(<?php echo json_encode($afc); ?>, <?php echo json_encode($item); ?>, "<?php echo $lote_nome; ?>")'><?php echo htmlspecialchars($afc['numero_afc']); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <span class="text-gray-400 italic font-normal text-xs">Nenhuma AFC cadastrada</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>

                                                    <tr class="row-details">
                                                        <td colspan="10" class="text-left">
                                                            <span class="detail-label">CI &nbsp;&nbsp;&nbsp;</span>
                                                            <?php if (!empty($item['cis'])): ?>
                                                                <?php foreach ($item['cis'] as $ci): ?>
                                                                    <span class="detail-item" onclick='openModalDetalhesCI(<?php echo json_encode($ci); ?>, <?php echo json_encode($item); ?>, "<?php echo $lote_nome; ?>")' title="Empenho: <?php echo htmlspecialchars($ci['numero_empenho']); ?>">
                                                                        <?php echo htmlspecialchars($ci['numero_ci']); ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <span class="text-gray-400 italic font-normal text-xs">Nenhuma CI cadastrada</span>
                                                            <?php endif; ?>
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

                <div class="flex justify-end pt-4 no-print">
                    <button type="button" onclick="openModalVincular()" class="btn btn-success text-lg px-8 py-3 shadow-lg transform hover:scale-105 transition-transform duration-200 flex items-center gap-2">
                        <i class="fas fa-file-contract"></i> Vincular para Consignação
                    </button>
                </div>
                
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
                                        <input type="text" name="numero_contrato" id="numero_contrato" class="w-full pl-4 pr-10 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" value="<?php echo htmlspecialchars($valor_contrato_inicial); ?>" readonly required>
                                    </div>
                                    <button type="button" onclick="enableEditContrato()" class="btn btn-primary px-4 py-3" title="Editar manualmente">Editar</button>
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
            <form method="POST" action="consignado.php<?php echo $pregao_id ? '?pregao_id='.$pregao_id : ''; ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">REF.</label>
                        <input type="text" name="ref_produto" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
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

    <!-- MODAL EDITAR ITEM -->
    <div id="modal-item-info" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
         <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-2xl my-10">
            <div class="flex justify-between items-center mb-6 border-b pb-4"><h3 class="text-xl font-bold text-gray-800">Editar Item Consignado</h3><button type="button" onclick="closeModalItemInfo()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form method="POST" action="consignado.php" id="form-item-info">
                <input type="hidden" name="atualizar_item_consignado" value="1"><input type="hidden" name="item_id" id="modal_item_id"><input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">
                <div class="bg-gray-50 p-4 rounded-lg border mb-6"><p class="text-sm font-bold text-gray-600">ITEM <span id="modal_item_num"></span></p><p class="text-md text-gray-900 font-medium mt-1" id="modal_item_desc"></p></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">E-fisco / CATMAT</label><input type="text" name="codigo_catmat" id="modal_catmat" class="w-64 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">QTD Total Licitado</label><input type="text" id="modal_qtd_licitado" class="w-64 px-3 py-2 border rounded-lg bg-gray-100 text-gray-500" readonly></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">CONS Entregue</label><input type="number" name="qtd_entregue" id="modal_qtd_entregue" class="w-64 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">QTD Faturada</label><input type="number" name="qtd_faturada" id="modal_qtd_faturada" class="w-64 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                </div>
                <div class="mb-6"><label class="block text-sm font-bold text-gray-700 mb-1">Observação</label><textarea name="observacao_item" id="modal_observacao" rows="3" class="w-64 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações relevantes sobre o item..."></textarea></div>
                <div class="flex justify-end gap-3 pt-4 border-t"><button type="button" onclick="closeModalItemInfo()" class="btn btn-secondary px-6">Cancelar</button><button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">Salvar Alterações</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL ADICIONAR AFC -->
    <div id="modal-afc" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-4xl my-10">
            <div class="modal-header"><h3 class="modal-title">ADICIONAR AFC</h3><button type="button" onclick="closeModalAFC()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form method="POST" action="consignado.php" id="form-afc">
                <input type="hidden" name="salvar_afc" value="1"><input type="hidden" name="item_id" id="afc_item_id"><input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">
                <div class="flex mb-6"><span class="tag-badge" id="afc_lote">LOTE 01</span><span class="tag-badge" id="afc_item_num">ITEM 01</span></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4">
                        <div><label class="form-label">E-fisco / CATMAT</label><input type="text" id="afc_catmat" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                        <div><label class="form-label">QTD SOLICITADA</label><input type="number" name="qtd_solicitada" id="afc_qtd" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" required oninput="calcTotalAFC()"></div>
                        <div><label class="form-label">VALOR UNITÁRIO DO ITEM</label><input type="text" id="afc_unit" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly><input type="hidden" id="afc_unit_val"></div>
                        <div><label class="form-label">VALOR TOTAL DO ITEM</label><input type="text" id="afc_total" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly><input type="hidden" name="valor_total_hidden" id="afc_total_val"></div>
                    </div>
                    <div class="space-y-2">
                         <div class="flex items-center gap-2 mb-2"><label class="form-label mb-0">AFC Nº</label><input type="text" name="numero_afc" class="w-32 px-2 py-1 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" required></div>
                         <div class="kit-section"><h4 class="kit-title">ENTREGAR KITS</h4>
                             <div class="grid grid-cols-[1fr_80p_80p_80p] gap-x-2 gap-y-2 items-center text-sm">
                                 <span class="font-semibold text-gray-700">OXIGENADOR</span><input type="number" name="qtd_oxigenador" class="w-80 px-2 py-1 border rounded text-center">
                                 <span class="font-semibold text-gray-700">BOMBA CENTRÍFUGA</span><input type="number" name="qtd_bomba" class="w-80 px-2 py-1 border rounded text-center">
                                 <span class="font-semibold text-gray-700">HEMOCONCENTRADOR</span><input type="number" name="qtd_hemoconcentrador" class="w-80 px-2 py-1 border rounded text-center">
                                 <span class="font-semibold text-gray-700">CONJUNTO DE TUBOS</span><input type="number" name="qtd_tubos" class="w-80 px-2 py-1 border rounded text-center">
                                 <span class="font-semibold text-gray-700">CARDIOPLEGIA CRISTALOIDE</span><input type="number" name="qtd_cardioplegia" class="w-80 px-2 py-1 border rounded text-center">
                             </div>
                         </div>
                    </div>
                </div>
                <div class="mb-6"><label class="form-label">Observação</label><textarea name="observacao" rows="3" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações relevantes sobre a AFC..."></textarea></div>
                <div class="flex justify-end gap-3 pt-4 border-t"><button type="button" onclick="closeModalAFC()" class="btn btn-secondary px-6">Cancelar</button><button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">SALVAR</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL ADICIONAR CI (ATUALIZADO COM PEDIDO E NF) -->
    <div id="modal-ci" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-8xl my-10">
            <div class="modal-header"><h3 class="modal-title">ADICIONAR CI</h3><button type="button" onclick="closeModalCI()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form method="POST" action="consignado.php" id="form-ci">
                <input type="hidden" name="salvar_ci" value="1"><input type="hidden" name="item_id" id="ci_item_id"><input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">
                <input type="hidden" name="detalhes_produtos" id="ci_detalhes_produtos">

                <div class="flex justify-between items-center mb-6">
                     <div class="flex"><span class="tag-badge" id="ci_lote">LOTE 01</span><span class="tag-badge" id="ci_item_num">ITEM 01</span></div>
                     <!-- CAMPOS GLOBAIS NO TOPO -->
                     <div class="flex gap-2 items-end flex-wrap">
                         <div class="flex flex-col"><label class="form-label mb-1">CI Nº</label><input type="text" name="numero_ci" class="w-24 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" required></div>
                         <div class="flex flex-col"><label class="form-label mb-1">EMPENHO</label><input type="text" name="numero_empenho" class="w-24 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                         <div class="flex flex-col"><label class="form-label mb-1">PEDIDO</label><input type="text" name="numero_pedido" class="w-24 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                         <div class="flex flex-col"><label class="form-label mb-1">NF</label><input type="text" name="numero_nota_fiscal" class="w-24 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                     </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4">
                        <div><label class="form-label">E-fisco / CATMAT</label><input type="text" id="ci_catmat" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                        <div><label class="form-label">QTD SOLICITADA</label><input type="number" name="qtd_solicitada" id="ci_qtd" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" required oninput="calcTotalCI()"></div>
                        <div><label class="form-label">VALOR UNITÁRIO DO ITEM</label><input type="text" id="ci_unit" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly><input type="hidden" id="ci_unit_val"></div>
                        <div><label class="form-label">VALOR TOTAL DO ITEM</label><input type="text" id="ci_total" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly><input type="hidden" name="valor_total_hidden" id="ci_total_val"></div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-center my-4"><button type="button" class="btn btn-outline border-blue-500 text-blue-600 w-full font-bold" onclick="openModalSelecionarProdutos()">+ PRODUTOS</button></div>
                        <div class="border rounded-lg h-40 overflow-y-auto p-2 bg-gray-50 text-xs text-gray-500">
                            <table class="w-full text-left table-fixed">
                                <thead>
                                    <tr class="border-b bg-gray-100">
                                        <th class="pb-2 pt-2 pl-2 w-8/12 font-semibold text-gray-600">PRODUTO</th>
                                        <th class="pb-2 pt-2 w-2/12 font-semibold text-gray-600">REF.</th>
                                        <th class="pb-2 pt-2 w-3/12 font-semibold text-gray-600">LOTE</th>
                                        <th class="pb-2 pt-2 pr-2 w-1/12 text-center font-semibold text-gray-600">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody id="ci_produtos_list">
                                    <!-- Os produtos serão inseridos aqui via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="mb-6"><label class="form-label">Observação</label><textarea name="observacao" rows="3" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações relevantes sobre a CI..."></textarea></div>
                <div class="flex justify-end gap-3 pt-4 border-t"><button type="button" onclick="closeModalCI()" class="btn btn-secondary px-6">Cancelar</button><button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">SALVAR</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL SELECIONAR PRODUTOS -->
    <div id="modal-selecionar-produtos" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-3xl h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-4 border-b pb-4">
                <h3 class="text-xl font-bold text-gray-800">Selecionar Produtos</h3>
                <button type="button" onclick="closeModalSelecionarProdutos()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
            </div>
            <div class="overflow-y-auto flex-grow">
                <table class="min-w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-3">Ref.</th>
                            <th class="px-4 py-3">Produto</th>
                            <th class="px-4 py-3 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos_disponiveis_para_modal as $prod): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono"><?php echo htmlspecialchars($prod['referencia']); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($prod['produto']); ?></td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" onclick='adicionarProdutoAoCI(<?php echo json_encode($prod); ?>)' class="btn btn-sm btn-primary bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                    Adicionar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($produtos_disponiveis_para_modal)): ?>
                        <tr><td colspan="3" class="text-center py-4">Nenhum produto cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 pt-4 border-t flex justify-end">
                <button type="button" onclick="closeModalSelecionarProdutos()" class="btn btn-secondary px-4 py-2 border rounded hover:bg-gray-100">Fechar</button>
            </div>
        </div>
    </div>


    <!-- MODAL DETALHES AFC -->
    <div id="modal-detalhes-afc" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-7xl my-10">
            <div class="modal-header"><h3 class="modal-title">DETALHES AFC</h3><button type="button" onclick="closeModalDetalhesAFC()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form method="POST" action="consignado.php" id="form-detalhes-afc">
                <input type="hidden" name="atualizar_detalhes_afc" value="1"><input type="hidden" name="afc_id" id="det_afc_id"><input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex flex-col gap-2"><div class="flex"><span class="tag-badge" id="det_lote">LOTE 01</span><span class="tag-badge" id="det_item_num">ITEM 01</span></div></div>
                    <div class="flex items-center gap-2"><label class="form-label mb-0 text-lg">AFC Nº</label><input type="text" id="det_numero_afc" class="w-32 px-3 py-2 border rounded-lg bg-gray-100 text-gray-700 font-bold text-center" readonly></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                    <div class="space-y-4">
                        <div><label class="form-label">E-fisco / CATMAT</label><input type="text" id="det_catmat" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                        <div><label class="form-label">QTD SOLICITADA</label><input type="number" id="det_qtd_solicitada" class="w-full px-3 py-2 border rounded-lg form-input-readonly font-bold" readonly></div>
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 space-y-3">
                            <div><label class="form-label text-blue-800">QTD ENTREGUE <span class="text-xs font-normal lowercase">(Inserido Manualmente)</span></label><input type="number" name="qtd_entregue" id="det_qtd_entregue" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold text-blue-900" oninput="calcDetalhesAFC()"></div>
                            <div><label class="form-label text-red-600">FALTA ENTREGAR <span class="text-xs font-normal lowercase">(Calculado)</span></label><input type="number" id="det_falta_entregar" class="w-full px-3 py-2 border rounded-lg bg-red-50 text-red-700 font-bold" readonly></div>
                        </div>
                        <div><label class="form-label">VALOR UNITÁRIO DO ITEM</label><input type="text" id="det_unit" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly><input type="hidden" id="det_unit_val"></div>
                        <div><label class="form-label">VALOR TOTAL DO ITEM</label><input type="text" id="det_total" class="w-full px-3 py-2 border rounded-lg form-input-readonly font-bold" readonly></div>
                    </div>
                    <div class="space-y-4">
                         <div class="kit-section bg-white border-gray-200">
                             <div class="flex justify-between items-center border-b pb-2 mb-3"><h4 class="kit-title mb-0">DETALHAMENTO KITS</h4><span class="text-xs text-gray-500">Qtd Entregue editável manualmente</span></div>
                             <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center text-xs font-semibold text-gray-600 text-center uppercase mb-2"><div class="text-left">Componente</div><div>Entregar</div><div>Entregue</div><div>Falta</div></div>
                             <div id="det_kit_container" class="space-y-2 text-sm">
                                 <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center"><span class="text-gray-800 font-medium truncate">OXIGENADOR DE MEMBRANA</span><input type="text" class="w-full px-1 py-1 border rounded text-center bg-gray-50 text-gray-500 comp-entregar" id="entregar_oxigenador" readonly><input type="number" name="kit_entregue_oxigenador" class="w-full px-1 py-1 border rounded text-center comp-entregue comp-entregue-editable" id="entregue_oxigenador" oninput="calcFaltaComponente(this)"><input type="text" class="w-full px-1 py-1 border rounded text-center bg-red-50 text-red-600 comp-falta" id="falta_oxigenador" readonly></div>
                                 <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center"><span class="text-gray-800 font-medium truncate">BOMBA CENTRIFUGA</span><input type="text" class="w-full px-1 py-1 border rounded text-center bg-gray-50 text-gray-500 comp-entregar" id="entregar_bomba" readonly><input type="number" name="kit_entregue_bomba" class="w-full px-1 py-1 border rounded text-center comp-entregue comp-entregue-editable" id="entregue_bomba" oninput="calcFaltaComponente(this)"><input type="text" class="w-full px-1 py-1 border rounded text-center bg-red-50 text-red-600 comp-falta" id="falta_bomba" readonly></div>
                                 <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center"><span class="text-gray-800 font-medium truncate">HEMOCONCENTRADOR</span><input type="text" class="w-full px-1 py-1 border rounded text-center bg-gray-50 text-gray-500 comp-entregar" id="entregar_hemoconcentrador" readonly><input type="number" name="kit_entregue_hemoconcentrador" class="w-full px-1 py-1 border rounded text-center comp-entregue comp-entregue-editable" id="entregue_hemoconcentrador" oninput="calcFaltaComponente(this)"><input type="text" class="w-full px-1 py-1 border rounded text-center bg-red-50 text-red-600 comp-falta" id="falta_hemoconcentrador" readonly></div>
                                 <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center"><span class="text-gray-800 font-medium truncate">CONJUNTO DE TUBOS</span><input type="text" class="w-full px-1 py-1 border rounded text-center bg-gray-50 text-gray-500 comp-entregar" id="entregar_tubos" readonly><input type="number" name="kit_entregue_tubos" class="w-full px-1 py-1 border rounded text-center comp-entregue comp-entregue-editable" id="entregue_tubos" oninput="calcFaltaComponente(this)"><input type="text" class="w-full px-1 py-1 border rounded text-center bg-red-50 text-red-600 comp-falta" id="falta_tubos" readonly></div>
                                 <div class="grid grid-cols-[1fr_80px_80px_80px] gap-2 items-center"><span class="text-gray-800 font-medium truncate">CARDIOPLEGIA</span><input type="text" class="w-full px-1 py-1 border rounded text-center bg-gray-50 text-gray-500 comp-entregar" id="entregar_cardioplegia" readonly><input type="number" name="kit_entregue_cardioplegia" class="w-full px-1 py-1 border rounded text-center comp-entregue comp-entregue-editable" id="entregue_cardioplegia" oninput="calcFaltaComponente(this)"><input type="text" class="w-full px-1 py-1 border rounded text-center bg-red-50 text-red-600 comp-falta" id="falta_cardioplegia" readonly></div>
                             </div>
                         </div>
                    </div>
                </div>
                <div class="mb-6"><label class="form-label">Observação</label><textarea name="observacao" id="det_observacao" rows="3" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações..."></textarea></div>
                <div class="flex justify-end gap-3 pt-4 border-t"><button type="button" onclick="closeModalDetalhesAFC()" class="btn btn-secondary px-6">Cancelar</button><button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">SALVAR</button></div>
            </form>
        </div>
    </div>

    <!-- ============================================= -->
    <!-- MODAL: DETALHES CI (ATUALIZADO) -->
    <!-- ============================================= -->
    <div id="modal-detalhes-ci" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center hidden z-50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-8xl my-10">
            <div class="modal-header"><h3 class="modal-title">DETALHES CI</h3><button type="button" onclick="closeModalDetalhesCI()" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            
            <form method="POST" action="consignado.php" id="form-detalhes-ci">
                <input type="hidden" name="atualizar_detalhes_ci" value="1">
                <input type="hidden" name="ci_id" id="det_ci_id">
                <input type="hidden" name="pregao_id_redirect" value="<?php echo $pregao_id; ?>">
                <input type="hidden" name="detalhes_produtos" id="det_ci_detalhes_produtos">
                
                <div class="flex justify-between items-center mb-6">
                     <div class="flex"><span class="tag-badge" id="det_ci_lote">LOTE 01</span><span class="tag-badge" id="det_ci_item_num">ITEM 01</span></div>
                     <!-- CAMPOS GLOBAIS NO TOPO (DETALHES) -->
                     <div class="flex gap-2 items-end flex-wrap">
                         <div class="flex flex-col"><label class="form-label mb-1">CI Nº</label><input type="text" id="det_ci_numero" class="w-32 px-3 py-2 border rounded-lg bg-gray-100 font-bold" readonly></div>
                         <div class="flex flex-col"><label class="form-label mb-1">EMPENHO</label><input type="text" name="numero_empenho" id="det_ci_empenho" class="w-32 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                         <div class="flex flex-col"><label class="form-label mb-1">PEDIDO</label><input type="text" name="numero_pedido" id="det_ci_pedido" class="w-32 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                         <div class="flex flex-col"><label class="form-label mb-1">NF</label><input type="text" name="numero_nota_fiscal" id="det_ci_nf" class="w-32 px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none"></div>
                     </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4">
                        <div><label class="form-label">E-fisco / CATMAT</label><input type="text" id="det_ci_catmat" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                        <div><label class="form-label">QTD SOLICITADA</label><input type="number" id="det_ci_qtd" class="w-full px-3 py-2 border rounded-lg form-input-readonly font-bold" readonly></div>
                        <div><label class="form-label">VALOR UNITÁRIO DO ITEM</label><input type="text" id="det_ci_unit" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                        <div><label class="form-label">VALOR TOTAL DO ITEM</label><input type="text" id="det_ci_total" class="w-full px-3 py-2 border rounded-lg form-input-readonly" readonly></div>
                    </div>
                    <div class="space-y-6">
                        <!-- Lista de produtos na CI -->
                        <div class="border rounded-lg h-60 overflow-y-auto p-2 bg-gray-50 text-xs text-gray-500 mt-6">
                            <table class="w-full text-left table-fixed">
                                <thead>
                                    <tr class="border-b bg-gray-100">
                                        <th class="pb-2 pt-2 pl-2 w-8/12 font-semibold text-gray-600">PRODUTO</th>
                                        <th class="pb-2 pt-2 w-2/12 font-semibold text-gray-600">REF.</th>
                                        <th class="pb-2 pt-2 w-3/12 font-semibold text-gray-600">LOTE</th>
                                        <th class="pb-2 pt-2 pr-2 w-1/12 text-center font-semibold text-gray-600">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody id="det_ci_produtos_list"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mb-6"><label class="form-label">Observação</label><textarea name="observacao" id="det_ci_observacao" rows="3" class="w-full px-3 py-2 border rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Insira observações..."></textarea></div>
                <div class="flex justify-end gap-3 pt-4 border-t"><button type="button" onclick="closeModalDetalhesCI()" class="btn btn-secondary px-6">Cancelar</button><button type="submit" class="btn btn-primary px-6 bg-blue-600 hover:bg-blue-700">SALVAR ALTERAÇÕES</button></div>
            </form>
        </div>
    </div>

    <script src="js/consignado.js?v=1.0"></script>
</body>
</html>