<?php
// ==============================================
// ARQUIVO: cron_lembrete_pregao.php
// SCRIPT PARA SER EXECUTADO VIA CRON A CADA 1 MINUTO
// Envia e-mails de lembrete 30 minutos antes do pregao iniciar
// ==============================================

set_time_limit(120);

$lock_file = __DIR__ . '/cron_lembrete_pregao.lock';
$lock_handle = fopen($lock_file, 'c');

if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    exit;
}

require_once 'config.php';
require_once 'Database.php';
require_once 'notificacoes.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $sql = "
        SELECT id, numero_edital, numero_processo, orgao_comprador, 
               data_sessao, hora_sessao, modalidade, objeto
        FROM pregoes 
        WHERE data_sessao IS NOT NULL 
          AND hora_sessao IS NOT NULL
          AND lembrete_enviado = 0
          AND status NOT IN ('Cancelado', 'Concluido')
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pregoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pregoes)) {
        error_log("cron_lembrete_pregao.php: Nenhum pregao pendente de lembrete.");
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
        @unlink($lock_file);
        exit;
    }

    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $enviados = 0;

    foreach ($pregoes as $pregao) {
        $data_sessao = $pregao['data_sessao'];
        $hora_sessao = $pregao['hora_sessao'];

        try {
            $sessao = new DateTime($data_sessao . ' ' . $hora_sessao, new DateTimeZone('America/Sao_Paulo'));
        } catch (\Throwable $e) {
            error_log("cron_lembrete_pregao.php: Data invalida para pregao ID {$pregao['id']}: {$data_sessao} {$hora_sessao}");
            continue;
        }

        $intervalo = $now->diff($sessao);

        if ($intervalo->invert) {
            continue;
        }

        $minutos_faltantes = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;

        if ($minutos_faltantes >= 0 && $minutos_faltantes <= 35) {
            error_log("cron_lembrete_pregao.php: Enviando lembrete para pregao ID {$pregao['id']} (faltam {$minutos_faltantes} min)");
            enviarLembretePregao($pdo, $pregao, $minutos_faltantes);
            $enviados++;
        }
    }

    if ($enviados > 0) {
        error_log("cron_lembrete_pregao.php: {$enviados} lembretes enviados.");
    } else {
        error_log("cron_lembrete_pregao.php: Nenhum pregao dentro da janela de lembrete (0-35 min).");
    }

} catch (\Throwable $e) {
    error_log("Erro no cron_lembrete_pregao.php: " . $e->getMessage());
} finally {
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
    @unlink($lock_file);
}
