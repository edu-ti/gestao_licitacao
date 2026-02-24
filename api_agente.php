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

// Lendo JSON de Entrada ou POST Direto (Se tiver Arquivo de Catálogo)
$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($isMultipart) {
    $anexo_id = $_POST['anexo_id'] ?? null;
    $prompt_usuario = $_POST['prompt'] ?? null;
} else {
    $inputJSON = file_get_contents('php://input');
    $input = [];
    if ($inputJSON) {
        $input = json_decode($inputJSON, TRUE);
    }
    $anexo_id = $input['anexo_id'] ?? null;
    $prompt_usuario = $input['prompt'] ?? null;
}

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

    $parts = [
        [
            "inlineData" => [
                "mimeType" => $mimeType,
                "data" => $base64Data
            ]
        ]
    ];

    // Se houver arquivo de catálogo enviado (Produto PDF/Imagem)
    if ($isMultipart && isset($_FILES['catalogo']) && $_FILES['catalogo']['error'] === UPLOAD_ERR_OK) {
        $catTmp = $_FILES['catalogo']['tmp_name'];
        $catName = $_FILES['catalogo']['name'];

        $catExt = strtolower(pathinfo($catName, PATHINFO_EXTENSION));
        $catMime = 'text/plain';
        if ($catExt === 'pdf') {
            $catMime = 'application/pdf';
        } elseif (in_array($catExt, ['png', 'jpg', 'jpeg'])) {
            $catMime = 'image/' . ($catExt === 'jpg' ? 'jpeg' : $catExt);
        } elseif (in_array($catExt, ['doc', 'docx'])) {
            $catMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        $catData = file_get_contents($catTmp);
        $catBase64 = base64_encode($catData);

        $parts[] = [
            "inlineData" => [
                "mimeType" => $catMime,
                "data" => $catBase64
            ]
        ];
    }

    // Adiciona o texto ao final
    $parts[] = [
        "text" => $instrucao
    ];

    $payload = [
        "contents" => [
            [
                "parts" => $parts
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.2, // Baixa temperatura para manter linguagem formal e precisa (menos "criativo", mais exato)
            "maxOutputTokens" => 8192,
        ]
    ];

    // 4. Executar requisição CURL com sistema de tentativas (Retry)
    $maxTentativas = 3;
    $tentativa = 0;
    $sucesso = false;

    while ($tentativa < $maxTentativas) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        // Timeout longo de 180s pois leitura de PDF enorme pelo Gemini pode levar tempo.
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Falha de conexão com a IA: " . $curlError);
        }

        $geminiData = json_decode($response, true);

        // Se o modelo estiver sobrecarregado (Erro 503)
        if ($httpCode === 503) {
            $tentativa++;
            if ($tentativa < $maxTentativas) {
                sleep(2); // Espera 2 segundos antes de tentar de novo
                continue;
            }
        }

        // Verifica se deu sucesso na API ou outro erro fatal que não seja 503
        if ($httpCode === 200) {
            $sucesso = true;
            break;
        } else {
            // Qualquer outro erro ou se esgotar as tentativas de 503
            $msgErroAPI = $geminiData['error']['message'] ?? 'Erro desconhecido da IA.';
            if ($httpCode === 503) {
                // Mensagem customizada mais amigável que a padrão em inglês
                $msgErroAPI = "O modelo de Inteligência Artificial está com uma demanda altíssima no momento no servidor mundial. O sistema tentou 3 vezes mas não conseguiu vaga. Por favor, tente novamente em alguns minutos.";
            }
            throw new Exception("Erro da IA: " . $msgErroAPI);
        }
    }

    // Extrair o texto da resposta
    $textoResposta = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($textoResposta)) {
        throw new Exception("A Inteligência Artificial retornou uma resposta vazia ou foi bloqueada por filtros de segurança (Safety Ratings).");
    }

    // 6. Salvar no Histórico
    $usuario_id = $_SESSION['user_id'] ?? 0;

    // Reconecta ao banco de dados para evitar "MySQL server has gone away" após o tempo de espera da IA
    $db_reconnect = new Database();
    $pdo_reconnect = $db_reconnect->connect();

    // Garante que a tabela existe (executado rápido se já existir)
    $pdo_reconnect->exec("CREATE TABLE IF NOT EXISTS agente_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        anexo_id INT NOT NULL,
        usuario_id INT NOT NULL,
        prompt_usuario TEXT,
        resposta_ia LONGTEXT,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_anexo (anexo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $stmtHist = $pdo_reconnect->prepare("INSERT INTO agente_historico (anexo_id, usuario_id, prompt_usuario, resposta_ia) VALUES (?, ?, ?, ?)");
    $stmtHist->execute([$anexo_id, $usuario_id, $prompt_usuario, $textoResposta]);

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
