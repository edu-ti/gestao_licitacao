<?php
// ==============================================
// ARQUIVO: auth_api.php
// API DE AUTENTICAÇÃO
// VERSÃO CORRIGIDA E ROBUSTA
// ==============================================

// Garante que os erros sejam logados e não exibidos na tela, para evitar quebras
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Caminhos corretos para os arquivos
require_once 'config.php';
require_once 'Database.php'; // Nome do arquivo com 'D' maiúsculo

// Funções de Autenticação
function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $senha = $input['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        jsonResponse(['error' => 'Email e senha são obrigatórios'], 400);
    }
    
    try {
        $db = new Database();
        $pdo = $db->connect(); 
        
        $stmt = $pdo->prepare("SELECT id, email, nome, senha, perfil FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nome'] = $usuario['nome'];
            $_SESSION['user_perfil'] = $usuario['perfil'];

            logActivity($pdo, $usuario['id'], 'autenticacao', 'LOGIN', null, "Login realizado com sucesso");

            jsonResponse(['success' => true, 'message' => 'Login realizado com sucesso']);
        } else {
            jsonResponse(['error' => 'Email ou senha inválidos'], 401);
        }
        
    } catch (Exception $e) {
        error_log("Erro de login (auth_api): " . $e->getMessage());
        jsonResponse(['error' => 'Erro interno do servidor. Verifique os logs para mais detalhes.'], 500);
    }
}

function handleLogout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logout realizado com sucesso']);
}

function checkSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        jsonResponse(['loggedIn' => true, 'user_id' => $_SESSION['user_id'], 'nome' => $_SESSION['user_nome'], 'perfil' => $_SESSION['user_perfil']]);
    } else {
        jsonResponse(['loggedIn' => false]);
    }
}

// Roteamento
$action = $_GET['action'] ?? '';
switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        checkSession();
        break;
    default:
        jsonResponse(['error' => 'Ação não encontrada'], 404);
}
?>
