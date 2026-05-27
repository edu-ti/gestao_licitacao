<?php
// ==============================================
// ARQUIVO: notificacoes.php
// LÓGICA PARA ADICIONAR NOTIFICAÇÕES À FILA (COM DIAGNÓSTICO)
// ==============================================

require_once 'config.php';
require_once 'Database.php';

/**
 * Adiciona notificações à fila de e-mails e ao sistema.
 *
 * @param PDO $pdo A conexão com o banco de dados.
 * @param int $pregao_id O ID do pregão que gerou a notificação.
 * @param string $titulo O título da notificação.
 * @param string $mensagem_base A mensagem principal da notificação.
 * @param array|null $pregao_dados Dados completos do pregão (opcional). Se null, busca do banco.
 */
function criarNotificacao($pdo, $pregao_id, $titulo, $mensagem_base, $pregao_dados = null) {
    try {
        if ($pregao_dados === null) {
            $stmt = $pdo->prepare("SELECT numero_edital, numero_processo, orgao_comprador, data_sessao, hora_sessao, modalidade, objeto FROM pregoes WHERE id = ?");
            $stmt->execute(array($pregao_id));
            $pregao_dados = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pregao_dados) {
                return;
            }
        }

        $numero_edital   = isset($pregao_dados['numero_edital']) ? $pregao_dados['numero_edital'] : 'N/A';
        $numero_processo = isset($pregao_dados['numero_processo']) ? $pregao_dados['numero_processo'] : 'N/A';
        $orgao           = isset($pregao_dados['orgao_comprador']) ? $pregao_dados['orgao_comprador'] : 'N/A';
        $modalidade      = isset($pregao_dados['modalidade']) ? $pregao_dados['modalidade'] : 'N/A';
        $objeto          = isset($pregao_dados['objeto']) ? $pregao_dados['objeto'] : 'N/A';
        
        $data_sessao = 'N/A';
        if (!empty($pregao_dados['data_sessao'])) {
            $ts = strtotime($pregao_dados['data_sessao']);
            if ($ts) {
                $data_sessao = date('d/m/Y', $ts);
            }
        }
        
        $hora_sessao = 'N/A';
        if (!empty($pregao_dados['hora_sessao'])) {
            $hora_sessao = substr($pregao_dados['hora_sessao'], 0, 5);
        }

        $stmt_users = $pdo->query("SELECT id, email, nome FROM usuarios");
        $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

        if (empty($usuarios)) {
            return;
        }

        $link = "pregao_detalhes.php?id=" . $pregao_id;
        $mensagem_completa = $mensagem_base . " Clique para ver os detalhes.";
        $email_subject = "[LicitaFR] " . $titulo;

        $stmt_insert = $pdo->prepare("INSERT INTO notificacoes (usuario_destino_id, mensagem, link) VALUES (?, ?, ?)");
        $stmt_queue = $pdo->prepare("INSERT INTO email_queue (recipient_email, subject, body) VALUES (?, ?, ?)");

        $base_url = BASE_URL;

        foreach ($usuarios as $usuario) {
            $stmt_insert->execute(array($usuario['id'], $mensagem_completa, $link));

            $email_body = '<html><body style="font-family: Arial, sans-serif; color: #333;">'
                . '<div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">'
                . '<h2 style="color: #1a56db;">' . htmlspecialchars($titulo) . '</h2>'
                . '<p>Ola, <strong>' . htmlspecialchars($usuario['nome']) . '</strong>!</p>'
                . '<p>' . htmlspecialchars($mensagem_base) . '</p>'
                . '<hr style="border: none; border-top: 1px solid #eee;">'
                . '<h3 style="color: #1a56db;">Detalhes do Pregao</h3>'
                . '<table style="width: 100%; border-collapse: collapse;">'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Edital:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($numero_edital) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Processo:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($numero_processo) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Orgao:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($orgao) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Modalidade:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($modalidade) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Data da Sessao:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($data_sessao) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Horario:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($hora_sessao) . '</td></tr>'
                . '<tr><td style="padding: 8px;"><strong>Objeto:</strong></td><td style="padding: 8px;">' . htmlspecialchars($objeto) . '</td></tr>'
                . '</table><br>'
                . '<p><a href="' . $base_url . '/' . $link . '" style="background-color: #1a56db; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Detalhes do Pregao</a></p>'
                . '<br><p>Atenciosamente,<br><strong>Equipe Licitacao FR</strong></p>'
                . '</div></body></html>';

            $stmt_queue->execute(array($usuario['email'], $email_subject, $email_body));
        }

    } catch (\Throwable $e) {
        error_log("ERRO em criarNotificacao: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
    }
}

/**
 * Envia lembrete de pregão 30 minutos antes da sessão.
 *
 * @param PDO $pdo A conexão com o banco de dados.
 * @param array $pregao Dados do pregão.
 * @return bool
 */
function enviarLembretePregao($pdo, $pregao, $minutos_faltantes = 30) {
    try {
        $numero_edital   = isset($pregao['numero_edital']) ? $pregao['numero_edital'] : 'N/A';
        $numero_processo = isset($pregao['numero_processo']) ? $pregao['numero_processo'] : 'N/A';
        $orgao           = isset($pregao['orgao_comprador']) ? $pregao['orgao_comprador'] : 'N/A';
        $modalidade      = isset($pregao['modalidade']) ? $pregao['modalidade'] : 'N/A';
        
        $data_sessao = 'N/A';
        if (!empty($pregao['data_sessao'])) {
            $ts = strtotime($pregao['data_sessao']);
            if ($ts) {
                $data_sessao = date('d/m/Y', $ts);
            }
        }
        
        $hora_sessao = 'N/A';
        if (!empty($pregao['hora_sessao'])) {
            $hora_sessao = substr($pregao['hora_sessao'], 0, 5);
        }
        
        $pregao_id = $pregao['id'];
        $link = "pregao_detalhes.php?id=" . $pregao_id;

        $stmt_users = $pdo->query("SELECT id, email, nome FROM usuarios");
        $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

        if (empty($usuarios)) {
            return false;
        }

        $min_text = $minutos_faltantes >= 29 ? '30 minutos' : $minutos_faltantes . ' minutos';
        $email_subject = "[LicitaFR] Lembrete: Pregao inicia em " . $min_text . " - " . $numero_edital;
        $alert_color = $minutos_faltantes >= 29 ? '#e53e3e' : '#d97706';
        $stmt_queue = $pdo->prepare("INSERT INTO email_queue (recipient_email, subject, body) VALUES (?, ?, ?)");
        $base_url = BASE_URL;

        foreach ($usuarios as $usuario) {
            $email_body = '<html><body style="font-family: Arial, sans-serif; color: #333;">'
                . '<div style="max-width: 600px; margin: 0 auto; border: 2px solid ' . $alert_color . '; border-radius: 8px; padding: 20px;">'
                . '<h2 style="color: ' . $alert_color . ';">&#9200; Lembrete: Pregao inicia em ' . $min_text . '!</h2>'
                . '<p>Ola, <strong>' . htmlspecialchars($usuario['nome']) . '</strong>!</p>'
                . '<p>O pregao abaixo esta prestes a iniciar:</p>'
                . '<hr style="border: none; border-top: 1px solid #eee;">'
                . '<h3 style="color: #1a56db;">Detalhes do Pregao</h3>'
                . '<table style="width: 100%; border-collapse: collapse;">'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Edital:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($numero_edital) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Processo:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($numero_processo) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Orgao:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($orgao) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Modalidade:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($modalidade) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Data:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($data_sessao) . '</td></tr>'
                . '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Horario:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($hora_sessao) . '</td></tr>'
                . '</table><br>'
                . '<p><a href="' . $base_url . '/' . $link . '" style="background-color: #1a56db; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Acessar Pregao</a></p>'
                . '<br><p>Atenciosamente,<br><strong>Equipe Licitacao FR</strong></p>'
                . '</div></body></html>';

            $stmt_queue->execute(array($usuario['email'], $email_subject, $email_body));
        }

        $update_stmt = $pdo->prepare("UPDATE pregoes SET lembrete_enviado = 1 WHERE id = ?");
        $update_stmt->execute(array($pregao_id));

        return true;
    } catch (\Throwable $e) {
        error_log("ERRO em enviarLembretePregao: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
        return false;
    }
}
?>

