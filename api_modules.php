<?php
// ==============================================
// ARQUIVO: api_modules.php
// Versão com endpoint para marcar notificações como lidas
// ==============================================

require_once 'config.php';
require_once 'Database.php';

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Acesso não autorizado.'], 401);
    }
}

// Função para o calendário
function handleCalendario($method) {
    requireAuth();
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Método não suportado'], 405);
    }
    try {
        $db = new Database();
        $pdo = $db->connect();
        $stmt = $pdo->query("
            SELECT id, numero_edital, data_sessao, hora_sessao 
            FROM pregoes 
            WHERE data_sessao IS NOT NULL AND hora_sessao IS NOT NULL
        ");
        $eventos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eventos[] = [
                'title' => 'Disputa: ' . $row['numero_edital'],
                'start' => $row['data_sessao'] . 'T' . $row['hora_sessao'],
                'url'   => 'pregao_detalhes.php?id=' . $row['id'],
                'color' => '#3b82f6'
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($eventos);
        exit;
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erro ao buscar eventos.'], 500);
    }
}

// Função para gerir notificações
function handleNotificacoes($method, $action) {
    requireAuth();
    $db = new Database();
    $pdo = $db->connect();
    $user_id = $_SESSION['user_id'];

    try {
        switch ($action) {
            case 'mark_as_read':
                if ($method !== 'POST') jsonResponse(['error' => 'Método não suportado'], 405);
                $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_destino_id = ? AND lida = 0");
                $stmt->execute([$user_id]);
                jsonResponse(['success' => true]);
                break;

            // ==============================================
            // NOVA AÇÃO PARA VERIFICAR NOTIFICAÇÕES
            // ==============================================
            case 'check_new':
                if ($method !== 'GET') jsonResponse(['error' => 'Método não suportado'], 405);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_destino_id = ? AND lida = 0");
                $stmt->execute([$user_id]);
                $count = $stmt->fetchColumn();
                jsonResponse(['unread_count' => $count]);
                break;
            // ==============================================
            // FIM DA NOVA AÇÃO
            // ==============================================

            default:
                jsonResponse(['error' => 'Ação de notificação não suportada'], 400);
                break;
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erro ao processar notificações.'], 500);
    }
}

// Roteamento
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($module) {
    case 'calendario':
        handleCalendario($method);
        break;
    case 'notificacoes':
        handleNotificacoes($method, $action);
        break;
    default:
        if (!empty($module)) {
             jsonResponse(['error' => 'Módulo não encontrado'], 404);
        }
}
?>
