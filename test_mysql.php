<?php
$tests = [
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'u540193243_licitaWeb', 'pass' => 'gest@0licitaWeb'],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'u540193243_licitaWeb', 'pass' => 'gest@0licitaWeb'],
];

foreach ($tests as $t) {
    try {
        $dsn = "mysql:host={$t['host']};port={$t['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $t['user'], $t['pass'], [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ERRMODE_EXCEPTION => PDO::ERRMODE_EXCEPTION
        ]);
        echo "OK: {$t['user']}@{$t['host']}:{$t['port']}\n";
        $stmt = $pdo->query("SHOW DATABASES");
        while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
            echo "  - $row\n";
        }
    } catch (Exception $e) {
        echo "FAIL: {$t['user']}@{$t['host']}:{$t['port']} - " . $e->getMessage() . "\n";
    }
}
