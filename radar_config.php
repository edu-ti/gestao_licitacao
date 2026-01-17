<?php
// --- CONFIGURAÇÃO E CONEXÃO ---
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

// Helper para verificar checkboxes
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
    $class = $type === 'success' ? 'msg-success' : 'msg-error';
    $msg = '<div class="' . $class . '">' . htmlspecialchars($_GET['msg']) . '</div>';
}

?>

<!-- CSS CUSTOMIZADO (Design Limpo e Profissional) -->
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
</style>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento de Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0">
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php
        $page_title = 'Monitoramento de Licitações';
        include 'header.php';
        ?>

        <div class="monitorar-wrapper">
            <!-- Cabeçalho da Página -->
            <div class="page-header-custom">
                <div>
                    <h1 class="page-title">Configurações de Monitoramento</h1>
                    <p class="page-subtitle">Gerencie alertas, palavras-chave e notificações</p>
                </div>
                <div>
                    <a href="radar.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">Minhas Licitações</a>
                    <a href="dashboard.php" class="btn btn-outline-secondary ml-2">&larr; Voltar ao Painel</a>
                </div>
            </div>

            <?= $msg ?? '' ?>

            <form method="POST" action="radar_config_save.php"> <!-- Action placeholder -->

                <input type="hidden" name="action" value="save_config">

                <!-- SEÇÃO 1: ALERTAS SONOROS E VISUAIS -->
                <div class="content-box">
                    <div class="box-header">
                        <i class="fas fa-bell"></i> Alertas
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Empresa -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="alert_empresa" class="sr-only peer"
                                        <?= isChecked($config['alerts']['empresa']) ?>>
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600">
                                    </div>
                                </label>
                                <span class="font-medium">Empresa</span>
                                <i class="fas fa-question-circle text-gray-400 text-sm"
                                    title="Alertas quando o nome da sua empresa for citado"></i>
                            </div>
                            <select class="custom-input w-32" name="sound_empresa">
                                <option value="apito" <?= isSelected($config['alerts']['sound_empresa'], 'apito') ?>>
                                    Apito</option>
                                <option value="pop" <?= isSelected($config['alerts']['sound_empresa'], 'pop') ?>>Pop
                                </option>
                                <option value="none" <?= isSelected($config['alerts']['sound_empresa'], 'none') ?>>Mudo
                                </option>
                            </select>
                        </div>

                        <!-- Palavras-Chave -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="alert_keywords" class="sr-only peer"
                                        <?= isChecked($config['alerts']['keywords']) ?>>
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-900">
                                    </div>
                                </label>
                                <span class="font-medium">Palavras-Chave</span>
                                <i class="fas fa-question-circle text-gray-400 text-sm"
                                    title="Alertas quando suas palavras-chave forem encontradas"></i>
                            </div>
                            <select class="custom-input w-32" name="sound_keywords">
                                <option value="pop" <?= isSelected($config['alerts']['sound_keywords'], 'pop') ?>>Pop
                                </option>
                                <option value="apito" <?= isSelected($config['alerts']['sound_keywords'], 'apito') ?>>
                                    Apito</option>
                                <option value="none" <?= isSelected($config['alerts']['sound_keywords'], 'none') ?>>Mudo
                                </option>
                            </select>
                        </div>

                        <!-- Geral -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="alert_general" class="sr-only peer"
                                        <?= isChecked($config['alerts']['general']) ?>>
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                                <span class="font-medium">Geral</span>
                                <i class="fas fa-question-circle text-gray-400 text-sm"
                                    title="Alertas para outras notificações do sistema"></i>
                            </div>
                            <select class="custom-input w-32" name="sound_general">
                                <option value="pop" <?= isSelected($config['alerts']['sound_general'], 'pop') ?>>Pop
                                </option>
                                <option value="apito" <?= isSelected($config['alerts']['sound_general'], 'apito') ?>>
                                    Apito</option>
                                <option value="none" <?= isSelected($config['alerts']['sound_general'], 'none') ?>>Mudo
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 2: PRIORIDADE DE CORES -->
                <div class="content-box">
                    <div class="box-header">
                        <i class="fas fa-palette"></i> Ordem de prioridade de cor das palavras-chave
                    </div>
                    <div class="space-y-2">
                        <div class="p-2 rounded text-white font-bold px-4" style="background-color: #f59e0b;">1 -
                            Amarelo</div>
                        <div class="p-2 rounded text-white font-bold px-4" style="background-color: #ea580c;">2 -
                            Laranja</div>
                        <div class="p-2 rounded text-white font-bold px-4" style="background-color: #0ea5e9;">3 - Azul
                            claro</div>
                        <div class="p-2 rounded text-white font-bold px-4" style="background-color: #1e3a8a;">4 - Azul
                            escuro</div>
                        <div class="p-2 rounded text-white font-bold px-4" style="background-color: #6b7280;">5 - Cinza
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 3: GERENCIAMENTO DE PALAVRAS-CHAVE -->
                <div class="content-box">
                    <div class="box-header">
                        <i class="fas fa-tags"></i> Palavras-chave
                    </div>

                    <div class="flex gap-2 mb-4">
                        <button type="button" class="px-3 py-1 border rounded hover:bg-gray-50 text-gray-600"><i
                                class="fas fa-palette"></i> Alterar cor</button>
                        <button type="button" class="px-3 py-1 border rounded hover:bg-red-50 text-red-600"><i
                                class="fas fa-trash"></i> Excluir</button>
                    </div>

                    <div class="mb-4">
                        <input type="text" name="new_keyword" class="custom-input"
                            placeholder="Adicionar palavra-chave (pressione salvar para adicionar)">
                    </div>

                    <div class="bg-gray-50 rounded border p-2 h-64 overflow-y-auto">
                        <?php foreach ($config['keywords'] as $idx => $kw): ?>
                            <div class="flex items-center gap-2 p-2 border-b last:border-0 hover:bg-blue-50">
                                <input type="checkbox" name="keywords_active[<?= $idx ?>]" value="1"
                                    class="rounded text-blue-600 focus:ring-blue-500" <?= isChecked($kw['active']) ?>>
                                <input type="hidden" name="keywords_term[<?= $idx ?>]"
                                    value="<?= htmlspecialchars($kw['term']) ?>">
                                <span class="px-2 py-0.5 rounded text-white text-sm"
                                    style="background-color: #6b7280;"><?= htmlspecialchars($kw['term']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SEÇÃO 4: ALERTAS CONTÍNUOS -->
                <div class="content-box">
                    <div class="box-header">
                        <i class="fas fa-infinity"></i> Alertas Contínuos <i
                            class="fas fa-question-circle text-gray-400 text-sm ml-2"></i>
                    </div>
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                        <input type="hidden" name="continuous_alert" id="continuous_val"
                            value="<?= $config['continuous_alert'] ?>">
                        <button type="button"
                            onclick="document.getElementById('continuous_val').value='none'; updateBtnState(this)"
                            class="continuous-btn px-4 py-2 text-sm font-medium border border-gray-200 rounded-l-lg hover:bg-gray-100 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'none' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                            Nenhum
                        </button>
                        <button type="button"
                            onclick="document.getElementById('continuous_val').value='empresa'; updateBtnState(this)"
                            class="continuous-btn px-4 py-2 text-sm font-medium border-t border-b border-gray-200 hover:bg-gray-100 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'empresa' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                            Empresa
                        </button>
                        <button type="button"
                            onclick="document.getElementById('continuous_val').value='todos'; updateBtnState(this)"
                            class="continuous-btn px-4 py-2 text-sm font-medium border border-gray-200 rounded-r-lg hover:bg-gray-100 focus:z-10 focus:ring-2 focus:ring-blue-700 <?= $config['continuous_alert'] == 'todos' ? 'bg-blue-900 text-white' : 'bg-white text-gray-900' ?>">
                            Todos
                        </button>
                    </div>
                    <script>
                        function updateBtnState(btn) {
                            document.querySelectorAll('.continuous-btn').forEach(b => {
                                b.classList.remove('bg-blue-900', 'text-white');
                                b.classList.add('bg-white', 'text-gray-900');
                            });
                            btn.classList.remove('bg-white', 'text-gray-900');
                            btn.classList.add('bg-blue-900', 'text-white');
                        }
                    </script>
                </div>

                <!-- SEÇÃO 5: EXCLUSÃO AUTOMÁTICA E RELATÓRIO -->
                <div class="content-box">
                    <div class="mb-6">
                        <div class="box-header mb-2 relative">
                            Exclusão Automática
                        </div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tempo para exclusão: <i
                                class="fas fa-question-circle text-gray-400"></i></label>
                        <div class="flex items-center gap-4">
                            <input type="range" name="auto_delete_days" min="0" max="100"
                                value="<?= $config['auto_delete_days'] ?>"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                oninput="document.getElementById('range_display').innerText = this.value == 0 ? 'Nunca' : this.value + ' dias'">
                            <span id="range_display"
                                class="px-3 py-1 bg-gray-100 rounded text-sm font-medium text-gray-700 min-w-[80px] text-center">
                                <?= $config['auto_delete_days'] == 0 ? 'Nunca' : $config['auto_delete_days'] . ' dias' ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="box-header mb-2">
                            Relatório por e-mail: <i class="fas fa-question-circle text-gray-400"></i>
                        </div>
                        <input type="email" name="report_email" class="custom-input bg-gray-50 mb-2"
                            value="<?= htmlspecialchars($config['report_email']) ?>" placeholder="Digite aqui o e-mail">
                        <p class="text-xs text-justify text-gray-500 bg-gray-50 p-3 rounded border border-gray-100">
                            <strong>Garanta o recebimento:</strong> inclua o e-mail
                            <strong>editais@frpe.app.br</strong> em sua lista de remetentes confiáveis. Este
                            procedimento impede que nossos e-mails sejam falsamente interpretados como spam.
                        </p>
                    </div>
                </div>

                <!-- BOTÕES DE AÇÃO -->
                <div class="flex items-center gap-4 mt-6">
                    <button type="submit" class="btn-save shadow-lg hover:shadow-xl transition-shadow">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <button type="button"
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 font-medium cursor-pointer transition-colors shadow-sm"
                        onclick="window.history.back()">
                        <i class="fas fa-undo"></i> Cancelar
                    </button>
                </div>

            </form>
        </div>
</body>

</html>