<?php
// ==============================================
// ARQUIVO: gerenciar_licitacoes.php
// PAINEL DE GESTÃO DE LICITAÇÕES
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$page_title = 'Gerenciar Licitações';

$por_usuario = $_GET['filtro'] ?? 'todos';
$ordenar_por = $_GET['ordenar'] ?? 'prazo';
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$kpis = [
    'calendario' => 3,
    'favoritadas' => 35,
    'gerenciadas' => 384,
    'tarefas' => 0,
    'andamentos' => 580,
    'finalizadas' => 0
];

$licitacoes_mock = [
    [
        'id' => 1,
        'numero' => '001',
        'favorito' => true,
        'status_badge' => 'REABERTURA',
        'status_cor' => 'purple',
        'objeto' => 'Contratação de empresa para prestação de serviços de jardinagem e paisagismo em áreas públicas do município.',
        'data_publicacao' => '14/05/2026',
        'data_abertura' => '20/05/2026',
        'hora_sessao' => '09:00',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'orgao' => 'Prefeitura Municipal de São Paulo',
        'edital' => 'SMT-2026/015',
        'uasg' => '123456',
        'valor_estimado' => 'R$ 450.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'link_disputa' => '#',
        'conlicitacao' => 'CL-2026-00042',
        'chat_ativo' => true,
        'atualizado_em' => '16/05/2026 08:45'
    ],
    [
        'id' => 2,
        'numero' => '002',
        'favorito' => true,
        'status_badge' => 'RECEBENDO PROPOSTAS',
        'status_cor' => 'blue',
        'objeto' => 'Aquisição de mobiliário escolar (mesas, cadeiras e armários) para equiparação das unidades educacionais.',
        'data_publicacao' => '15/05/2026',
        'data_abertura' => '22/05/2026',
        'hora_sessao' => '14:00',
        'cidade' => 'Rio de Janeiro',
        'estado' => 'RJ',
        'orgao' => 'Secretaria Municipal de Educação',
        'edital' => 'SME-2026-0008',
        'uasg' => '234567',
        'valor_estimado' => 'R$ 1.200.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'link_disputa' => '#',
        'conlicitacao' => 'CL-2026-00055',
        'chat_ativo' => true,
        'atualizado_em' => '16/05/2026 09:10'
    ],
    [
        'id' => 3,
        'numero' => '003',
        'favorito' => false,
        'status_badge' => 'NOVA',
        'status_cor' => 'green',
        'objeto' => 'Elaboração de projeto executivo de engenharia para construção de ponte sobre o rio local.',
        'data_publicacao' => '16/05/2026',
        'data_abertura' => '30/05/2026',
        'hora_sessao' => '10:00',
        'cidade' => 'Curitiba',
        'estado' => 'PR',
        'orgao' => 'Prefeitura Municipal de Curitiba',
        'edital' => 'SMOP-2026/045',
        'uasg' => '345678',
        'valor_estimado' => 'R$ 3.800.000,00',
        'modalidade' => 'Concorrência',
        'link_disputa' => '#',
        'conlicitacao' => 'CL-2026-00067',
        'chat_ativo' => false,
        'atualizado_em' => '16/05/2026 10:00'
    ],
    [
        'id' => 4,
        'numero' => '004',
        'favorito' => true,
        'status_badge' => 'PROCESSO FINALIZADO',
        'status_cor' => 'gray',
        'objeto' => 'Fornecimento de materiais de construção para manutenção de próprios municipais.',
        'data_publicacao' => '10/05/2026',
        'data_abertura' => '18/05/2026',
        'hora_sessao' => '11:00',
        'cidade' => 'Belo Horizonte',
        'estado' => 'MG',
        'orgao' => 'Governo do Estado de Minas Gerais',
        'edital' => 'SEPLAG-012/2026',
        'uasg' => '456789',
        'valor_estimado' => 'R$ 680.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'link_disputa' => '#',
        'conlicitacao' => 'CL-2026-00038',
        'chat_ativo' => false,
        'atualizado_em' => '15/05/2026 16:30'
    ],
    [
        'id' => 5,
        'numero' => '005',
        'favorito' => false,
        'status_badge' => 'EM ANÁLISE',
        'status_cor' => 'orange',
        'objeto' => 'Contratação de serviços de vigilância e monitoramento eletrônico para prédios públicos.',
        'data_publicacao' => '13/05/2026',
        'data_abertura' => '25/05/2026',
        'hora_sessao' => '15:00',
        'cidade' => 'Fortaleza',
        'estado' => 'CE',
        'orgao' => 'Prefeitura Municipal de Fortaleza',
        'edital' => 'SGP-2026/003',
        'uasg' => '567890',
        'valor_estimado' => 'R$ 920.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'link_disputa' => '#',
        'conlicitacao' => 'CL-2026-00051',
        'chat_ativo' => true,
        'atualizado_em' => '14/05/2026 11:20'
    ]
];

$opcoes_ordenar = [
    'prazo' => 'Prazo',
    'atualizada' => 'Atualizada em',
    'favoritado' => 'Favoritado em'
];

$total_resultados = count($licitacoes_mock);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-reabertura { background-color: #8b5cf6; color: white; }
        .badge-recebendo { background-color: #3b82f6; color: white; }
        .badge-nova { background-color: #16a34a; color: white; }
        .badge-finalizado { background-color: #6b7280; color: white; }
        .badge-analise { background-color: #f59e0b; color: white; }
        
        .kpi-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .kpi-circle::before {
            content: '';
            position: absolute;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid rgba(59, 130, 246, 0.2);
            top: 5px;
            left: 5px;
        }
        
        .licitacao-card {
            transition: all 0.2s ease;
        }
        .licitacao-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .annotation-area {
            border-top: 1px dashed #e5e7eb;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .pagination-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            color: #3b82f6;
            border-radius: 0.375rem;
            text-decoration: none;
        }
        .pagination-link:hover {
            background-color: #f3f4f6;
        }
        .pagination-link.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .filtro-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .filtro-btn.active {
            background-color: #022b6d;
            color: white;
        }
        .filtro-btn:not(.active) {
            background-color: #e5e7eb;
            color: #374151;
        }
        .filtro-btn:not(.active):hover {
            background-color: #d1d5db;
        }
    </style>
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto">
        <?php include 'header.php'; ?>
        
        <!-- Cabeçalho da Página -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-tasks text-blue-900 mr-2"></i>Gerenciar Licitações
                    </h2>
                    <p class="text-gray-500 mt-1">Acompanhamento operacional de suas licitações</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar
                </a>
            </div>
            
            <!-- Seletor Por mim / Por todos -->
            <div class="flex gap-3 mt-4 pt-4 border-t border-gray-100">
                <a href="?filtro=por_mim" class="filtro-btn <?php echo ($por_usuario === 'por_mim') ? 'active' : ''; ?>">
                    <i class="fas fa-user mr-1"></i> Por mim
                </a>
                <a href="?filtro=todos" class="filtro-btn <?php echo ($por_usuario === 'todos') ? 'active' : ''; ?>">
                    <i class="fas fa-users mr-1"></i> Por todos
                </a>
            </div>
        </div>
        
        <!-- KPIs -->
        <div class="bg-white p-5 rounded-lg shadow mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="font-bold text-gray-700">
                    <i class="fas fa-chart-line mr-2"></i>Indicadores
                </h3>
                <button class="btn btn-secondary btn-sm">
                    <i class="fas fa-cog mr-1"></i> Configurações
                </button>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                <div class="text-center">
                    <div class="kpi-circle bg-blue-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-blue-700"><?php echo $kpis['calendario']; ?></span>
                        <i class="fas fa-calendar-alt text-blue-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Calendário</span>
                </div>
                <div class="text-center">
                    <div class="kpi-circle bg-red-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-red-600"><?php echo $kpis['favoritadas']; ?></span>
                        <i class="fas fa-heart text-red-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Favoritadas</span>
                </div>
                <div class="text-center">
                    <div class="kpi-circle bg-green-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-green-600"><?php echo $kpis['gerenciadas']; ?></span>
                        <i class="fas fa-briefcase text-green-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Gerenciadas</span>
                </div>
                <div class="text-center">
                    <div class="kpi-circle bg-yellow-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-yellow-600"><?php echo $kpis['tarefas']; ?></span>
                        <i class="fas fa-tasks text-yellow-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Tarefas</span>
                </div>
                <div class="text-center">
                    <div class="kpi-circle bg-purple-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-purple-600"><?php echo $kpis['andamentos']; ?></span>
                        <i class="fas fa-spinner text-purple-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Andamentos</span>
                </div>
                <div class="text-center">
                    <div class="kpi-circle bg-gray-50 mx-auto mb-2">
                        <span class="text-2xl font-bold text-gray-600"><?php echo $kpis['finalizadas']; ?></span>
                        <i class="fas fa-check-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-600 font-medium">Finalizadas</span>
                </div>
            </div>
        </div>
        
        <!-- Filtros e Ações -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <!-- Ordenação -->
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600 font-medium">Ordenar por:</span>
                    <select name="ordenar" class="w-auto" onchange="window.location.href='?ordenar='+this.value+'&filtro=<?php echo $por_usuario; ?>'">
                        <?php foreach ($opcoes_ordenar as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($ordenar_por === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Ações em Lote -->
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-secondary btn-sm">
                        <i class="fas fa-share-alt"></i> Compartilhar
                    </button>
                    <button class="btn btn-secondary btn-sm">
                        <i class="fas fa-file-excel"></i> Gerar xlsx
                    </button>
                    <button class="btn btn-secondary btn-sm">
                        <i class="fas fa-file-word"></i> Gerar docx
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Lista de Cards -->
        <div class="space-y-4 mb-6">
            <?php foreach ($licitacoes_mock as $licitacao): ?>
            <div class="licitacao-card bg-white p-5 rounded-lg shadow border-l-4 
                <?php echo $licitacao['status_cor'] === 'purple' ? 'border-l-purple-500' : 
                    ($licitacao['status_cor'] === 'blue' ? 'border-l-blue-500' : 
                    ($licitacao['status_cor'] === 'green' ? 'border-l-green-500' : 
                    ($licitacao['status_cor'] === 'gray' ? 'border-l-gray-500' : 'border-l-orange-500'))); ?>">
                
                <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                    <!-- Número e Favorito -->
                    <div class="flex-shrink-0 flex flex-col items-center gap-2">
                        <span class="text-lg font-bold text-gray-400">#<?php echo $licitacao['numero']; ?></span>
                        <button class="text-<?php echo $licitacao['favorito'] ? 'red' : 'gray'; ?> hover:text-red-600 transition-colors" title="Favorito">
                            <i class="fas fa-heart fa-lg"></i>
                        </button>
                    </div>
                    
                    <!-- Conteúdo Principal -->
                    <div class="flex-grow">
                        <!-- Badge de Status -->
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase badge-<?php echo $licitacao['status_cor']; ?>">
                                <?php echo htmlspecialchars($licitacao['status_badge']); ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-calendar mr-1"></i> Pub.: <?php echo $licitacao['data_publicacao']; ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i> Abertura: <?php echo $licitacao['data_abertura']; ?> às <?php echo $licitacao['hora_sessao']; ?>
                            </span>
                        </div>
                        
                        <!-- Objeto -->
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($licitacao['objeto']); ?>
                        </h3>
                        
                        <!-- Informações -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 text-sm mb-3">
                            <div class="text-gray-600">
                                <i class="fas fa-building mr-1 text-gray-400"></i>
                                <span class="font-medium">Órgão:</span> <?php echo htmlspecialchars($licitacao['orgao']); ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                <span class="font-medium">Cidade/UF:</span> <?php echo htmlspecialchars($licitacao['cidade']); ?>/<?php echo $licitacao['estado']; ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-file-alt mr-1 text-gray-400"></i>
                                <span class="font-medium">Edital:</span> <?php echo htmlspecialchars($licitacao['edital']); ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-external-link-alt mr-1 text-gray-400"></i>
                                <a href="<?php echo $licitacao['link_disputa']; ?>" class="text-blue-600 hover:underline">Acesso à disputa</a>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-money-bill-wave mr-1 text-gray-400"></i>
                                <span class="font-medium">Valor:</span> <?php echo $licitacao['valor_estimado']; ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-barcode mr-1 text-gray-400"></i>
                                <span class="font-medium">UASG:</span> <?php echo $licitacao['uasg']; ?>
                            </div>
                        </div>
                        
                        <!-- Link Ver Mais -->
                        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-3">
                            <i class="fas fa-external-link-alt mr-1"></i> Ver mais informações da licitação
                        </a>
                        
                        <!-- Ações do Card -->
                        <div class="flex flex-wrap gap-2 mb-3">
                            <button class="btn btn-detalhe btn-sm">
                                <i class="fas fa-list"></i> Ver itens
                            </button>
                            <button class="btn btn-secondary btn-sm">
                                <i class="fas fa-download"></i> Baixar edital
                            </button>
                            <button class="btn btn-secondary btn-sm" title="Resumo do edital">
                                <i class="fas fa-file-alt"></i> Resumo
                            </button>
                            <button class="btn btn-secondary btn-sm" title="Pergunte ao edital">
                                <i class="fas fa-question-circle"></i> Perguntar
                            </button>
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-cog"></i> Gerenciar
                            </button>
                            <button class="btn btn-sm <?php echo $licitacao['chat_ativo'] ? 'btn-success' : 'btn-secondary'; ?>" title="<?php echo $licitacao['chat_ativo'] ? 'Desativar' : 'Ativar'; ?> monitoramento de chat">
                                <i class="fas fa-comments"></i> <?php echo $licitacao['chat_ativo'] ? 'Chat Ativo' : 'Monitorar Chat'; ?>
                            </button>
                            <button class="btn btn-secondary btn-sm" title="Ver arquivos">
                                <i class="fas fa-folder-open"></i> Arquivos
                            </button>
                        </div>
                        
                        <!-- Área de Anotações -->
                        <div class="annotation-area">
                            <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center mb-2">
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-hashtag mr-1"></i> Conlicitação: <span class="font-mono text-gray-700"><?php echo $licitacao['conlicitacao']; ?></span>
                                </div>
                                <div class="text-xs text-gray-400 sm:ml-auto">
                                    <i class="fas fa-sync-alt mr-1"></i> Atualizado: <?php echo $licitacao['atualizado_em']; ?>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <input type="text" placeholder="Adicionar anotação..." class="flex-grow text-sm border rounded px-3 py-2" style="height: 36px;">
                                <button class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginação -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-500">
                    Mostrando 1 a <?php echo count($licitacoes_mock); ?> de <?php echo $total_resultados; ?> resultados
                </div>
                <nav class="flex items-center gap-1">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>" class="pagination-link <?php echo ($pagina_atual <= 1) ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    <a href="?page=1" class="pagination-link <?php echo ($pagina_atual == 1) ? 'active' : ''; ?>">1</a>
                    <a href="?page=2" class="pagination-link <?php echo ($pagina_atual == 2) ? 'active' : ''; ?>">2</a>
                    <span class="px-2 text-gray-400">...</span>
                    <a href="?page=20" class="pagination-link">20</a>
                    <a href="?page=<?php echo $pagina_atual + 1; ?>" class="pagination-link">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Link Voltar ao Dashboard -->
        <div class="text-center mb-8">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home mr-2"></i> Voltar para o Dashboard
            </a>
        </div>
    </div>
    
    <script src="js/script.js?v=2.35"></script>
    <script>
        // =====================================================
        // FUTURA INTEGRAÇÃO COM BANCO DE DADOS
        // =====================================================
        /*
        Para conectar com o banco de dados, substitua o array $licitacoes_mock 
        e os KPIs por queries como estas:
        
        // KPIs
        $pdo = (new Database())->connect();
        
        $kpis['favoritadas'] = $pdo->query("SELECT COUNT(*) FROM pregoes WHERE favorito = 1 AND user_id = " . $_SESSION['user_id'])->fetchColumn();
        $kpis['gerenciadas'] = $pdo->query("SELECT COUNT(*) FROM pregoes WHERE user_id = " . $_SESSION['user_id'])->fetchColumn();
        $kpis['andamentos'] = $pdo->query("SELECT COUNT(*) FROM pregoes WHERE status IN ('Em análise', 'Recebendo propostas')")->fetchColumn();
        
        // Lista de licitações
        $where = "WHERE 1=1";
        if ($por_usuario === 'por_mim') {
            $where .= " AND p.user_id = " . $_SESSION['user_id'];
        }
        
        $sql = "SELECT p.*, u.nome as orgao_nome 
                FROM pregoes p 
                LEFT JOIN orgaos u ON p.uasg = u.uasg
                $where";
        
        if ($ordenar_por === 'prazo') {
            $sql .= " ORDER BY p.data_sessao ASC";
        } elseif ($ordenar_por === 'atualizada') {
            $sql .= " ORDER BY p.updated_at DESC";
        } else {
            $sql .= " ORDER BY p.favorito_at DESC";
        }
        
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $licitacoes_mock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
    </script>
</body>
</html>