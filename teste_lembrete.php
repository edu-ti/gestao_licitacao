<?php
require_once 'config.php';
require_once 'Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    echo "<h2>Teste de Lembrete - Pregoes proximos</h2>";
    echo "<p>Horario atual do PHP: " . $now->format('d/m/Y H:i:s') . " (America/Sao_Paulo)</p>";

    $sql = "
        SELECT id, numero_edital, numero_processo, orgao_comprador, 
               data_sessao, hora_sessao, lembrete_enviado, status
        FROM pregoes 
        WHERE data_sessao IS NOT NULL 
          AND hora_sessao IS NOT NULL
          AND lembrete_enviado = 0
          AND status NOT IN ('Cancelado', 'Concluido')
        ORDER BY data_sessao ASC, hora_sessao ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pregoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $proximos = array();
    $passados = array();

    foreach ($pregoes as $p) {
        try {
            $sessao = new DateTime($p['data_sessao'] . ' ' . $p['hora_sessao'], new DateTimeZone('America/Sao_Paulo'));
        } catch (Throwable $e) {
            continue;
        }

        $intervalo = $now->diff($sessao);
        $minutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
        if ($intervalo->invert) {
            $minutos = -$minutos;
        }

        $p['minutos_faltantes'] = $minutos;

        if ($minutos >= 0 && $minutos <= 35) {
            $proximos[] = $p;
        } elseif ($minutos < 0) {
            $passados[] = $p;
        }
    }

    if (empty($proximos)) {
        echo "<p style='color: orange;'>Nenhum pregao encontrado com sessao nos proximos 35 minutos.</p>";
        if (!empty($passados)) {
            echo "<p>Pregoes com sessao ja passada (nao serao notificados): " . count($passados) . "</p>";
        }
        echo "<p>Crie um pregao com data/hora para daqui a 5 minutos e recarregue esta pagina.</p>";
    } else {
        echo "<p style='color: green;'>Encontrados " . count($proximos) . " pregoes na janela de lembrete:</p>";
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Edital</th><th>Data Sessao</th><th>Hora</th><th>Minutos Faltantes</th><th>Lembrete Enviado</th><th>Acao</th></tr>";
        foreach ($proximos as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['numero_edital']}</td>";
            echo "<td>{$p['data_sessao']}</td>";
            echo "<td>{$p['hora_sessao']}</td>";
            echo "<td><strong>{$p['minutos_faltantes']} min</strong></td>";
            echo "<td>" . ($p['lembrete_enviado'] ? 'Sim' : 'Nao') . "</td>";
            echo "<td><a href='cron_lembrete_pregao.php' style='color:blue;'>Forcar envio agora</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<hr><h2>Todos os pregoes cadastrados</h2>";
    $all = $pdo->query("SELECT id, numero_edital, data_sessao, hora_sessao, status, lembrete_enviado FROM pregoes ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Edital</th><th>Data Sessao</th><th>Hora</th><th>Status</th><th>Lembrete Enviado</th></tr>";
    foreach ($all as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['numero_edital']}</td>";
        echo "<td>{$p['data_sessao']}</td>";
        echo "<td>{$p['hora_sessao']}</td>";
        echo "<td>{$p['status']}</td>";
        echo "<td>" . ($p['lembrete_enviado'] ? 'Sim' : 'Nao') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Throwable $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . ":" . $e->getLine() . "</p>";
}
