<?php
// ==============================================
// api_agente.php
// Endpoint que conecta o backend PHP à API do Google Gemini
// ==============================================
require_once 'auth.php'; // Usa auth normal pois o agente exige login padrão (pode trocar se usar tokens)
require_once 'Database.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
    exit;
}

// Verifica chave de API no config.php
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    echo json_encode(['status' => 'error', 'message' => 'A chave de API do Gemini não foi configurada no sistema.']);
    exit;
}

// Lendo JSON de Entrada
$inputJSON = file_get_contents('php://input');
$input = json_encode(array());
if ($inputJSON) {
    $input = json_decode($inputJSON, TRUE);
}

$anexo_id = $input['anexo_id'] ?? null;
$prompt_usuario = $input['prompt'] ?? null;

if (!$anexo_id || !$prompt_usuario) {
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros incompletos (anexo_id ou prompt ausentes).']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->connect();

    // 1. Buscar Anexo no BD
    $stmt = $pdo->prepare("SELECT * FROM anexos_pregao WHERE id = ?");
    $stmt->execute([$anexo_id]);
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        throw new Exception("Documento não encontrado no sistema.");
    }

    $filePath = UPLOAD_DIR . $anexo['nome_arquivo'];
    if (!file_exists($filePath)) {
        throw new Exception("O arquivo físico do edital não existe na pasta do servidor.");
    }

    // 2. Determinar MIME Type e Base64
    $extensao = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeType = 'text/plain';

    if ($extensao === 'pdf') {
        $mimeType = 'application/pdf';
    } elseif (in_array($extensao, ['doc', 'docx'])) {
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        // Warning: Gemini has better support for true plain text or PDF. DOCX might require specific model handles or text extraction in PHP first, but Gemini 1.5 supports basic generic docs.
    } elseif (in_array($extensao, ['txt', 'csv'])) {
        $mimeType = 'text/plain';
    } else {
        throw new Exception("Formato de arquivo não suportado pela Inteligência Artificial. Apenas PDF ou Textos.");
    }

    $fileData = file_get_contents($filePath);
    $base64Data = base64_encode($fileData);

    // 3. Montar Payload para o Gemini 2.5 Flash 
    // URL: https://ai.google.dev/api/rest/v1beta/models/generateContent
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

    $instrucao = "Você é um Analista de Licitações Públicas sênior e Advogado especialista no Brasil. Seu objetivo é ajudar o usuário com editais, licitações, pregoes eletrônicos e compras governamentais. Leia o anexo fornecido com extrema atenção aos detalhes administrativos e técnicos.\n\nPEDIDO DO USUÁRIO:\n" . $prompt_usuario;

    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "inlineData" => [
                            "mimeType" => $mimeType,
                            "data" => $base64Data
                        ]
                    ],
                    [
                        "text" => $instrucao
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.2, // Baixa temperatura para manter linguagem formal e precisa (menos "criativo", mais exato)
            "maxOutputTokens" => 8192,
        ]
    ];

    // 4. Executar requisição CURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    // Timeout longo de 60s pois leitura de PDF enorme pelo Gemini pode levar 10-30s.
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Falha de conexão com a IA: " . $curlError);
    }

    $geminiData = json_decode($response, true);

    // 5. Tratar retorno do Gemini
    if ($httpCode !== 200) {
        // Erro retornado pela API do Google
        $msgErroAPI = $geminiData['error']['message'] ?? 'Erro desconhecido da IA.';
        throw new Exception("Erro da IA: " . $msgErroAPI);
    }

    // Extrair o texto da resposta
    $textoResposta = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($textoResposta)) {
        throw new Exception("A Inteligência Artificial retornou uma resposta vazia ou foi bloqueada por filtros de segurança (Safety Ratings).");
    }

    echo json_encode([
        'status' => 'success',
        'resposta' => $textoResposta
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
