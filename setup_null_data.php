<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $pdo->exec("ALTER TABLE licencas_certidoes MODIFY data_vencimento DATE NULL DEFAULT NULL");
    echo "OK - Coluna 'data_vencimento' agora aceita NULL.<br>";
    echo "Migration concluida. Pode fechar.";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage();
}
