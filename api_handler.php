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
    case 'buscar_cnpj':
        handleBuscarCnpj();
        break;
    default:
        jsonResponse(['error' => 'Ação não encontrada.'], 404);
        break;
}

function handleAddFornecedor() {
    $nome = $_POST['nome_fornecedor'] ?? '';
    $cnpj = $_POST['cnpj_fornecedor'] ?? '';
    $estado = $_POST['estado_fornecedor'] ?? '';
    $nome_fantasia = $_POST['nome_fantasia_fornecedor'] ?? '';
    $porte = $_POST['porte_fornecedor'] ?? '';
    $endereco = $_POST['endereco_fornecedor'] ?? '';
    $bairro = $_POST['bairro_fornecedor'] ?? '';
    $cidade = $_POST['cidade_fornecedor'] ?? '';
    $cep = $_POST['cep_fornecedor'] ?? '';

    $me_epp = in_array($porte, ['ME', 'EPP']) ? 'Sim' : 'Nao';

    if (empty($nome)) {
        jsonResponse(['error' => 'O nome do fornecedor é obrigatório.'], 400);
    }

    try {
        $db = new Database();
        $pdo = $db->connect();

        $stmt = $pdo->prepare("INSERT INTO fornecedores (nome, nome_fantasia, cnpj, estado, me_epp, porte, endereco, bairro, cidade, cep) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $nome_fantasia, $cnpj, $estado, $me_epp, $porte, $endereco, $bairro, $cidade, $cep]);
        $newId = $pdo->lastInsertId();

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

function handleBuscarCnpj() {
    $cnpj = $_GET['cnpj'] ?? '';
    $cnpj = preg_replace('/\D/', '', $cnpj);

    if (strlen($cnpj) !== 14) {
        jsonResponse(['error' => 'CNPJ inválido. Digite 14 dígitos.'], 400);
    }

    $url = 'https://brasilapi.com.br/api/cnpj/v1/' . $cnpj;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'GestaoLicitacao/1.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 500) {
        error_log("buscar_cnpj cURL error: " . $curlError . " | HTTP: " . $httpCode);
        jsonResponse(['error' => 'Não foi possível consultar o CNPJ. Verifique o número ou tente novamente.'], 502);
    }

    if ($httpCode === 404) {
        jsonResponse(['error' => 'CNPJ não encontrado na base da Receita Federal.'], 404);
    }

    $data = json_decode($response, true);

    if (!is_array($data) || isset($data['message']) || isset($data['type'])) {
        jsonResponse(['error' => 'CNPJ não encontrado na base da Receita Federal.'], 404);
    }

    $porte = $data['porte'] ?? '';

    $result = [
        'razao_social' => $data['razao_social'] ?? '',
        'nome_fantasia' => $data['nome_fantasia'] ?? '',
        'porte' => $porte,
        'descricao_porte' => $data['descricao_porte'] ?? '',
        'logradouro' => $data['logradouro'] ?? '',
        'numero' => $data['numero'] ?? '',
        'complemento' => $data['complemento'] ?? '',
        'bairro' => $data['bairro'] ?? '',
        'municipio' => $data['municipio'] ?? '',
        'uf' => $data['uf'] ?? '',
        'cep' => $data['cep'] ?? '',
    ];

    jsonResponse(['success' => true, 'data' => $result]);
}
?>
