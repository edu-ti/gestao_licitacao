<?php
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $sql = "CREATE TABLE IF NOT EXISTS agente_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        anexo_id INT NOT NULL,
        usuario_id INT NOT NULL,
        prompt_usuario TEXT,
        resposta_ia LONGTEXT,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_anexo (anexo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Tabela 'agente_historico' verificada/criada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
