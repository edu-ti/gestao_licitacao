<?php
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Tabela para armazenar histórico de mensagens já vistas (evita duplicatas e reenvio de alertas)
    // O hash é geralmente um MD5 do conteúdo da mensagem
    $sql = "CREATE TABLE IF NOT EXISTS monitor_seen_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        monitor_name VARCHAR(50) NOT NULL,
        message_hash VARCHAR(64) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_monitor_hash (monitor_name, message_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Table 'monitor_seen_messages' created or already exists.\n";

} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>