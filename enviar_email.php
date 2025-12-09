<?php
// Arquivo: enviar_email.php
// Contém funções para enviar e-mails (adaptado para o processador de fila)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'config.php';

/**
 * Inicializa e configura o objeto PHPMailer com uma conexão SMTP persistente.
 * @return PHPMailer|false Retorna o objeto PHPMailer em caso de sucesso ou false em caso de falha.
 */
function inicializarMailer() {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPKeepAlive = true; // Mantém a conexão aberta para enviar vários e-mails

        $mail->setFrom(SMTP_USER, APP_NAME);
        
        return $mail;
    } catch (Exception $e) {
        error_log("PHPMailer Error (Inicialização): {$e->getMessage()}");
        return false;
    }
}

/**
 * Envia uma notificação por e-mail usando uma instância existente do PHPMailer.
 *
 * @param PHPMailer $mail O objeto PHPMailer já inicializado.
 * @param string $to O endereço de e-mail do destinatário.
 * @param string $subject O assunto do e-mail.
 * @param string $message O corpo do e-mail em HTML.
 * @return bool Retorna true em caso de sucesso, false em caso de falha.
 */
function enviarEmail($mail, $to, $subject, $message) {
    try {
        $mail->clearAddresses(); // Limpa os destinatários anteriores
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        return $mail->send();
    } catch (Exception $e) {
        // A mensagem de erro já é guardada em $mail->ErrorInfo
        return false;
    }
}
?>

