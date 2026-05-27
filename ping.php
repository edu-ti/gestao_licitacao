<?php
session_start();
$_SESSION['last_activity'] = time();
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
