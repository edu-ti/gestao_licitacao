<?php
// ==============================================
// ARQUIVO: cron_alertas_licencas.php
// SCRIPT PARA RODAR DIARIAMENTE (ex: 8h da manha)
// Verifica licencas vencendo e vencidas e envia emails
// ==============================================

set_time_limit(120);

$lock_file = __DIR__ . '/cron_alertas_licencas.lock';
$lock_handle = fopen($lock_file, 'c');

if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    exit;
}

require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $hoje = date('Y-m-d');
    $daqui_30 = date('Y-m-d', strtotime('+30 days'));

    error_log("cron_alertas_licencas.php: Verificando licencas vencendo ate {$daqui_30}...");

    $stmt_users = $pdo->query("SELECT id, email, nome FROM usuarios");
    $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    if (empty($usuarios)) {
        error_log("cron_alertas_licencas.php: Nenhum usuario encontrado.");
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
        @unlink($lock_file);
        exit;
    }

    $base_url = BASE_URL;

    // =============================================
    // 1. Licencas VENCENDO em ate 30 dias
    // =============================================
    $sql_vencendo = "
        SELECT l.id, l.titulo, l.data_vencimento, l.fornecedor_id, f.nome AS empresa_nome
        FROM licencas_certidoes l
        LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
        WHERE l.fornecedor_id IS NOT NULL
          AND l.data_vencimento IS NOT NULL
          AND l.sem_validade = 0
          AND l.data_vencimento BETWEEN ? AND ?
          AND l.notificado = 0
        ORDER BY l.data_vencimento ASC
    ";
    $stmt = $pdo->prepare($sql_vencendo);
    $stmt->execute([$hoje, $daqui_30]);
    $vencendo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_vencendo = 0;
    foreach ($vencendo as $lic) {
        $titulo = $lic['titulo'];
        $empresa = !empty($lic['empresa_nome']) ? $lic['empresa_nome'] : 'Nao definida';
        $data_venc = date('d/m/Y', strtotime($lic['data_vencimento']));
        $dias = (new DateTime($lic['data_vencimento']))->diff(new DateTime($hoje))->days;

        $mensagem_alerta = "ALERTA: {$titulo} da empresa {$empresa} vence em {$dias} dia(s) ({$data_venc}).";
        $pdo->prepare("INSERT INTO alertas_licencas (mensagem) VALUES (?)")->execute([$mensagem_alerta]);

        $email_subject = "[LicitaFR] Alerta: Licenca vence em {$dias} dias - {$titulo}";

        $sql_queue = "INSERT INTO email_queue (recipient_email, subject, body) VALUES (?, ?, ?)";
        $stmt_queue = $pdo->prepare($sql_queue);

        foreach ($usuarios as $u) {
            $body = '<html><body style="font-family: Arial, sans-serif; color: #333;">'
                . '<div style="max-width: 600px; margin: 0 auto; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px;">'
                . '<h2 style="color: #f59e0b;">&#9888; Licenca prestes a vencer!</h2>'
                . '<p>Ola, <strong>' . htmlspecialchars($u['nome']) . '</strong>!</p>'
                . '<p>O documento abaixo esta proximo do vencimento:</p>'
                . '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Documento:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($titulo) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Empresa:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($empresa) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Vencimento:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . $data_venc . '</td></tr>'
                . '<tr><td style="padding: 8px;"><strong>Faltam:</strong></td><td style="padding: 8px; color: #f59e0b; font-weight: bold;">' . $dias . ' dia(s)</td></tr>'
                . '</table>'
                . '<p><a href="' . $base_url . '/gestao_licitacao/licencas.php" style="background-color: #f59e0b; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Acessar Licencas</a></p>'
                . '<br><p>Atenciosamente,<br><strong>Equipe Licitacao FR</strong></p>'
                . '</div></body></html>';
            $stmt_queue->execute([$u['email'], $email_subject, $body]);
        }

        $pdo->prepare("UPDATE licencas_certidoes SET notificado = 1 WHERE id = ?")->execute([$lic['id']]);
        $total_vencendo++;
    }

    // =============================================
    // 2. Licencas VENCIDAS
    // =============================================
    $sql_vencidas = "
        SELECT l.id, l.titulo, l.data_vencimento, l.fornecedor_id, f.nome AS empresa_nome
        FROM licencas_certidoes l
        LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
        WHERE l.fornecedor_id IS NOT NULL
          AND l.data_vencimento IS NOT NULL
          AND l.sem_validade = 0
          AND l.data_vencimento < ?
          AND l.notificado_vencido = 0
        ORDER BY l.data_vencimento DESC
    ";
    $stmt = $pdo->prepare($sql_vencidas);
    $stmt->execute([$hoje]);
    $vencidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_vencidas = 0;
    foreach ($vencidas as $lic) {
        $titulo = $lic['titulo'];
        $empresa = !empty($lic['empresa_nome']) ? $lic['empresa_nome'] : 'Nao definida';
        $data_venc = date('d/m/Y', strtotime($lic['data_vencimento']));
        $dias = (new DateTime($lic['data_vencimento']))->diff(new DateTime($hoje))->days;

        $mensagem_alerta = "ALERTA: {$titulo} da empresa {$empresa} VENCEU em {$data_venc} (ha {$dias} dias).";
        $pdo->prepare("INSERT INTO alertas_licencas (mensagem) VALUES (?)")->execute([$mensagem_alerta]);

        $email_subject = "[LicitaFR] Alerta: Licenca VENCIDA - {$titulo}";

        $sql_queue = "INSERT INTO email_queue (recipient_email, subject, body) VALUES (?, ?, ?)";
        $stmt_queue = $pdo->prepare($sql_queue);

        foreach ($usuarios as $u) {
            $body = '<html><body style="font-family: Arial, sans-serif; color: #333;">'
                . '<div style="max-width: 600px; margin: 0 auto; border: 2px solid #ef4444; border-radius: 8px; padding: 20px;">'
                . '<h2 style="color: #ef4444;">&#10060; Licenca VENCIDA!</h2>'
                . '<p>Ola, <strong>' . htmlspecialchars($u['nome']) . '</strong>!</p>'
                . '<p>O documento abaixo esta vencido e precisa ser renovado:</p>'
                . '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Documento:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($titulo) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Empresa:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($empresa) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Venceu em:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . $data_venc . '</td></tr>'
                . '<tr><td style="padding: 8px;"><strong>Dias vencido:</strong></td><td style="padding: 8px; color: #ef4444; font-weight: bold;">' . $dias . ' dia(s)</td></tr>'
                . '</table>'
                . '<p><a href="' . $base_url . '/gestao_licitacao/licencas.php" style="background-color: #ef4444; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Acessar Licencas</a></p>'
                . '<br><p>Atenciosamente,<br><strong>Equipe Licitacao FR</strong></p>'
                . '</div></body></html>';
            $stmt_queue->execute([$u['email'], $email_subject, $body]);
        }

        $pdo->prepare("UPDATE licencas_certidoes SET notificado_vencido = 1 WHERE id = ?")->execute([$lic['id']]);
        $total_vencidas++;
    }

    error_log("cron_alertas_licencas.php: {$total_vencendo} licencas vencendo e {$total_vencidas} vencidas notificadas.");

} catch (\Throwable $e) {
    error_log("Erro no cron_alertas_licencas.php: " . $e->getMessage());
} finally {
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
    @unlink($lock_file);
}
