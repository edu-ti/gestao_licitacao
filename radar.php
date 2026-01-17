<?php
// ==============================================
// ARQUIVO: radar.php
// DASHBOARD DO MONITOR DE MENSAGENS
// ==============================================

ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';

// Lógica de Execução Manual
$msgFeedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_monitor'])) {
    // Executa o script em background ou diretamente
    // Como estamos no Windows e é local, vamos usar exec.
    // Em produção, ideal seria disparar job na fila, mas aqui é protótipo.

    $output = [];
    $return_var = 0;
    // Redireciona 2>&1 para capturar erros também
    exec('php D:\gestao_licitacao\monitor_pe.php 2>&1', $output, $return_var);

    $msgFeedback = implode("<br>", $output);

    // Força recarregar logs
    header("Location: radar.php?success=1");
    exit;
}

// Leitura dos Logs
$logs = [];
$logFile = 'monitor_logs.json';
if (file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true) ?? [];
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radar de Licitações - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0">
    <!-- Usando CSS inline para protótipo rápido, ideal mover para .css -->
    <style>
        /* Reset básico para esta área */
        .licencas-wrapper {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
            min-height: 80vh;
        }

        /* Cabeçalho */
        .page-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
        }

        .page-title {
            font-size: 24px;
            color: #333;
            margin: 0;
            font-weight: 600;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Container Branco (Card) */
        .content-box {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .box-header {
            font-size: 18px;
            color: #495057;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Formulário */
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 13px;
        }

        .custom-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out;
            box-sizing: border-box;
            /* Garante que padding não quebre layout */
        }

        .custom-input:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .btn-save {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            height: 42px;
            /* Altura igual aos inputs */
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background-color: #218838;
        }

        /* Tabela */
        .table-container {
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .custom-table th {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .custom-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #333;
            vertical-align: middle;
        }

        .custom-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges e Ações */
        .status-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            margin-right: 4px;
            transition: opacity 0.2s;
            border: none;
        }

        .action-btn:hover {
            opacity: 0.8;
            color: white;
        }

        .btn-view {
            background-color: #17a2b8;
        }

        .btn-down {
            background-color: #6c757d;
        }

        .btn-del {
            background-color: #dc3545;
        }

        /* Mensagens */
        .msg-success {
            padding: 15px;
            background: #d4edda;
            color: #155724;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .msg-error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Responsivo */
        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .btn-save {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .btn-run {
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .btn-run:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .badge-alert {
            background-color: #e74c3c;
        }

        /* Vermelho */
        .badge-info {
            background-color: #95a5a6;
        }

        /* Cinza */

        .keyword-hit {
            color: #c0392b;
            font-weight: bold;
        }

        .log-console {
            background: #2d3436;
            color: #dfe6e9;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            margin-bottom: 20px;
            display: none;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
    </style>
</head>

<body>

    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php
        $page_title = 'Monitoramento de Licitações';
        include 'header.php';
        ?>

        <div class="monitorar-wrapper">
            <!-- Cabeçalho da Página -->
            <div class="page-header-custom">
                <div>
                    <span>Monitoramento Avisos de Licitações</span>
                </div>
                <a href="radar.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">Minhas Licitações</a>
                <a href="radar_config.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">Configuração</a>
                <a href="dashboard.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">&larr; Voltar ao
                    Painel</a>
            </div>
        </div>



        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>📡 Radar de Licitações (PE Integrado)</h1>

                <form method="post">
                    <button type="submit" name="run_monitor" class="btn-run">🔄 Executar Monitoramento Agora</button>
                </form>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    Monitoramento executado com sucesso! Verifique os logs abaixo.
                </div>
            <?php endif; ?>

            <?php if (!empty($msgFeedback)): ?>
                <div class="log-console" style="display: block;">
                    <strong>Console Output:</strong><br>
                    <?php echo $msgFeedback; ?>
                </div>
            <?php endif; ?>

            <h2>Histórico de Atividade</h2>

            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <h3>Nenhum registro encontrado</h3>
                    <p>O monitor ainda não rodou ou não encontrou mensagens.</p>
                    <p>Clique em "Executar Monitoramento Agora" para testar.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="150">Data Coleta</th>
                            <th width="100">Status</th>
                            <th width="150">Remetente (Portal)</th>
                            <th width="150">Data (Portal)</th>
                            <th>Mensagem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr style="<?php echo $log['is_alert'] ? 'background-color: #fff5f5;' : ''; ?>">
                                <td>
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                                <td>
                                    <?php if ($log['is_alert']): ?>
                                        <span class="badge badge-alert">ALERTA</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">VISTO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['remetente']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['data_mensagem']); ?>
                                </td>
                                <td>
                                    <?php
                                    $texto = htmlspecialchars($log['texto']);
                                    if ($log['is_alert'] && !empty($log['keyword'])) {
                                        // Destaca a palavra-chave
                                        $texto = str_ireplace($log['keyword'], '<span class="keyword-hit">' . strtoupper($log['keyword']) . '</span>', $texto);
                                    }
                                    echo $texto;
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>

</body>

</html>