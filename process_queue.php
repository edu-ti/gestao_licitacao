<?php
// ==============================================
// ARQUIVO: process_queue.php
// SCRIPT PARA SER EXECUTADO VIA CRON JOB
// Processa a fila e envia os e-mails de forma controlada.
// ==============================================

// Aumenta o tempo de execução para o script de background
set_time_limit(300); // 5 minutos

// Define um lock para evitar execuções sobrepostas
$lock_file = __DIR__ . '/process_queue.lock';
$lock_handle = fopen($lock_file, 'c');

if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    // Processo já em execução, termina silenciosamente.
    exit;
}

require_once 'config.php';
require_once 'Database.php';
require_once 'enviar_email.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Busca até 5 e-mails pendentes para enviar (lote reduzido para evitar ratelimit)
    $stmt = $pdo->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 5 LIMIT 5");
    $stmt->execute();
    $emails_para_enviar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails_para_enviar)) {
        // Sem e-mails, termina silenciosamente.
        flock($lock_handle, LOCK_UN); // Liberta o lock
        fclose($lock_handle);
        @unlink($lock_file); // Remove o ficheiro de lock
        exit;
    }

    $mail = inicializarMailer();
    if (!$mail) {
        throw new Exception("Falha ao inicializar o PHPMailer.");
    }

    foreach ($emails_para_enviar as $email_job) {
        $success = enviarEmail($mail, $email_job['recipient_email'], $email_job['subject'], $email_job['body']);

        if ($success) {
            $update_stmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update_stmt->execute([$email_job['id']]);
        } else {
            $error_info = $mail->ErrorInfo;
            $update_stmt = $pdo->prepare("UPDATE email_queue SET attempts = attempts + 1, error_message = ? WHERE id = ?");
            $update_stmt->execute([$error_info, $email_job['id']]);
            // Regista o erro no log do servidor para análise posterior
            error_log("Falha ao enviar e-mail para {$email_job['recipient_email']}. Erro: $error_info");
        }
        
        // Adiciona uma pausa de 10 segundos entre cada envio para respeitar os limites do servidor
        sleep(10);
    }
    
    if ($mail->SMTPKeepAlive) {
        $mail->smtpClose();
    }

} catch (Exception $e) {
    error_log("Erro no processamento da fila de e-mails: " . $e->getMessage());
} finally {
    // Garante que o lock é sempre libertado
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
    @unlink($lock_file);
}
?>

