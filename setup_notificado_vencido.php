<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $check = $pdo->query("SHOW COLUMNS FROM licencas_certidoes LIKE 'notificado_vencido'");
    if ($check->rowCount() > 0) {
        echo "OK - Coluna 'notificado_vencido' ja existe.<br>";
    } else {
        $pdo->exec("ALTER TABLE licencas_certidoes ADD COLUMN notificado_vencido TINYINT(1) NOT NULL DEFAULT 0 AFTER notificado");
        echo "OK - Coluna 'notificado_vencido' adicionada!<br>";
    }
    echo "Migration concluida. Pode fechar.";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage();
}
