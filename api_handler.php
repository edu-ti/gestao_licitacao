<?php
// ==============================================
// ARQUIVO: api_handler.php
// Processa requisições AJAX do sistema (Fetch API)
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inicia a sessão e carrega dependências
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php'; // O config.php já carrega a função jsonResponse
require_once 'Database.php';
require_once 'functions.php';

// Verificação de autenticação e permissão
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    jsonResponse(['error' => 'Acesso não autorizado.'], 403);
}

$action = $_GET['action'] ?? '';

// Roteador de ações
switch ($action) {
    case 'add_fornecedor':
        handleAddFornecedor();
        break;
    default:
        jsonResponse(['error' => 'Ação não encontrada.'], 404);
        break;
}

function handleAddFornecedor() {
    $nome = $_POST['nome_fornecedor'] ?? '';
    $cnpj = $_POST['cnpj_fornecedor'] ?? '';
    $estado = $_POST['estado_fornecedor'] ?? '';
    // **NOVO: Captura o valor de ME/EPP**
    $me_epp = $_POST['me_epp_fornecedor'] ?? 'Nao';

    if (empty($nome)) {
        jsonResponse(['error' => 'O nome do fornecedor é obrigatório.'], 400);
    }

    try {
        $db = new Database();
        $pdo = $db->connect();

        // **NOVO: Adiciona a coluna me_epp na query de inserção**
        $stmt = $pdo->prepare("INSERT INTO fornecedores (nome, cnpj, estado, me_epp) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $cnpj, $estado, $me_epp]);
        $newId = $pdo->lastInsertId();

        // **NOVO: Retorna o valor de me_epp na resposta**
        $responseData = [
            'id' => $newId,
            'nome' => htmlspecialchars($nome),
            'cnpj' => htmlspecialchars($cnpj),
            'estado' => htmlspecialchars($estado),
            'me_epp' => htmlspecialchars($me_epp)
        ];

        jsonResponse([
            'success' => true,
            'message' => 'Fornecedor adicionado com sucesso!',
            'data' => $responseData
        ]);

    } catch (Exception $e) {
        error_log("API Error (add_fornecedor): " . $e->getMessage());
        jsonResponse(['error' => 'Erro interno do servidor ao adicionar fornecedor.'], 500);
    }
}
?>
