<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Verifica se a coluna ja existe
    $check = $pdo->query("SHOW COLUMNS FROM pregoes LIKE 'lembrete_enviado'");
    if ($check->rowCount() > 0) {
        echo "OK - Coluna 'lembrete_enviado' ja existe na tabela pregoes.<br>";
        exit;
    }

    // Adiciona a coluna
    $pdo->exec("ALTER TABLE pregoes ADD COLUMN lembrete_enviado TINYINT(1) NOT NULL DEFAULT 0");
    echo "OK - Coluna 'lembrete_enviado' adicionada com sucesso!<br>";
    echo "Migration concluida. Voce ja pode fechar esta pagina.";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
