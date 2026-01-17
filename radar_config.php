<?php
// ==============================================
// ARQUIVO: radar_config.php
// CONFIGURAÇÕES DO MONITOR
// ==============================================
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

// Carregar Configurações Existentes
$configFile = 'monitor_config.json';
$defaultConfig = [
    'alerts' => [
        'empresa' => true,
        'sound_empresa' => 'apito',
        'keywords' => true,
        'sound_keywords' => 'pop',
        'general' => true,
        'sound_general' => 'none'
    ],
    'keywords' => [
        ['term' => 'iminência', 'active' => true],
        ['term' => 'recurso', 'active' => true],
        ['term' => 'desempate', 'active' => true],
        ['term' => 'anexo', 'active' => true],
        ['term' => 'originais', 'active' => true]
    ],
    'continuous_alert' => 'none',
    'auto_delete_days' => 0,
    'report_email' => ''
];

$config = $defaultConfig;
if (file_exists($configFile)) {
    $loaded = json_decode(file_get_contents($configFile), true);
    if ($loaded) {
        $config = array_replace_recursive($defaultConfig, $loaded);
    }
}

// Helpers
function isChecked($val)
{
    return $val ? 'checked' : '';
}
function isSelected($current, $val)
{
    return $current === $val ? 'selected' : '';
}

// Mensagens de Feedback
$msg = '';
if (isset($_GET['msg'])) {
    $type = $_GET['type'] ?? 'success';
    $msgClass = $type === 'success'
        ? 'bg-green-100 border-l-4 border-green-500 text-green-700'
        : 'bg-red-100 border-l-4 border-red-500 text-red-700';
    $msg = '<div class="' . $msgClass . ' p-4 mb-6 rounded shadow-sm">' . htmlspecialchars($_GET['msg']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Monitoramento de Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0">
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8">

    <div class="container mx-auto bg-white p-6 sm:p-8 rounded-lg shadow-lg min-h-[80vh]">
        <?php
        $page_title = 'Monitoramento de Licitações';
        include 'header.php';
        ?>

        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 pb-4 border-b border-gray-100">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl font-bold text-gray-700">Configurações de Monitoramento</h1>
                <p class="text-gray-500 mt-1 text-sm">Gerencie alertas, palavras-chave e notificações</p>
            </div>
            <div class="flex gap-2">
                <a href="radar.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800 text-white shadow-sm">
                    <i class="fas fa-satellite-dish mr-2"></i> Ir para Radar
                </a>
                <a href="dashboard.php"
                    class="btn btn-outline-secondary border-gray-300 text-gray-600 hover:bg-gray-50">
                    &larr; Voltar
                </a>
            </div>
        </div>

        <?= $msg ?>

        <form method="POST" action="radar_config_save.php">
            <input type="hidden" name="action" value="save_config">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- COLUNA ESQUERDA -->
                <div class="space-y-6">

                    <!-- SEÇÃO 1: ALERTAS -->
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                            <i class="fas fa-bell text-blue-600 text-lg"></i>
                            <h3 class="font-bold text-gray-700">Alertas</h3>
                        </div>

                        <div class="p-5 space-y-4">
                            <!-- Empresa -->
                            <div
                                class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded hover:border-gray-300 transition-colors">
                                <div class="flex items-center gap-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="alert_empresa" class="sr-only peer"
                                            <?= isChecked($config['alerts']['empresa']) ?>>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600">
                                        </div>
                                    </label>
                                    <div>
                                        <span class="font-medium text-gray-800 block">Sua Empresa</span>
                                        <span class="text-xs text-gray-500">Citação direta ou CNPJ</span>
                                    </div>
                                </div>
                                <select
                                    class="form-select text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                                    name="sound_empresa">
                                    <option value="apito" <?= isSelected($config['alerts']['sound_empresa'], 'apito') ?>>
                                        🔊 Apito</option>
                                    <option value="pop" <?= isSelected($config['alerts']['sound_empresa'], 'pop') ?>>🎵
                                        Pop</option>
                                    <option value="none" <?= isSelected($config['alerts']['sound_empresa'], 'none') ?>>🔕
                                        Mudo</option>
                                </select>
                            </div>

                            <!-- Termos da Empresa -->
                            <div id="company_terms_container"
                                class="ml-14 -mt-2 mb-2 p-3 bg-gray-50 rounded-md border border-gray-200 text-sm"
                                style="<?= empty($config['alerts']['empresa']) ? 'display:none;' : '' ?>">
                                <label class="block font-medium text-gray-700 mb-1">Termos monitorados (separados por
                                    vírgula):</label>
                                <input type="text" name="company_terms"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"
                                    value="<?= htmlspecialchars($config['company_terms'] ?? '') ?>"
                                    placeholder="Ex: Minha Empresa, 00.000.000/0001-00">
                                <p class="text-xs text-gray-500 mt-1">O sistema dará prioridade máxima (cor 1) para
                                    estes termos.</p>
                            </div>

                            <!-- Palavras-Chave -->
                            <div
                                class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded hover:border-gray-300 transition-colors">
                                <div class="flex items-center gap-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="alert_keywords" class="sr-only peer"
                                            <?= isChecked($config['alerts']['keywords']) ?>>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                                        </div>
                                    </label>
                                    <div>
                                        <span class="font-medium text-gray-800 block">Palavras-Chave</span>
                                        <span class="text-xs text-gray-500">Termos específicos</span>
                                    </div>
                                </div>
                                <select
                                    class="form-select text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                                    name="sound_keywords">
                                    <option value="pop" <?= isSelected($config['alerts']['sound_keywords'], 'pop') ?>>🎵
                                        Pop</option>
                                    <option value="apito" <?= isSelected($config['alerts']['sound_keywords'], 'apito') ?>>🔊 Apito</option>
                                    <option value="none" <?= isSelected($config['alerts']['sound_keywords'], 'none') ?>>
                                        🔕 Mudo</option>
                                </select>
                            </div>

                            <!-- Geral -->
                            <div
                                class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded hover:border-gray-300 transition-colors">
                                <div class="flex items-center gap-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="alert_general" class="sr-only peer"
                                            <?= isChecked($config['alerts']['general']) ?>>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                        </div>
                                    </label>
                                    <div>
                                        <span class="font-medium text-gray-800 block">Geral</span>
                                        <span class="text-xs text-gray-500">Outras notificações</span>
                                    </div>
                                </div>
                                <select
                                    class="form-select text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                                    name="sound_general">
                                    <option value="pop" <?= isSelected($config['alerts']['sound_general'], 'pop') ?>>🎵
                                        Pop</option>
                                    <option value="apito" <?= isSelected($config['alerts']['sound_general'], 'apito') ?>>
                                        🔊 Apito</option>
                                    <option value="none" <?= isSelected($config['alerts']['sound_general'], 'none') ?>>🔕
                                        Mudo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO: ALERTAS CONTÍNUOS -->
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                            <i class="fas fa-infinity text-purple-600 text-lg"></i>
                            <h3 class="font-bold text-gray-700">Alertas Contínuos</h3>
                        </div>
                        <div class="p-5">
                            <p class="text-sm text-gray-500 mb-4">Escolha quais tipos de alerta devem tocar
                                repetidamente até serem vistos:</p>
                            <div class="inline-flex rounded-md shadow-sm w-full" role="group">
                                <input type="hidden" name="continuous_alert" id="continuous_val"
                                    value="<?= $config['continuous_alert'] ?>">

                                <button type="button" onclick="setContinuous('none', this)"
                                    class="w-1/3 px-4 py-2 text-sm font-medium border border-gray-200 rounded-l-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'none' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                                    Nenhum
                                </button>
                                <button type="button" onclick="setContinuous('empresa', this)"
                                    class="w-1/3 px-4 py-2 text-sm font-medium border-t border-b border-gray-200 hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'empresa' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                                    Apenas Empresa
                                </button>
                                <button type="button" onclick="setContinuous('todos', this)"
                                    class="w-1/3 px-4 py-2 text-sm font-medium border border-gray-200 rounded-r-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'todos' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                                    Todos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RELATÓRIO E LIMPEZA -->
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                            <i class="fas fa-envelope-open-text text-gray-600 text-lg"></i>
                            <h3 class="font-bold text-gray-700">Relatórios e Limpeza</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail para relatório
                                    diário:</label>
                                <input type="email" name="report_email"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"
                                    value="<?= htmlspecialchars($config['report_email']) ?>"
                                    placeholder="email@exemplo.com">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Exclusão automática de logs
                                    antigos:</label>
                                <div class="flex items-center gap-4">
                                    <input type="range" name="auto_delete_days" min="0" max="60"
                                        value="<?= $config['auto_delete_days'] ?>"
                                        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                        oninput="document.getElementById('range_display').innerText = this.value == 0 ? 'Nunca' : this.value + ' dias'">
                                    <span id="range_display"
                                        class="px-3 py-1 bg-gray-100 border border-gray-200 rounded text-sm font-bold text-gray-700 w-24 text-center">
                                        <?= $config['auto_delete_days'] == 0 ? 'Nunca' : $config['auto_delete_days'] . ' dias' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- COLUNA DIREITA (Palavras-Chave) -->
                <div>
                    <div
                        class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden h-full flex flex-col">
                        <div
                            class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-tags text-orange-500 text-lg"></i>
                                <h3 class="font-bold text-gray-700">Palavras-Chave</h3>
                            </div>
                            <span class="text-xs font-semibold bg-blue-100 text-blue-800 px-2 py-0.5 rounded">
                                <?= count($config['keywords']) ?> ativas
                            </span>
                        </div>

                        <!-- Legenda de Cores -->
                        <div class="px-5 py-3 bg-white border-b border-gray-100">
                            <p class="text-xs text-gray-500 mb-2 font-medium uppercase tracking-wide">Prioridade de
                                Cores</p>
                            <div class="flex flex-wrap gap-2">
                                <span class="w-4 h-4 rounded-full bg-amber-500 shadow-sm" title="1 - Alta"></span>
                                <span class="w-4 h-4 rounded-full bg-orange-600 shadow-sm"
                                    title="2 - Média-Alta"></span>
                                <span class="w-4 h-4 rounded-full bg-sky-500 shadow-sm" title="3 - Média"></span>
                                <span class="w-4 h-4 rounded-full bg-blue-900 shadow-sm" title="4 - Média-Baixa"></span>
                                <span class="w-4 h-4 rounded-full bg-gray-500 shadow-sm" title="5 - Baixa"></span>
                            </div>
                        </div>

                        <!-- Adicionar Nova -->
                        <div class="p-5 bg-gray-50 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" name="new_keyword"
                                    class="flex-grow border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500 shadow-sm p-2 text-sm"
                                    placeholder="Nova palavra-chave...">
                                <select name="new_keyword_color"
                                    class="border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500 shadow-sm p-2 text-sm w-full sm:w-auto">
                                    <option value="1">🟡 Alta</option>
                                    <option value="2">🟠 Média+</option>
                                    <option value="3">🔵 Média</option>
                                    <option value="4">🧿 Baixa+</option>
                                    <option value="5" selected>🔘 Baixa</option>
                                </select>
                                <button type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition-colors flex items-center justify-center">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Lista -->
                        <div class="flex-grow overflow-y-auto p-2 space-y-1 max-h-[600px] bg-gray-100/50">
                            <?php if (empty($config['keywords'])): ?>
                                <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                                    <i class="far fa-folder-open text-4xl mb-2 opacity-30"></i>
                                    <p>Nenhuma palavra-chave cadastrada.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($config['keywords'] as $idx => $kw):
                                    $colorCode = match ($kw['color'] ?? '5') {
                                        '1' => '#f59e0b', // amber-500
                                        '2' => '#ea580c', // orange-600
                                        '3' => '#0ea5e9', // sky-500
                                        '4' => '#1e3a8a', // blue-900
                                        default => '#6b7280' // gray-500
                                    };
                                    ?>
                                    <div
                                        class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-md hover:shadow-md transition-all">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" name="keywords_active[<?= $idx ?>]" value="1"
                                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 transition-colors cursor-pointer"
                                                <?= isChecked($kw['active']) ?>>
                                        </div>

                                        <input type="hidden" name="keywords_term[<?= $idx ?>]"
                                            value="<?= htmlspecialchars($kw['term']) ?>">

                                        <div class="flex-grow min-w-0">
                                            <span
                                                class="px-2 py-1 rounded text-white text-xs font-bold inline-block truncate max-w-full shadow-sm"
                                                style="background-color: <?= $colorCode ?>;">
                                                <?= htmlspecialchars($kw['term']) ?>
                                            </span>
                                        </div>

                                        <select name="keywords_color[<?= $idx ?>]"
                                            class="text-xs border-gray-200 bg-gray-50 rounded p-1 w-20 focus:ring-0 focus:border-blue-300">
                                            <option value="1" <?= isSelected($kw['color'] ?? '5', '1') ?>>🟡 Alta</option>
                                            <option value="2" <?= isSelected($kw['color'] ?? '5', '2') ?>>🟠 Méd+</option>
                                            <option value="3" <?= isSelected($kw['color'] ?? '5', '3') ?>>🔵 Méd</option>
                                            <option value="4" <?= isSelected($kw['color'] ?? '5', '4') ?>>🧿 Bai+</option>
                                            <option value="5" <?= isSelected($kw['color'] ?? '5', '5') ?>>🔘 Bai</option>
                                        </select>

                                        <button type="button" onclick="this.closest('.group').remove()"
                                            class="text-gray-300 hover:text-red-500 transition-colors p-1" title="Excluir">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- BARRA DE AÇÃO FLUTUANTE ou FIXA -->
            <div
                class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-end gap-4 sticky bottom-0 bg-[#d9e3ec]/90 backdrop-blur p-4 -mx-4 -mb-4 sm:-mx-8 sm:-mb-8 sm:rounded-b-lg z-10">
                <button type="button"
                    class="px-6 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-700 font-medium shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all"
                    onclick="window.history.back()">
                    Cancelar
                </button>
                <button type="submit"
                    class="px-8 py-2.5 bg-green-600 border border-transparent rounded-lg text-white font-bold shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all transform hover:-translate-y-0.5">
                    <i class="fas fa-save mr-2"></i> Salvar Alterações
                </button>
            </div>

        </form>
    </div>

    <script>
        // Toggle Company Terms
        const empresaCheckbox = document.querySelector('input[name="alert_empresa"]');
        const paramsContainer = document.getElementById('company_terms_container');
        if (empresaCheckbox && paramsContainer) {
            empresaCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    paramsContainer.style.display = 'block';
                    paramsContainer.classList.remove('opacity-0');
                    paramsContainer.classList.add('opacity-100');
                } else {
                    paramsContainer.style.display = 'none';
                }
            });
        }

        // Set Continuous Button State
        function setContinuous(val, btn) {
            document.getElementById('continuous_val').value = val;
            const container = btn.parentElement;
            const buttons = container.querySelectorAll('button');

            buttons.forEach(b => {
                b.classList.remove('bg-blue-900', 'text-white');
                b.classList.add('bg-white', 'text-gray-900');
            });

            btn.classList.remove('bg-white', 'text-gray-900');
            btn.classList.add('bg-blue-900', 'text-white');
        }
    </script>
</body>

</html>