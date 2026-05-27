<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $check = $pdo->query("SHOW COLUMNS FROM licencas_certidoes LIKE 'fornecedor_id'");
    if ($check->rowCount() > 0) {
        echo "OK - Coluna 'fornecedor_id' ja existe.<br>";
    } else {
        $pdo->exec("ALTER TABLE licencas_certidoes ADD COLUMN fornecedor_id INT(11) DEFAULT NULL AFTER id");
        $pdo->exec("ALTER TABLE licencas_certidoes ADD INDEX idx_fornecedor (fornecedor_id)");
        echo "OK - Migration da tabela licencas_certidoes concluida!<br>";
    }
    echo "Voce ja pode fechar esta pagina.";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage();
}
