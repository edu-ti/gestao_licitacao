<?php
require_once 'auth.php';
require_once 'config.php';

// Apenas usuários logados
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {

    // 1. Processar Alertas
    $alerts = [
        'empresa' => isset($_POST['alert_empresa']),
        'sound_empresa' => $_POST['sound_empresa'] ?? 'none',
        'keywords' => isset($_POST['alert_keywords']),
        'sound_keywords' => $_POST['sound_keywords'] ?? 'none',
        'general' => isset($_POST['alert_general']),
        'sound_general' => $_POST['sound_general'] ?? 'none',
    ];

    // 2. Processar Palavras-Chave
    $keywords = [];

    // Processar existentes
    if (isset($_POST['keywords_term']) && is_array($_POST['keywords_term'])) {
        foreach ($_POST['keywords_term'] as $idx => $term) {
            // Se o checkbox 'keywords_active' estiver setado para este índice, é true
            $isActive = isset($_POST['keywords_active'][$idx]);
            $keywords[] = [
                'term' => $term,
                'active' => $isActive
            ];
        }
    }

    // Adicionar nova palavra-chave se fornecida
    if (!empty($_POST['new_keyword'])) {
        $keywords[] = [
            'term' => trim($_POST['new_keyword']),
            'active' => true // Novas sempre ativas por padrão
        ];
    }

    // Remover duplicatas
    // (Poderíamos adicionar lógica aqui, mas por enquanto confia no usuário)

    // 3. Outras Configurações
    $continuous_alert = $_POST['continuous_alert'] ?? 'none';
    $auto_delete_days = intval($_POST['auto_delete_days'] ?? 0);
    $report_email = trim($_POST['report_email'] ?? '');

    // 4. Montar Array Final
    $configData = [
        'alerts' => $alerts,
        'keywords' => $keywords,
        'continuous_alert' => $continuous_alert,
        'auto_delete_days' => $auto_delete_days,
        'report_email' => $report_email,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['user_id']
    ];

    // 5. Salvar em JSON
    $configFile = 'monitor_config.json';
    if (file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT))) {
        $msg = "Configurações salvas com sucesso!";
        $type = "success";
    } else {
        $msg = "Erro ao salvar configurações.";
        $type = "error";
    }

    // Redirecionar de volta
    header("Location: radar_config.php?msg=" . urlencode($msg) . "&type=" . $type);
    exit();

} else {
    // Se não for POST válido
    header("Location: radar_config.php");
    exit();
}
