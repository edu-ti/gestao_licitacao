<?php
// ==============================================
// ARQUIVO: dashboard.php
// Versão com Paginação, Modais de Confirmação e AJAX
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'notificacoes.php';

// A função formatarCNPJ agora está em functions.php, que já é carregado pelo auth.php
$status_list = ["Em análise", "Acolhimento de propostas", "Homologado", "Revogado", "Fracassado", "Anulado", "Adjudicado", "Suspenso"];

$mensagem_pregao = '';
$mensagem_fornecedor = '';
$mensagem_token = ''; 


if (isset($_SESSION['mensagem_pregao'])) {
    $mensagem_pregao = $_SESSION['mensagem_pregao'];
    unset($_SESSION['mensagem_pregao']);
}
if (isset($_SESSION['mensagem_fornecedor'])) {
    $mensagem_fornecedor = $_SESSION['mensagem_fornecedor'];
    unset($_SESSION['mensagem_fornecedor']);
}
if (isset($_SESSION['mensagem_token'])) {
    $mensagem_token = $_SESSION['mensagem_token'];
    unset($_SESSION['mensagem_token']);
}

$current_user_id = $_SESSION['user_id'];
$erro_fatal = '';

try {
    $db = new Database();
    $pdo = $db->connect();

    // --- Lógica de POST (APENAS PARA ADMINS E AÇÕES QUE RECARREGAM A PÁGINA) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isAdmin()) {
        
        // Adicionar Pregão
        if (isset($_POST['submit_pregao'])) {
            $status_pregao = !empty($_POST['status']) ? $_POST['status'] : 'Em análise';
            $sql = "INSERT INTO pregoes (numero_edital, numero_processo, modalidade, orgao_comprador, local_disputa, uasg, objeto, data_publicacao, data_abertura, data_sessao, hora_sessao, status, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['numero_edital'], $_POST['numero_processo'], $_POST['modalidade'], $_POST['orgao_comprador'], $_POST['local_disputa'], $_POST['uasg'], $_POST['objeto'], null, !empty($_POST['data_sessao']) ? $_POST['data_sessao'] : null, !empty($_POST['hora_sessao']) ? $_POST['hora_sessao'] : null, $status_pregao, $current_user_id]);
            $_SESSION['mensagem_pregao'] = "Pregão cadastrado com sucesso!";
            $novo_pregao_id = $pdo->lastInsertId();
            
            logActivity($pdo, $current_user_id, 'pregoes', 'CADASTRO', $novo_pregao_id, "Pregão " . htmlspecialchars($_POST['numero_edital']) . " foi cadastrado.");
            
            error_log("Dashboard: Tentando chamar criarNotificacao para o novo pregão ID: " . $novo_pregao_id);
            criarNotificacao($pdo, $novo_pregao_id, "Novo Pregão: " . $_POST['numero_edital'], "Novo pregão cadastrado: {$_POST['numero_edital']}.");
            error_log("Dashboard: Chamada para criarNotificacao concluída.");
        }
        
        // Editar Pregão
        if (isset($_POST['submit_edit_pregao'])) {
            $status_pregao = !empty($_POST['status']) ? $_POST['status'] : 'Em análise';
            $sql = "UPDATE pregoes SET numero_edital = ?, numero_processo = ?, modalidade = ?, orgao_comprador = ?, local_disputa = ?, uasg = ?, objeto = ?, data_abertura = ?, data_sessao = ?, hora_sessao = ?, status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['numero_edital'], $_POST['numero_processo'], $_POST['modalidade'], $_POST['orgao_comprador'], $_POST['local_disputa'], $_POST['uasg'], $_POST['objeto'], null, !empty($_POST['data_sessao']) ? $_POST['data_sessao'] : null, !empty($_POST['hora_sessao']) ? $_POST['hora_sessao'] : null, $status_pregao, $_POST['edit_pregao_id']]);
            $_SESSION['mensagem_pregao'] = "Pregão atualizado com sucesso!";
            
            error_log("Dashboard: Tentando chamar criarNotificacao para o pregão editado ID: " . $_POST['edit_pregao_id']);
            criarNotificacao($pdo, $_POST['edit_pregao_id'], "Pregão Atualizado: " . $_POST['numero_edital'], "O pregão {$_POST['numero_edital']} foi atualizado.");
            error_log("Dashboard: Chamada para criarNotificacao concluída.");
        }

        // Excluir Pregão
        if (isset($_POST['excluir_id_pregao'])) {
            $pdo->prepare("DELETE FROM pregoes WHERE id = ?")->execute([intval($_POST['excluir_id_pregao'])]);
            $_SESSION['mensagem_pregao'] = "Pregão excluído com sucesso!";
        }
        
        // Excluir Fornecedor
        if (isset($_POST['excluir_id_fornecedor'])) {
            $pdo->prepare("DELETE FROM fornecedores WHERE id = ?")->execute([intval($_POST['excluir_id_fornecedor'])]);
            $_SESSION['mensagem_fornecedor'] = "Fornecedor excluído com sucesso!";
        }
      
        if (isset($_POST['submit_generate_token'])) {
            $novo_token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO registration_tokens (token) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $_SESSION['mensagem_token'] = $stmt->execute([$novo_token]) ? "Novo token gerado com sucesso!" : "Erro ao gerar o token.";
        }

        header("Location: dashboard.php");
        exit();
    }
    
    $filtro_status = $_GET['filtro_status'] ?? '';
    $filtro_orgao = $_GET['filtro_orgao'] ?? '';
    $filtro_data_inicio = $_GET['filtro_data_inicio'] ?? '';
    $filtro_data_fim = $_GET['filtro_data_fim'] ?? '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $where_clauses = [];
    $params = [];
    $count_params = [];
    if (!empty($filtro_status)) { $where_clauses[] = "status = :status"; $params[':status'] = $filtro_status; }
    if (!empty($filtro_orgao)) { $where_clauses[] = "orgao_comprador LIKE :orgao"; $params[':orgao'] = '%' . $filtro_orgao . '%'; }
    if (!empty($filtro_data_inicio)) { $where_clauses[] = "data_sessao >= :data_inicio"; $params[':data_inicio'] = $filtro_data_inicio; }
    if (!empty($filtro_data_fim)) { $where_clauses[] = "data_sessao <= :data_fim"; $params[':data_fim'] = $filtro_data_fim; }
    $count_params = $params;
    $where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM pregoes" . $where_sql);
    $total_stmt->execute($count_params);
    $total_pregoes = $total_stmt->fetchColumn();
    $total_pages = ceil($total_pregoes / $limit);
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    $sql_pregoes = "SELECT * FROM pregoes" . $where_sql . " ORDER BY data_publicacao DESC LIMIT :limit OFFSET :offset";
    $stmt_pregoes = $pdo->prepare($sql_pregoes);
    foreach ($params as $key => &$val) {
        $stmt_pregoes->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt_pregoes->execute();
    $pregoes = $stmt_pregoes->fetchAll(PDO::FETCH_ASSOC);
    $active_tokens = [];
    if (isAdmin()) {
        $sql_tokens = "SELECT token, created_at FROM registration_tokens WHERE is_used = 0 AND created_at >= NOW() - INTERVAL 30 MINUTE ORDER BY created_at DESC";
        $active_tokens = $pdo->query($sql_tokens)->fetchAll(PDO::FETCH_ASSOC);
    }
    $total_pregoes_geral = $pdo->query("SELECT COUNT(*) FROM pregoes")->fetchColumn();
    $valor_total_disputa = $pdo->query("SELECT SUM(quantidade * valor_unitario) FROM itens_pregoes")->fetchColumn();
    $total_fornecedores = $pdo->query("SELECT COUNT(*) FROM fornecedores")->fetchColumn();
    $recente_stmt = $pdo->query("SELECT numero_edital FROM pregoes ORDER BY data_publicacao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $pregao_mais_recente = $recente_stmt ? $recente_stmt['numero_edital'] : 'N/D';
    $status_counts = $pdo->query("SELECT status, COUNT(*) as total FROM pregoes GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erro_fatal = "Ocorreu um erro crítico na base de dados. Detalhes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Gestão</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css?v=2.29">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .fc .fc-button-primary { background-color: #3b82f6; border-color: #3b82f6; }
        .fc .fc-daygrid-day.fc-day-today { background-color: rgba(59, 130, 246, 0.1); }
        .pagination-link { padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; color: #3b82f6; }
        .pagination-link.active { background-color: #3b82f6; color: white; border-color: #3b82f6; }
        .token-timer { font-weight: 600; color: #16a34a; }
    </style>
</head>
<body class="bg-[#d9e3ec] p-8">
    <div class="container mx-auto">
        <?php if (!empty($erro_fatal)): ?>
            <div class="bg-white p-8 rounded-lg shadow-lg">
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                    <p><?php echo htmlspecialchars($erro_fatal); ?></p>
                </div>
            </div>
        <?php else: ?>
            <?php 
                $page_title = 'Painel de Gestão';
                include 'header.php'; 
            ?>
            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-lg"><h4 class="text-gray-500 text-sm font-medium">Total de Pregões</h4><p class="text-3xl font-bold text-gray-800"><?php echo $total_pregoes_geral; ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-lg"><h4 class="text-gray-500 text-sm font-medium">Valor Total em Disputa</h4><p class="text-3xl font-bold text-gray-800">R$ <?php echo number_format($valor_total_disputa ?? 0, 2, ',', '.'); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-lg"><h4 class="text-gray-500 text-sm font-medium">Fornecedores Ativos</h4><p class="text-3xl font-bold text-gray-800"><?php echo $total_fornecedores; ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-lg"><h4 class="text-gray-500 text-sm font-medium">Pregão Mais Recente</h4><p class="text-xl font-bold text-gray-800 truncate"><?php echo htmlspecialchars($pregao_mais_recente); ?></p></div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                <h3 class="text-xl font-bold text-gray-700 mb-4">Resumo por Status</h3>
                <div class="max-w-md mx-auto">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
               <!-- Filtro -->
                    <div class="bg-gray-50 p-4 rounded-lg border mb-6">
                        <form method="GET" action="dashboard.php">
                            <h3 class="font-semibold text-gray-700 mb-2">Filtrar Pregões</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                                <div class="lg:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700">Órgão Comprador</label>
                                    <input type="text" name="filtro_orgao" class="mt-1 w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($filtro_orgao); ?>" placeholder="Nome do órgão">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select name="filtro_status" class="mt-1 w-full px-3 py-2 border rounded-lg">
                                        <option value="">Todos</option>
                                        <?php foreach ($status_list as $status_item): ?>
                                            <option value="<?php echo htmlspecialchars($status_item); ?>" <?php echo ($filtro_status === $status_item) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_item); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">De</label>
                                    <input type="date" name="filtro_data_inicio" class="mt-1 w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Até</label>
                                    <input type="date" name="filtro_data_fim" class="mt-1 w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                                </div>
                                <div class="flex space-x-2">
                                    <button type="submit" class="btn btn-primary w-full">Filtrar</button>
                                    <a href="dashboard.php" class="btn btn-secondary w-full">Limpar</a>
                                </div>
                            </div>
                        </form>
                    </div>

            <!-- Abas -->
            <div class="bg-white p-4 sm:p-8 rounded-lg shadow-lg">
                <div class="tab-container mb-6">
                        <button class="tab-btn active" data-tab="pregoes">Gestão de Pregões</button>
                        <button class="tab-btn" data-tab="fornecedores">Gestão de Fornecedores</button>
                        <?php if (isAdmin()): ?>
                        <button class="tab-btn" data-tab="acessos">Gerar Token</button>
                        <?php endif; ?>
                        <button class="tab-btn" data-tab="calendario">Calendário</button>
                        <a href="relatorios.php" class="tab-btn">Relatórios</a>
                        <a href="consignado.php" class="tab-btn">Consignado</a>
                </div>

                <!-- Aba Pregões -->
                 <div id="tab-pregoes" class="tab-content active">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                        <h2 class="text-2xl font-bold text-gray-700">Lista de Pregões</h2>
                        <?php if (isAdmin()): ?>
                        <button id="open-modal-pregao-btn" class="btn btn-primary w-full sm:w-auto"><span>Novo Pregão</span></button>
                        <?php endif; ?>
                    </div>
                 
                    <?php if (!empty($mensagem_pregao)): ?><div class="bg-green-100 text-green-700 p-4 mb-4 rounded-md"><?php echo $mensagem_pregao; ?></div><?php endif; ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md border">
                        <table class="min-w-full leading-normal">
                            <thead class="bg-gray-50"><tr class="bg-[#d9e3ec]"><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Edital</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Órgão</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data da Disputa</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th><th class="px-5 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th></tr></thead>
                            <tbody>
                                <?php foreach ($pregoes as $row): ?>
                                    <tr>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold"><?php echo htmlspecialchars($row['numero_edital']); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($row['orgao_comprador']); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo !empty($row['data_sessao']) ? date("d/m/Y", strtotime($row['data_sessao'])) : 'N/D'; ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo !empty($row['status']) ? htmlspecialchars($row['status']) : 'N/D'; ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="pregao_detalhes.php?id=<?php echo $row['id']; ?>" class="btn btn-detalhe btn-sm">Detalhes</a>
                                                <?php if (isAdmin()): ?>
                                                    <button class="btn btn-secondary btn-sm edit-pregao-btn " data-id="<?php echo $row['id']; ?>" 
                                                    data-numero_edital="<?php echo htmlspecialchars($row['numero_edital']); ?>" 
                                                    data-numero_processo="<?php echo htmlspecialchars($row['numero_processo']); ?>" 
                                                    data-modalidade="<?php echo htmlspecialchars($row['modalidade']); ?>" 
                                                    data-orgao_comprador="<?php echo htmlspecialchars($row['orgao_comprador']); ?>" 
                                                    data-local_disputa="<?php echo htmlspecialchars($row['local_disputa']); ?>" 
                                                    data-uasg="<?php echo htmlspecialchars($row['uasg']); ?>" 
                                                    data-objeto="<?php echo htmlspecialchars($row['objeto']); ?>" 
                                                    data-data_sessao="<?php echo htmlspecialchars($row['data_sessao'] ?? ''); ?>" 
                                                    data-hora_sessao="<?php echo htmlspecialchars($row['hora_sessao'] ?? ''); ?>" 
                                                    data-status="<?php echo htmlspecialchars($row['status']); ?>">Editar</button>
                                                    <form id="delete-pregao-form-<?php echo $row['id']; ?>" method="POST" class="inline-block"><input type="hidden" name="excluir_id_pregao" value="<?php echo $row['id']; ?>"></form>
                                                    <button type="button" class="btn btn-danger btn-sm js-confirm-delete" data-form-id="delete-pregao-form-<?php echo $row['id']; ?>" data-message="Tem certeza que deseja excluir o pregão <?php echo htmlspecialchars($row['numero_edital']); ?>?">Excluir</button>
                                                <?php endif; ?>
                                            </div>    
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pregoes)): ?>
                                    <tr><td colspan="5" class="text-center p-5 text-gray-500">Nenhum pregão encontrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Paginação -->
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center space-x-1">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET, '', '&'); ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>

                <!-- Aba Fornecedores -->
                <div id="tab-fornecedores" class="tab-content">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-gray-700">Lista de Fornecedores</h2>
                        <?php if (isAdmin()): ?>
                        <button id="open-modal-fornecedor-btn" class="btn btn-primary">+ Novo Fornecedor</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($mensagem_fornecedor)): ?><div class="bg-green-100 text-green-700 p-4 mb-4 rounded-md"><?php echo $mensagem_fornecedor; ?></div><?php endif; ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md border">
                        <table class="min-w-full leading-normal">
                            <thead class="bg-[#d9e3ec]"><tr><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">CNPJ</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ME/EPP</th><th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Estado</th><th class="px-5 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th></tr></thead>
                            <tbody id="fornecedores-table-body">
                                <?php foreach ($fornecedores as $row): ?>
                                    <tr id="fornecedor-row-<?php echo $row['id']; ?>">
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($row['nome']); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars(formatarCNPJ($row['cnpj'])); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($row['me_epp'] ?? 'Nao'); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($row['estado']); ?></td>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                        <?php if (isAdmin()): ?>
                                            <form id="delete-fornecedor-form-<?php echo $row['id']; ?>" method="POST"><input type="hidden" name="excluir_id_fornecedor" value="<?php echo $row['id']; ?>"></form>    
                                            <button type="button" class="btn btn-danger btn-sm js-confirm-delete" data-form-id="delete-fornecedor-form-<?php echo $row['id']; ?>" data-message="Tem certeza que deseja excluir o fornecedor <?php echo htmlspecialchars($row['nome']); ?>?">Excluir</button>
                                        <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Aba Acessos -->
                <?php if (isAdmin()): ?>
                <div id="tab-acessos" class="tab-content">
                    <h2 class="text-2xl font-bold text-gray-700 mb-4">Gerar Token para Novo Registo</h2>
                    <?php if (!empty($mensagem_token)): ?><div class="bg-green-100 text-green-800 p-4 mb-4 rounded-md"><?php echo $mensagem_token; ?></div><?php endif; ?>
                    
                    <div class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <p class="text-gray-600 mb-4">Clique no botão para gerar um novo código de validação. Válido por 30 minutos.</p>
                        <form method="POST"><button type="submit" name="submit_generate_token" class="btn btn-primary">Gerar Novo Token</button></form>
                    </div>

                    <h3 class="text-xl font-bold text-gray-700 mt-8 mb-4">Tokens Ativos</h3>
                    <div id="active-tokens-container" class="space-y-3">
                        <?php if (empty($active_tokens)): ?>
                            <p class="text-gray-500">Nenhum token ativo no momento.</p>
                        <?php else: ?>
                            <?php foreach ($active_tokens as $token): ?>
                                <div class="bg-white p-4 rounded-lg shadow border flex justify-between items-center">
                                    <span class="font-mono text-lg text-gray-800"><?php echo htmlspecialchars($token['token']); ?></span>
                                    <span class="token-timer text-sm" data-created-at="<?php echo htmlspecialchars($token['created_at']); ?>"></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Aba Calendário -->
                <div id="tab-calendario" class="tab-content"><div id='calendario'></div></div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modais -->
    <div id="modal-pregao" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden overflow-y-auto"><div class="bg-[#f7f6f6] p-8 rounded-lg shadow-xl w-full max-w-2xl my-8"><div class="flex justify-between items-center"><h2 id="modal-pregao-title" class="text-2xl font-bold mb-6">Cadastrar Novo Pregão</h2><button class="close-modal-btn text-gray-500 text-3xl mb-6">&times;</button></div><form id="form-pregao" method="post"><input type="hidden" id="edit_pregao_id" name="edit_pregao_id"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label>Número do Edital</label><input type="text" name="numero_edital" class="w-full px-3 py-2 border rounded-lg" required></div><div><label>Número do Processo</label><input type="text" name="numero_processo" class="w-full px-3 py-2 border rounded-lg"></div><div><label>Modalidade</label><select name="modalidade" class="w-full px-3 py-2 border rounded-lg" required>
        
                        <option value="Pregão Eletrônico">Pregão Eletrônico</option>
                        <option value="Compra Direta">Compra Direta</option>
                        <option value="Dispensa de Licitação">Dispensa de Licitação</option>
                        <option value="Pregão Presencial">Pregão Presencial</option>
                        <option value="Concorrência">Concorrência</option>
                        <option value="Tomada de Preços">Tomada de Preços</option>
                        <option value="Convite">Convite</option>
                        <option value="Outra">Outra</option></select></div>
        
        <div><label>Órgão Comprador</label><input type="text" name="orgao_comprador" class="w-full px-3 py-2 border rounded-lg" required></div><div><label>Local da Disputa</label><input type="text" name="local_disputa" class="w-full px-3 py-2 border rounded-lg"></div><div><label>UASG</label><input type="text" name="uasg" class="w-full px-3 py-2 border rounded-lg"></div></div><div class="mt-4"><label>Objeto</label><textarea name="objeto" rows="3" class="w-full px-3 py-2 border rounded-lg" required></textarea></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"><div><label>Data de Abertura/Disputa</label><input type="date" name="data_sessao" class="w-full px-3 py-2 border rounded-lg"></div><div><label>Hora da Disputa</label><input type="time" name="hora_sessao" class="w-full px-3 py-2 border rounded-lg"></div></div><div class="mt-4"><label>Status</label><select name="status" class="w-full px-3 py-2 border rounded-lg"required>
            <?php foreach($status_list as $status_item): ?>
                <option value="<?php echo htmlspecialchars($status_item); ?>"><?php echo htmlspecialchars($status_item); ?></option>
            <?php endforeach; ?>
        </select></div><div class="flex justify-end mt-6"><button id="submit-pregao-btn" type="submit" name="submit_pregao" class="btn btn-primary">Cadastrar</button></div></form></div></div>
    
    <!-- Modal Fornecedor (AJAX) -->
    <div id="modal-fornecedor" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden"><div class="bg-[#f7f6f6] p-8 rounded-lg shadow-xl w-full max-w-lg relative"><button class="close-modal-btn absolute top-4 right-4 text-gray-500 text-3xl">&times;</button><h2 class="text-2xl font-bold mb-6">Cadastrar Novo Fornecedor</h2><form id="form-fornecedor"><div id="fornecedor-form-message" class="mb-4 "></div><div class="mb-4"><label>Nome</label><input type="text" name="nome_fornecedor" class="w-full px-3 py-2 border rounded-lg" required></div><div class="mb-4"><label>CNPJ</label><input type="text" id="cnpj_fornecedor_input" name="cnpj_fornecedor" class="w-full px-3 py-2 border rounded-lg" placeholder="00.000.000/0000-00"></div><div class="mb-4"><label>Estado</label><select name="estado_fornecedor" class="w-full px-3 py-2 border rounded-lg"><option value="">Selecione</option>
    <option value="AC">Acre</option>
                <option value="AL">Alagoas</option><option value="AP">Amapá</option>
                <option value="AM">Amazonas</option><option value="BA">Bahia</option>
                <option value="CE">Ceará</option><option value="DF">Distrito Federal</option>
                <option value="ES">Espírito Santo</option><option value="GO">Goiás</option>
                <option value="MA">Maranhão</option><option value="MT">Mato Grosso</option>
                <option value="MS">Mato Grosso do Sul</option><option value="MG">Minas Gerais</option>
                <option value="PA">Pará</option><option value="PB">Paraíba</option>
                <option value="PR">Paraná</option><option value="PE">Pernambuco</option>
                <option value="PI">Piauí</option><option value="RJ">Rio de Janeiro</option>
                <option value="RN">Rio Grande do Norte</option><option value="RS">Rio Grande do Sul</option>
                <option value="RO">Rondônia</option><option value="RR">Roraima</option>
                <option value="SC">Santa Catarina</option><option value="SP">São Paulo</option>
                <option value="SE">Sergipe</option><option value="TO">Tocantins</option>
    </select></div>
    <div class="mb-6">
        <label>Empresa ME/EPP?</label>
        <select name="me_epp_fornecedor" class="w-full px-3 py-2 border rounded-lg">
            <option value="Nao">Não</option>
            <option value="Sim">Sim</option>
        </select>
    </div>
    <div class="flex justify-end"><button type="submit" class="btn btn-primary">Cadastrar</button></div></form></div></div>

    <!-- Modal de Confirmação Genérico -->
    <div id="modal-confirm" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden"><div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md"><h3 class="text-xl font-bold mb-4">Confirmar Ação</h3><p id="modal-confirm-message" class="text-gray-600 mb-6"></p><div class="flex justify-end gap-4"><button id="modal-confirm-cancel" class="btn btn-secondary">Cancelar</button><button id="modal-confirm-ok" class="btn btn-danger">Confirmar</button></div></div></div>

    <!-- Container para Notificações Toast -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-2"></div>

    <script src="js/script.js?v=2.29"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicialização do Calendário
            const calendarEl = document.getElementById('calendario');
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth', 
                    locale: 'pt-br',
                    buttonText: {
                        today: 'Hoje',
                        month: 'Mês',
                        week:  'Semana',
                        list:  'Lista'
                    },
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                    events: 'api_modules.php?module=calendario',
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        if (info.event.url) { window.open(info.event.url, "_blank"); }
                    }
                });
                
                const calendarTab = document.querySelector('button[data-tab="calendario"]');
                if (calendarTab) {
                    let isCalendarRendered = false;
                    calendarTab.addEventListener('click', () => {
                        if (!isCalendarRendered) {
                            setTimeout(() => { calendar.render(); isCalendarRendered = true; }, 10);
                        }
                    });
                }
            }

            // GRÁFICO DE STATUS (CHART.JS)
            const statusData = <?php echo json_encode($status_counts ?? []); ?>;
            const statusLabels = Object.keys(statusData);
            const statusValues = Object.values(statusData);
            
            const chartCtx = document.getElementById('statusChart');
            if (chartCtx && statusLabels.length > 0) {
                new Chart(chartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            label: 'Nº de Pregões',
                            data: statusValues,
                            backgroundColor: ['rgba(59, 130, 246, 0.8)','rgba(239, 68, 68, 0.8)','rgba(16, 185, 129, 0.8)','rgba(245, 158, 11, 0.8)','rgba(107, 114, 128, 0.8)','rgba(139, 92, 246, 0.8)'],
                            borderColor: ['rgba(255, 255, 255, 1)'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'top' } }
                    }
                });
            }

            // LÓGICA DO TEMPORIZADOR DE TOKEN
            const tokenTimers = document.querySelectorAll('.token-timer');
            if (tokenTimers.length > 0) {
                const updateTimers = () => {
                    tokenTimers.forEach(timer => {
                        const createdAt = new Date(timer.dataset.createdAt.replace(' ', 'T') + 'Z'); // Assume UTC
                        const now = new Date();
                        const diff = now - createdAt;
                        const remainingSeconds = (30 * 60) - Math.floor(diff / 1000);

                        if (remainingSeconds > 0) {
                            const minutes = Math.floor(remainingSeconds / 60);
                            const seconds = remainingSeconds % 60;
                            timer.textContent = `Expira em ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                        } else {
                            timer.textContent = 'Expirado';
                            timer.classList.remove('text-green-600');
                            timer.classList.add('text-red-500');
                            timer.closest('.flex').style.opacity = '0.5';
                        }
                    });
                };
                setInterval(updateTimers, 1000);
                updateTimers(); 
            }
        });
    </script>
</body>
</html>