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
    <title>Radar de Licitações - PE Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0">

    <style>
        /* Animação simples para pulso */
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }
    </style>
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8">

    <div class="container mx-auto bg-white p-6 sm:p-8 rounded-lg shadow-lg min-h-[80vh]">
        <?php
        $page_title = 'Monitoramento de Licitações';
        include 'header.php';
        ?>

        <!-- Cabeçalho da Página -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 pb-4 border-b border-gray-100">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-satellite-dish text-blue-900"></i>
                    Radar de Licitações
                    <span class="text-sm font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">PE Integrado</span>
                </h1>
                <p class="text-gray-500 mt-1 text-sm">Monitoramento em tempo real de avisos e mensagens do portal</p>
            </div>
            <div class="flex gap-2">
                <a href="radar_config.php" class="btn btn-secondary shadow-sm">
                    <i class="fas fa-cog"></i> Configurações
                </a>
                <a href="dashboard.php"
                    class="btn btn-outline-secondary border-gray-300 text-gray-600 hover:bg-gray-50">
                    &larr; Voltar
                </a>
            </div>
        </div>

        <!-- Ações Principais -->
        <div
            class="flex flex-col md:flex-row justify-between items-center bg-gray-50 p-4 rounded-lg border border-gray-200 mb-8">
            <div class="flex items-center gap-3 mb-4 md:mb-0">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse-slow"></div>
                <div>
                    <span class="text-sm font-bold text-gray-700 block">Status do Monitor</span>
                    <span class="text-xs text-gray-500">Aguardando execução manual ou agendada</span>
                </div>
            </div>

            <form method="post" class="flex items-center">
                <button type="submit" name="run_monitor"
                    class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded shadow hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                    <i class="fas fa-sync-alt mr-2"></i> Executar Monitoramento Agora
                </button>
            </form>
        </div>

        <!-- Feedback de Sucesso -->
        <?php if (isset($_GET['success'])): ?>
            <div
                class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-start gap-3">
                <i class="fas fa-check-circle mt-1 text-xl"></i>
                <div>
                    <h3 class="font-bold">Monitoramento Concluído</h3>
                    <p class="text-sm">A varredura foi finalizada com sucesso. Verifique os resultados abaixo.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Console Log (se houver output) -->
        <?php if (!empty($msgFeedback)): ?>
            <div class="bg-gray-900 text-gray-200 p-4 rounded-lg mb-6 font-mono text-xs overflow-x-auto shadow-inner">
                <h4 class="text-gray-400 border-b border-gray-700 pb-2 mb-2 uppercase font-bold text-[10px]">Console Output
                </h4>
                <div class="whitespace-pre-wrap leading-relaxed"><?php echo $msgFeedback; ?></div>
            </div>
        <?php endif; ?>

        <!-- Elementos de Áudio -->
        <audio id="audio-apito" src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" preload="auto"></audio>
        <audio id="audio-pop" src="https://actions.google.com/sounds/v1/cartoon/pop.ogg" preload="auto"></audio>

        <!-- Seção de Histórico -->
        <div class="mb-4 flex items-center gap-2">
            <h2 class="text-xl font-bold text-gray-700 border-l-4 border-blue-900 pl-3">Histórico de Atividade</h2>
            <span class="bg-gray-200 text-gray-600 text-xs font-bold px-2 py-1 rounded-full"><?= count($logs) ?>
                registros</span>
        </div>

        <?php if (empty($logs)): ?>
            <div
                class="flex flex-col items-center justify-center py-16 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                <div class="bg-white p-4 rounded-full shadow-sm mb-4">
                    <i class="fas fa-search text-3xl text-blue-200"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-700">Nenhum registro encontrado</h3>
                <p class="mb-4 text-sm text-gray-400">O monitor ainda não rodou ou não encontrou mensagens relevantes.</p>
            </div>
        <?php else: ?>

            <!-- Script de Áudio -->
            <?php if (isset($_GET['success']) && !empty($logs[0]['is_alert'])):
                // Carrega config
                $soundToPlay = 'none';
                $latestLog = $logs[0];
                $configFile = 'monitor_config.json';
                $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

                // Determina som
                if (isset($latestLog['keyword']) && strpos($latestLog['keyword'], '(Empresa)') !== false) {
                    $soundToPlay = $config['alerts']['sound_empresa'] ?? 'none';
                } else {
                    $soundToPlay = $config['alerts']['sound_keywords'] ?? 'none';
                }
                ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const sound = "<?= $soundToPlay ?>";
                        if (sound !== 'none') {
                            const audio = document.getElementById('audio-' + sound);
                            if (audio) {
                                audio.play().catch(e => console.log('Autoplay bloqueado (interação necessária)'));
                            }
                        }
                    });
                </script>
            <?php endif; ?>

            <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                <table class="w-full border-collapse bg-white text-left text-sm text-gray-500">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-medium text-gray-900 w-40">Data Coleta</th>
                            <th scope="col" class="px-6 py-4 font-medium text-gray-900 w-32 text-center">Status</th>
                            <th scope="col" class="px-6 py-4 font-medium text-gray-900 w-48">Remetente</th>
                            <th scope="col" class="px-6 py-4 font-medium text-gray-900 w-40">Data Origem</th>
                            <th scope="col" class="px-6 py-4 font-medium text-gray-900">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 border-t border-gray-100">
                        <?php foreach ($logs as $log):
                            $colorCode = match ($log['color'] ?? '5') {
                                '1' => '#f59e0b', // Amarelo
                                '2' => '#ea580c', // Laranja
                                '3' => '#0ea5e9', // Azul Claro
                                '4' => '#1e3a8a', // Azul Escuro
                                default => '#6b7280' // Cinza
                            };

                            $rowClass = $log['is_alert'] ? 'bg-red-50/50 hover:bg-red-50' : 'hover:bg-gray-50';
                            ?>
                            <tr class="<?= $rowClass ?> transition-colors duration-150">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($log['is_alert']): ?>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold text-white shadow-sm"
                                            style="background-color: <?= $colorCode ?>;">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white/50"></span>
                                            ALERTA
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                            VISTO
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <span class="font-medium"><?php echo htmlspecialchars($log['remetente']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($log['data_mensagem']); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600 leading-relaxed">
                                    <?php
                                    $texto = htmlspecialchars($log['texto']);
                                    if ($log['is_alert'] && !empty($log['keyword'])) {
                                        $cleanKeyword = str_replace(' (Empresa)', '', $log['keyword']);
                                        if (!empty($cleanKeyword)) {
                                            // Highlight mais bonito
                                            $texto = preg_replace(
                                                '/(' . preg_quote($cleanKeyword, '/') . ')/i',
                                                '<span class="font-bold text-red-600 bg-red-100 px-1 rounded mx-0.5 border-b border-red-200">$1</span>',
                                                $texto
                                            );
                                        }
                                    }
                                    echo $texto;
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>