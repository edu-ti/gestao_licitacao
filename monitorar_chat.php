<?php
// ==============================================
// ARQUIVO: monitorar_chat.php
// MONITORAMENTO DE CHAT DE PREGÕES
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$page_title = 'Monitorar Chat';

$aba_selecionada = $_GET['aba'] ?? 'todos';
$busca = $_GET['busca'] ?? '';
$pregao_selecionado = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Dados mockados de pregões monitorados
$pregoes_mock = [
    [
        'id' => 1,
        'edital' => 'PE/3/2026',
        'orgao' => 'Prefeitura Municipal de São Paulo',
        'portal' => 'PE Integrado',
        'data_hora' => '16/05/2026 09:00',
        'nao_lidos' => 5,
        'importante' => true,
        'arquivado' => false
    ],
    [
        'id' => 2,
        'edital' => 'PE/15/2026',
        'orgao' => 'Governo do Estado de Minas Gerais',
        'portal' => 'PE Integrado',
        'data_hora' => '15/05/2026 14:30',
        'nao_lidos' => 0,
        'importante' => true,
        'arquivado' => false
    ],
    [
        'id' => 3,
        'edital' => 'PE/8/2026',
        'orgao' => 'Secretaria Municipal de Educação - RJ',
        'portal' => 'PE Integrado',
        'data_hora' => '14/05/2026 10:15',
        'nao_lidos' => 12,
        'importante' => false,
        'arquivado' => false
    ],
    [
        'id' => 4,
        'edital' => 'TP/2/2026',
        'orgao' => 'Prefeitura Municipal de Curitiba',
        'portal' => 'Comprasnet',
        'data_hora' => '13/05/2026 16:45',
        'nao_lidos' => 0,
        'importante' => false,
        'arquivado' => true
    ],
    [
        'id' => 5,
        'edital' => 'PE/22/2026',
        'orgao' => 'Governo do Estado de São Paulo',
        'portal' => 'PE Integrado',
        'data_hora' => '16/05/2026 08:20',
        'nao_lidos' => 3,
        'importante' => false,
        'arquivado' => false
    ]
];

// Mensagens mockadas para o pregão selecionado
$mensagens_mock = [
    [
        'id' => 1,
        'tipo' => 'SESSAO - PROCESSO',
        'texto' => 'Sessão pública Iniciada. Pregão Eletrônico nº 3/2026. Processo administrativo: 12345.678901/2026-11. Objeto: Contratação de serviços de jardinagem.',
        'data_hora' => '16/05/2026 09:00:00',
        'is_alert' => false
    ],
    [
        'id' => 2,
        'tipo' => 'MENSAGEM',
        'texto' => 'Prezados participantes, informamos que documentos de habilitação serão solicitados na fase de assinatura do contrato. Favor atentarem para a documentação obrigatória.',
        'data_hora' => '16/05/2026 09:15:23',
        'is_alert' => false
    ],
    [
        'id' => 3,
        'tipo' => 'MENSAGEM - IMPORTANTE',
        'texto' => 'ATENÇÃO: foi publicada mensagem deIMINÊNCIA de recurso contra a decisão de classificação. Prazo para contrarrazões: 10 minutos.',
        'data_hora' => '16/05/2026 09:28:45',
        'is_alert' => true,
        'keyword' => 'IMINÊNCIA'
    ],
    [
        'id' => 4,
        'tipo' => 'MENSAGEM',
        'texto' => 'Empresa ABC Ltda solicitou esclarecimento sobre o item 5 - especificação técnica do material de jardinagem.',
        'data_hora' => '16/05/2026 09:35:10',
        'is_alert' => false
    ],
    [
        'id' => 5,
        'tipo' => 'SESSAO - LANCE',
        'texto' => 'Lances registrados: R$ 420.000,00 (Empresa XYZ), R$ 415.000,00 (Empresa ABC), R$ 410.000,00 (Empresa 123).',
        'data_hora' => '16/05/2026 09:42:33',
        'is_alert' => false
    ],
    [
        'id' => 6,
        'tipo' => 'MENSAGEM - IMPORTANTE',
        'texto' => 'Foi identificado novo documento na sessão: Ata de Julgamento anexada pelo pregoeiro.',
        'data_hora' => '16/05/2026 09:55:00',
        'is_alert' => true,
        'keyword' => 'documentos'
    ],
    [
        'id' => 7,
        'tipo' => 'SESSAO - ENCERRAMENTO',
        'texto' => 'Encerrada a fase de lances. Procedendo para análise de propostas.',
        'data_hora' => '16/05/2026 10:05:15',
        'is_alert' => false
    ]
];

$pregao_atual = null;
foreach ($pregoes_mock as $p) {
    if ($p['id'] === $pregao_selecionado) {
        $pregao_atual = $p;
        break;
    }
}
if (!$pregao_atual && count($pregoes_mock) > 0) {
    $pregao_atual = $pregoes_mock[0];
    $pregao_selecionado = $pregao_atual['id'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorar Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .chat-item:hover {
            background-color: #f3f4f6;
        }
        .chat-item.active {
            background-color: #dbeafe;
            border-left: 3px solid #022b6d;
        }
        
        .message-item {
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .message-item.alert {
            background-color: #fef3c7;
            border-left-color: #f59e0b;
        }
        .message-item:hover {
            background-color: #f9fafb;
        }
        
        .highlight-keyword {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: 700;
            padding: 0 4px;
            border-radius: 2px;
        }
        
        .badge-tab {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .badge-tab.active {
            background-color: #022b6d;
            color: white;
        }
        .badge-tab:not(.active) {
            background-color: #e5e7eb;
            color: #374151;
        }
        .badge-tab:not(.active):hover {
            background-color: #d1d5db;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .notif-badge {
            background-color: #dc2626;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }
    </style>
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto">
        <?php include 'header.php'; ?>
        
        <!-- Cabeçalho da Página -->
        <div class="bg-white p-4 rounded-lg shadow-lg mb-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-comments text-blue-900 mr-2"></i>Monitorar Chat
                    </h2>
                    <p class="text-gray-500 mt-1 text-sm">Acompanhamento em tempo real das sessões de pregão</p>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-secondary btn-sm" onclick="openConfigModal()">
                        <i class="fas fa-cog mr-1"></i> Configurações
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Layout em Duas Colunas -->
        <div class="flex flex-col lg:flex-row gap-4">
            
            <!-- Coluna Esquerda: Lista de Pregões -->
            <div class="lg:w-1/3">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <!-- Filtros da Lista -->
                    <div class="p-4 border-b border-gray-200">
                        <!-- Seletor Por mim / Por todos -->
                        <div class="flex gap-2 mb-3">
                            <button class="badge-tab active text-sm flex-1">
                                <i class="fas fa-user mr-1"></i> Por mim
                            </button>
                            <button class="badge-tab text-sm flex-1">
                                <i class="fas fa-users mr-1"></i> Por todos
                            </button>
                        </div>
                        
                        <!-- Campo de Busca -->
                        <div class="relative">
                            <input type="text" id="search-chat" placeholder="Buscar por edital ou Conlicitação..." 
                                class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Abas -->
                    <div class="flex border-b border-gray-200">
                        <a href="?aba=todos" class="flex-1 py-3 text-center text-sm font-medium border-b-2 <?php echo ($aba_selecionada === 'todos') ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            Todos
                        </a>
                        <a href="?aba=nao_lidos" class="flex-1 py-3 text-center text-sm font-medium border-b-2 <?php echo ($aba_selecionada === 'nao_lidos') ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            Não lidos
                            <?php 
                            $total_nao_lidos = array_sum(array_column($pregoes_mock, 'nao_lidos'));
                            if ($total_nao_lidos > 0): 
                            ?>
                                <span class="notif-badge"><?php echo $total_nao_lidos; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?aba=importantes" class="flex-1 py-3 text-center text-sm font-medium border-b-2 <?php echo ($aba_selecionada === 'importantes') ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            Importantes
                        </a>
                        <a href="?aba=arquivados" class="flex-1 py-3 text-center text-sm font-medium border-b-2 <?php echo ($aba_selecionada === 'arquivados') ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            Arquivados
                        </a>
                    </div>
                    
                    <!-- Lista de Pregões -->
                    <div class="max-h-[60vh] overflow-y-auto">
                        <?php foreach ($pregoes_mock as $pregao): 
                            $mostrar = true;
                            if ($aba_selecionada === 'nao_lidos' && $pregao['nao_lidos'] == 0) $mostrar = false;
                            if ($aba_selecionada === 'importantes' && !$pregao['importante']) $mostrar = false;
                            if ($aba_selecionada === 'arquivados' && !$pregao['arquivado']) $mostrar = false;
                            
                            if (!$mostrar) continue;
                        ?>
                            <a href="?id=<?php echo $pregao['id']; ?>&aba=<?php echo $aba_selecionada; ?>" 
                                class="chat-item block p-4 border-b border-gray-100 <?php echo ($pregao_selecionado === $pregao['id']) ? 'active' : ''; ?>">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-gray-800"><?php echo $pregao['edital']; ?></span>
                                    <?php if ($pregao['importante']): ?>
                                        <i class="fas fa-star text-yellow-500"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-600 mb-1 truncate">
                                    <?php echo htmlspecialchars($pregao['orgao']); ?>
                                </div>
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <span><i class="fas fa-globe mr-1"></i><?php echo $pregao['portal']; ?></span>
                                    <span><?php echo $pregao['data_hora']; ?></span>
                                </div>
                                <?php if ($pregao['nao_lidos'] > 0): ?>
                                    <div class="mt-2">
                                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full">
                                            <?php echo $pregao['nao_lidos']; ?> nova(s) mensagem(ns)
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pregoes_mock) || ($aba_selecionada !== 'todos' && count($pregoes_mock) === 0)): ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-3 text-gray-300"></i>
                                <p>Nenhum pregão encontrado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Coluna Direita: Mensagens do Chat -->
            <div class="lg:w-2/3">
                <?php if ($pregao_atual): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <!-- Cabeçalho do Chat -->
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                            <div>
                                <h3 class="font-bold text-lg text-gray-800">
                                    <?php echo $pregao_atual['edital']; ?>
                                </h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pregao_atual['orgao']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-calendar mr-1"></i> <?php echo $pregao_atual['data_hora']; ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-globe mr-1"></i> <?php echo $pregao_atual['portal']; ?>
                                </p>
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <select class="w-auto text-sm border rounded px-2 py-1">
                                    <option>Todos os lotes</option>
                                    <option>Lote 001</option>
                                    <option>Lote 002</option>
                                </select>
                                <a href="#" class="text-xs text-blue-600 hover:underline">
                                    <i class="fas fa-check-square mr-1"></i> Selecionar lotes de interesse
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barra de Ações -->
                    <div class="px-4 py-2 border-b border-gray-200 flex items-center gap-3">
                        <button class="text-gray-500 hover:text-blue-600" title="IA - Análise inteligente">
                            <i class="fas fa-robot"></i>
                        </button>
                        <button class="text-gray-500 hover:text-yellow-600" title="Marcar como importante">
                            <i class="far fa-star"></i>
                        </button>
                        <button class="text-gray-500 hover:text-blue-600" title="Atualizar mensagens" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button class="text-gray-500 hover:text-blue-600" title="Pesquisar na conversa">
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <div class="ml-auto relative">
                            <button class="text-gray-500 hover:text-gray-700" id="options-menu-btn">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <!-- Dropdown de Opções -->
                            <div id="options-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                <div class="py-1">
                                    <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="alert('Marcar como importante')">
                                        <i class="fas fa-star mr-2 text-yellow-500"></i> Marcar como importante
                                    </button>
                                    <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="alert('Arquivar licença')">
                                        <i class="fas fa-archive mr-2 text-gray-500"></i> Arquivar licença
                                    </button>
                                    <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="alert('Informações da licença')">
                                        <i class="fas fa-info-circle mr-2 text-blue-500"></i> Informações da licença
                                    </button>
                                    <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="alert('Anotações')">
                                        <i class="fas fa-sticky-note mr-2 text-yellow-500"></i> Anotações
                                    </button>
                                    <hr class="my-1">
                                    <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-external-link-alt mr-2 text-green-500"></i> Acessar local da disputa
                                    </button>
                                    <button class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                        <i class="fas fa-ban mr-2"></i> Desativar monitoramento
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Mensagens -->
                    <div class="max-h-[50vh] overflow-y-auto p-4 space-y-3" id="chat-messages">
                        <?php foreach ($mensagens_mock as $msg): ?>
                        <div class="message-item bg-white p-3 rounded-lg border border-gray-200 <?php echo $msg['is_alert'] ? 'alert' : ''; ?>">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-gray-600 uppercase bg-gray-100 px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($msg['tipo']); ?>
                                </span>
                                <span class="text-xs text-gray-400">
                                    <?php echo $msg['data_hora']; ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-700">
                                <?php 
                                $texto = htmlspecialchars($msg['texto']);
                                if (!empty($msg['keyword'])) {
                                    $palavra = preg_quote($msg['keyword'], '/');
                                    $texto = preg_replace('/(' . $palavra . ')/i', '<span class="highlight-keyword">$1</span>', $texto);
                                }
                                // Destaque para "documentos"
                                $texto = preg_replace('/(documentos)/i', '<span class="highlight-keyword">$1</span>', $texto);
                                echo $texto;
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Rodapé com status -->
                    <div class="p-3 border-t border-gray-200 bg-gray-50 text-xs text-gray-500 flex justify-between">
                        <span>
                            <i class="fas fa-circle text-green-500 mr-1"></i> Conectado ao chat
                        </span>
                        <span>
                            Última atualização: <?php echo date('d/m/Y H:i:s'); ?>
                        </span>
                    </div>
                </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        <i class="fas fa-comments text-4xl mb-4 text-gray-300"></i>
                        <p class="text-lg">Selecione um pregão para visualizar as mensagens</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal de Configurações -->
    <div id="config-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="font-bold text-lg text-gray-800">
                    <i class="fas fa-cog mr-2"></i>Configurações de Monitoramento
                </h3>
                <button onclick="closeConfigModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            
            <div class="p-4 overflow-y-auto" style="max-height: calc(90vh - 130px);">
                <!-- Palavras-Chave -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-key mr-2"></i>Palavras-Chave Monitoradas
                    </h4>
                    <div class="flex gap-2 mb-3">
                        <input type="text" id="new-keyword" placeholder="Adicionar palavra-chave..." 
                            class="flex-grow border rounded px-3 py-2">
                        <button class="btn btn-primary" onclick="addKeyword()">
                            <i class="fas fa-plus mr-1"></i> Adicionar
                        </button>
                    </div>
                    <div id="keywords-list" class="flex flex-wrap gap-2">
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                            iminência <button class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeKeyword(this)">&times;</button>
                        </span>
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                            recurso <button class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeKeyword(this)">&times;</button>
                        </span>
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                            documentos <button class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeKeyword(this)">&times;</button>
                        </span>
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                            decisão <button class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeKeyword(this)">&times;</button>
                        </span>
                    </div>
                </div>
                
                <!-- Notificações -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-bell mr-2"></i>Notificações
                    </h4>
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="notif-email" class="w-4 h-4 text-blue-600 rounded">
                        <label for="notif-email" class="text-sm text-gray-700">Email</label>
                    </div>
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="notif-sound" class="w-4 h-4 text-blue-600 rounded">
                        <label for="notif-sound" class="text-sm text-gray-700">Aviso sonoro</label>
                    </div>
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="notif-push" class="w-4 h-4 text-blue-600 rounded">
                        <label for="notif-push" class="text-sm text-gray-700">Push notification</label>
                    </div>
                </div>
                
                <!-- Tipos de Mensagem -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-envelope mr-2"></i>Tipos de Mensagem
                    </h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="tipo_msg" id="tipo_todas" value="todas" class="w-4 h-4 text-blue-600" checked>
                            <label for="tipo_todas" class="text-sm text-gray-700">Todas as mensagens do chat</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="radio" name="tipo_msg" id="tipo_palavra" value="palavra" class="w-4 h-4 text-blue-600">
                            <label for="tipo_palavra" class="text-sm text-gray-700">Somente mensagens com palavra-chave</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-4 border-t border-gray-200 flex justify-end gap-2">
                <button class="btn btn-secondary" onclick="closeConfigModal()">Cancelar</button>
                <button class="btn btn-primary" onclick="saveConfig()">
                    <i class="fas fa-save mr-1"></i> Salvar Configurações
                </button>
            </div>
        </div>
    </div>
    
    <script src="js/script.js?v=2.35"></script>
    <script>
        // Menu de Opções
        const optionsBtn = document.getElementById('options-menu-btn');
        const optionsDropdown = document.getElementById('options-dropdown');
        
        if (optionsBtn) {
            optionsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                optionsDropdown.classList.toggle('hidden');
            });
        }
        
        document.addEventListener('click', () => {
            if (optionsDropdown && !optionsDropdown.classList.contains('hidden')) {
                optionsDropdown.classList.add('hidden');
            }
        });
        
        // Modal de Configurações
        function openConfigModal() {
            document.getElementById('config-modal').classList.remove('hidden');
        }
        
        function closeConfigModal() {
            document.getElementById('config-modal').classList.add('hidden');
        }
        
        function addKeyword() {
            const input = document.getElementById('new-keyword');
            const keyword = input.value.trim();
            if (keyword) {
                const list = document.getElementById('keywords-list');
                const span = document.createElement('span');
                span.className = 'bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm';
                span.innerHTML = `${keyword} <button class="ml-1 text-blue-500 hover:text-blue-700" onclick="removeKeyword(this)">&times;</button>`;
                list.appendChild(span);
                input.value = '';
            }
        }
        
        function removeKeyword(btn) {
            btn.parentElement.remove();
        }
        
        function saveConfig() {
            alert('Configurações salvas com sucesso!');
            closeConfigModal();
        }
        
        // =====================================================
        // FUTURA INTEGRAÇÃO COM MONITOR_PE.PHP E LOGS REAIS
        // =====================================================
        /*
        Para conectar com os dados reais do monitoramento, substitua os arrays mockados:
        
        // Carregar dados do monitor_logs.json
        $logFile = 'monitor_logs.json';
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        
        // Agrupar mensagens por edital/processo
        // O monitor_pe.php já gera logs estruturados com:
        // - timestamp, remetente, data_mensagem, texto, is_alert, keyword, color
        
        // Para carregar lista de pregões monitorados:
        $historyFile = 'monitor_history.json';
        $pregoes = [];
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true);
            // Transformar em lista de pregões únicos
            // Cada entrada do history é uma mensagem, agrupe por processo/edital
        }
        
        // Para polling automático (atualização em tempo real):
        // Use setInterval no JavaScript para fazer fetch de novos dados
        // Exemplo: a cada 30 segundos chamar uma API que retorna novas mensagens
        
        setInterval(() => {
            fetch('api_modules.php?module=chat&action=get_messages&pregao_id=<?php echo $pregao_selecionado; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.new_messages) {
                        // Atualizar a lista de mensagens na tela
                        // Adicionar as novas mensagens no topo
                    }
                });
        }, 30000);
        */
    </script>
</body>
</html>