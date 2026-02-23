<?php
require_once 'config.php';
require_once 'Database.php';
$db = new Database();
$pdo = $db->connect();
$stmt = $pdo->query("SELECT id, nome FROM fornecedores");
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($fornecedores);
