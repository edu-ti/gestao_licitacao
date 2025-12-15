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
 */
function criarNotificacao($pdo, $pregao_id, $titulo, $mensagem_base) {
    error_log("notificacoes.php: Função criarNotificacao iniciada para o pregão ID: " . $pregao_id);

    try {
        // 1. Buscar todos os usuários cadastrados
        $stmt_users = $pdo->query("SELECT id, email, nome FROM usuarios");
        $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        error_log("notificacoes.php: Encontrados " . count($usuarios) . " usuários para notificar.");

        if (!$usuarios) {
            error_log("Nenhum usuário encontrado para notificar.");
            return;
        }

        // 2. Preparar a notificação para inserção no banco
        $link = "pregao_detalhes.php?id=" . $pregao_id;
        $mensagem_completa = $mensagem_base . " Clique para ver os detalhes.";
        $email_subject = "[LicitaFR] " . $titulo;

        $sql_insert_notification = "INSERT INTO notificacoes (usuario_destino_id, mensagem, link) VALUES (?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert_notification);

        $sql_insert_queue = "INSERT INTO email_queue (recipient_email, subject, body) VALUES (?, ?, ?)";
        $stmt_queue = $pdo->prepare($sql_insert_queue);

        // 3. Loop para criar notificação no sistema e adicionar e-mail à fila
        foreach ($usuarios as $usuario) {
            // Inserir notificação no sistema
            $stmt_insert->execute([$usuario['id'], $mensagem_completa, $link]);
            
            // Corpo do e-mail
            $email_body = "
                <html><body>
                <p>Olá, {$usuario['nome']}!</p>
                <p>{$mensagem_base}</p>
                <p>Você pode ver mais detalhes acessando o link abaixo:</p>
                <p><a href='" . BASE_URL . "/{$link}'>Ver Detalhes do Pregão</a></p>
                <br><p>Atenciosamente,<br>Equipe Licitação FR</p>
                </body></html>";

            // Adicionar e-mail à fila para envio posterior
            $stmt_queue->execute([$usuario['email'], $email_subject, $email_body]);
            error_log("notificacoes.php: Adicionado e-mail à fila para o usuário ID: " . $usuario['id'] . " ({$usuario['email']})");
        }
        error_log("notificacoes.php: Todas as notificações foram adicionadas à fila com sucesso.");

    } catch (Exception $e) {
        // Loga o erro, mas não interrompe a execução principal
        error_log("ERRO em criarNotificacao: " . $e->getMessage());
    }
}
?>

