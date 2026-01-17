<?php
// ==============================================
// CONFIGURAÇÃO DO BANCO DE DADOS E FUNÇÕES GLOBAIS
// config.php
// ==============================================
// Define o fuso horário para o horário de Brasília para garantir que todas as datas e horas sejam exibidas corretamente.
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados
// Substitua as credenciais pelas do seu projeto "licitaweb"
define('DB_HOST', '127.0.0.1'); 
define('DB_NAME', 'u540193243_licitaweb_db'); 
define('DB_USER', 'u540193243_licitaWeb'); 
define('DB_PASS', 'gest@0licitaWeb'); 
define('DB_CHARSET', 'utf8mb4');
define( 'DB_COLLATE', '' );

// ==============================================
// NOVAS CONFIGURAÇÕES DE E-MAIL (SMTP)
// ATENÇÃO: Preencha com as credenciais do seu serviço de e-mail.
// ==============================================
define('SMTP_HOST', 'smtp.hostinger.com');    // Ex: smtp.hostinger.com, smtp.gmail.com
define('SMTP_USER', 'contato@frpe.app.br'); // Ex: contato@frpe.app.br
define('SMTP_PASS', 'g3st@0Frpe');      // A senha do seu e-mail
define('SMTP_PORT', 465);                      // Porta do servidor SMTP (465 para SSL, 587 para TLS)

// ==============================================
// CONFIGURAÇÕES DO MONITOR DE MENSAGENS
// ==============================================
define('MONITOR_KEYWORDS', [
    'convocação',
    'convoca',
    'anexo',
    'recurso',
    'contraproposta',
    'negociação',
    'sessão retomada',
    'avisos',
    'esclarecimento'
]);

// Definição de constantes e outras configurações
define('APP_NAME', 'Sistema de Gestão de Pregões');
define('BASE_URL', 'http://frpe.app.br'); 

// Caminho para o diretório de uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Timeout da sessão em segundos (ex: 3600 = 1 hora)
define('SESSION_TIMEOUT', 3600);

// Níveis de permissão
define('PERM_ADMIN', 'Admin');
define('PERM_PADRAO', 'Padrao');

// Adicionando a função hashPassword que estava faltando
/**
 * Gera um hash de senha seguro usando o algoritmo PASSWORD_DEFAULT.
 * @param string $senha A senha em texto simples a ser hasheada.
 * @return string O hash da senha.
 */
function hashPassword($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

// Função para verificar se uma senha em texto simples corresponde a um hash
/**
 * Verifica se uma senha corresponde a um hash.
 * @param string $senha A senha em texto simples.
 * @param string $hash O hash a ser verificado.
 * @return bool Retorna TRUE se a senha corresponder, FALSE caso contrário.
 */
function verifyPassword($senha, $hash) {
    return password_verify($senha, $hash);
}

// Função para tratar erros de upload
function handleUploadError($error_code) {
    $php_errors = [
        UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite de upload definido no php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite de upload definido no formulário HTML.',
        UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi feito parcialmente.',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Faltando uma pasta temporária.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo em disco.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload do arquivo.'
    ];
    $error_message = $php_errors[$error_code] ?? 'Erro desconhecido no upload.';
    error_log("Falha no upload de arquivo: " . $error_message . " (Código: " . $error_code . ")");
    throw new Exception('Falha ao fazer upload do arquivo: ' . $error_message);
}

// Função para criar diretório de uploads se não existir
function createDirectoryIfNotExists($dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Falha ao criar o diretório de uploads: " . $dir);
        }
    }
}
createDirectoryIfNotExists(UPLOAD_DIR);

// Função global para logar atividades
function logActivity($pdo, $usuario_id, $tabela, $acao, $registro_id, $detalhes) {
    // Garante que a conexão com o banco de dados é válida
    if (!$pdo) {
        error_log("Erro de log: conexão PDO não é válida.");
        return;
    }
    
    // Insere o log na tabela `logs_atividades` usando uma prepared statement para segurança
    $sql = "INSERT INTO logs_atividades (usuario_id, tabela, acao, registro_id, detalhes) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    // Tenta executar a query
    try {
        $stmt->execute([$usuario_id, $tabela, $acao, $registro_id, $detalhes]);
    } catch (PDOException $e) {
        // Loga o erro, mas não interrompe a execução do script
        error_log("Erro ao inserir log de atividade: " . $e->getMessage());
    }
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerar ID da sessão para segurança
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Verificar timeout da sessão
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();    // Limpa todas as variáveis de sessão
    session_destroy();  // Destrói a sessão
    session_start();    // Inicia uma nova sessão vazia
}
$_SESSION['last_activity'] = time(); // Atualiza o timestamp da última atividade

// Funções para resposta JSON (mantidas aqui para facilitar o uso nas APIs)
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}
