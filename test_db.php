<?php
require_once 'config.php';
require_once 'Database.php';

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->query("DESCRIBE anexos_pregao");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);

// Lets also add the column right away if it doesn't exist to save time
$has_tipo = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'tipo_documento') {
        $has_tipo = true;
    }
}

if (!$has_tipo) {
    echo "\nAdding tipo_documento...\n";
    $pdo->exec("ALTER TABLE anexos_pregao ADD COLUMN tipo_documento VARCHAR(50) DEFAULT 'Anexo'");
    echo "Added successfully.";
} else {
    echo "\ntipo_documento already exists.";
}
