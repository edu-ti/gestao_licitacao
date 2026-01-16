<?php
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $sql = "CREATE TABLE IF NOT EXISTS monitor_cursor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        monitor_name VARCHAR(50) UNIQUE NOT NULL,
        last_checked_id VARCHAR(255),
        last_checked_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Table 'monitor_cursor' created or already exists.\n";

} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
