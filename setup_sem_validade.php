<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $check = $pdo->query("SHOW COLUMNS FROM licencas_certidoes LIKE 'sem_validade'");
    if ($check->rowCount() > 0) {
        echo "OK - Coluna 'sem_validade' ja existe.<br>";
    } else {
        $pdo->exec("ALTER TABLE licencas_certidoes ADD COLUMN sem_validade TINYINT(1) NOT NULL DEFAULT 0 AFTER notificado_vencido");
        echo "OK - Coluna 'sem_validade' adicionada!<br>";
    }
    echo "Migration concluida. Pode fechar.";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage();
}
