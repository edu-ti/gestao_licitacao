<?php
// ==============================================
// ARQUIVO: boletim_licitacoes.php
// BOLETIM DIÁRIO DE LICITAÇÕES
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$page_title = 'Boletim de Licitações';

$situacao_selecionada = $_GET['situacao'] ?? '';
$estado_selecionado = $_GET['estado'] ?? '';
$status_selecionado = $_GET['status'] ?? '';
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$licitacoes_mock = [
    [
        'id' => 1,
        'numero' => '001',
        'status_badge' => 'URGENTE',
        'status_badge_cor' => 'red',
        'objeto' => 'Contratação de empresa para fornecimento de materiais de expediente e papelaria para atender as necessidades da Prefeitura Municipal.',
        'data_publicacao' => '16/05/2026',
        'data_abertura' => '20/05/2026',
        'orgao' => 'Prefeitura Municipal de São Paulo',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'edital' => '2026/001',
        'valor_estimado' => 'R$ 150.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'conlicitacao' => 'CL-2026-0001',
        'atualizado_em' => '16/05/2026 09:05'
    ],
    [
        'id' => 2,
        'numero' => '002',
        'status_badge' => 'NOVA',
        'status_badge_cor' => 'green',
        'objeto' => 'Prestação de serviços de manutenção preventiva e corretiva em equipamentos de ar condicionado e refrigeração.',
        'data_publicacao' => '15/05/2026',
        'data_abertura' => '22/05/2026',
        'orgao' => 'Secretaria Municipal de Educação',
        'cidade' => 'Rio de Janeiro',
        'estado' => 'RJ',
        'edital' => '2026/045',
        'valor_estimado' => 'R$ 85.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'conlicitacao' => 'CL-2026-0002',
        'atualizado_em' => '15/05/2026 14:30'
    ],
    [
        'id' => 3,
        'numero' => '003',
        'status_badge' => 'EM ANÁLISE',
        'status_badge_cor' => 'blue',
        'objeto' => 'Aquisição de equipamentos de informática incluindo notebooks, desktops e periféricos para modernização dos setores administrativos.',
        'data_publicacao' => '14/05/2026',
        'data_abertura' => '25/05/2026',
        'orgao' => 'Governo do Estado de Minas Gerais',
        'cidade' => 'Belo Horizonte',
        'estado' => 'MG',
        'edital' => '2026/0123',
        'valor_estimado' => 'R$ 520.000,00',
        'modalidade' => 'Tomada de Preços',
        'conlicitacao' => 'CL-2026-0003',
        'atualizado_em' => '14/05/2026 11:20'
    ]
];

$situacoes = ['Todos', 'Aberta', 'Fechada', 'Em Andamento', 'Encerrada'];
$estados = ['Todos', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
$status_opcoes = ['Todos', 'Urgente', 'Nova', 'Em Análise', 'Destaque', 'Vencida'];

$total_licitacoes = count($licitacoes_mock);
$total_acompanhamentos = 12;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletim de Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-urgente { background-color: #dc2626; color: white; }
        .badge-nova { background-color: #16a34a; color: white; }
        .badge-analise { background-color: #3b82f6; color: white; }
        .badge-destaque { background-color: #f59e0b; color: white; }
        
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
    </style>
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto">
        <?php include 'header.php'; ?>
        
        <!-- Título do Boletim -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        Boletim <?php echo date('d'); ?> de <?php echo ucfirst(date('F')); ?>, <?php echo date('H:i'); ?> - Edição nº 4868
                    </h2>
                    <p class="text-gray-500 mt-1">Filtro: Licitações e Acompanhamentos</p>
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
        
        <!-- Abas de Totalizadores -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex gap-4">
                <button class="tab-btn active bg-[#022b6d] text-white">
                    <i class="fas fa-gavel mr-2"></i> Licitações <span class="bg-white text-[#022b6d] px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $total_licitacoes; ?></span>
                </button>
                <button class="tab-btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-eye mr-2"></i> Acompanhamentos <span class="bg-gray-400 text-white px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $total_acompanhamentos; ?></span>
                </button>
            </div>
        </div>
        
        <!-- Filtros Rápidos -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form method="GET" action="boletim_licitacoes.php" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                    <select name="situacao" class="w-full">
                        <?php foreach ($situacoes as $sit): ?>
                            <option value="<?php echo $sit; ?>" <?php echo ($situacao_selecionada === $sit) ? 'selected' : ''; ?>><?php echo $sit; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full">
                        <?php foreach ($estados as $est): ?>
                            <option value="<?php echo $est; ?>" <?php echo ($estado_selecionado === $est) ? 'selected' : ''; ?>><?php echo $est; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full">
                        <?php foreach ($status_opcoes as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($status_selecionado === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="boletim_licitacoes.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Lista de Cards de Licitações -->
        <div class="space-y-4 mb-6">
            <?php foreach ($licitacoes_mock as $licitacao): ?>
            <div class="licitacao-card bg-white p-5 rounded-lg shadow border-l-4 <?php echo $licitacao['status_badge_cor'] === 'red' ? 'border-l-red-500' : ($licitacao['status_badge_cor'] === 'green' ? 'border-l-green-500' : 'border-l-blue-500'); ?>">
                <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                    <!-- Número do Item -->
                    <div class="flex-shrink-0">
                        <span class="text-lg font-bold text-gray-400">#<?php echo $licitacao['numero']; ?></span>
                    </div>
                    
                    <!-- Conteúdo Principal -->
                    <div class="flex-grow">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <!-- Badge de Status -->
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase badge-<?php echo $licitacao['status_badge_cor']; ?>">
                                <?php echo htmlspecialchars($licitacao['status_badge']); ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-calendar mr-1"></i> Publicação: <?php echo $licitacao['data_publicacao']; ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i> Abertura: <?php echo $licitacao['data_abertura']; ?>
                            </span>
                        </div>
                        
                        <!-- Objeto -->
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($licitacao['objeto']); ?>
                        </h3>
                        
                        <!-- Informações do Órgão -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-3">
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
                                <i class="fas fa-money-bill-wave mr-1 text-gray-400"></i>
                                <span class="font-medium">Valor:</span> <?php echo $licitacao['valor_estimado']; ?>
                            </div>
                        </div>
                        
                        <!-- Modalidade -->
                        <div class="text-sm text-gray-500 mb-3">
                            <span class="bg-gray-100 px-2 py-1 rounded text-xs">
                                <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($licitacao['modalidade']); ?>
                            </span>
                        </div>
                        
                        <!-- Link Ver Mais -->
                        <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center">
                            <i class="fas fa-external-link-alt mr-1"></i> Ver mais informações da licitação
                        </a>
                        
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
                    
                    <!-- Ações do Card -->
                    <div class="flex-shrink-0 flex flex-col gap-2 lg:min-w-[160px]">
                        <button class="btn btn-detalhe btn-sm w-full">
                            <i class="fas fa-list"></i> Ver itens
                        </button>
                        <button class="btn btn-secondary btn-sm w-full">
                            <i class="fas fa-download"></i> Baixar edital
                        </button>
                        <button class="btn btn-primary btn-sm w-full">
                            <i class="fas fa-cog"></i> Gerenciar
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginação -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-500">
                    Mostrando 1 a <?php echo count($licitacoes_mock); ?> de <?php echo $total_licitacoes; ?> resultados
                </div>
                <nav class="flex items-center gap-1">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>" class="pagination-link <?php echo ($pagina_atual <= 1) ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    <a href="?page=1" class="pagination-link <?php echo ($pagina_atual == 1) ? 'active' : ''; ?>">1</a>
                    <a href="?page=2" class="pagination-link <?php echo ($pagina_atual == 2) ? 'active' : ''; ?>">2</a>
                    <a href="?page=3" class="pagination-link <?php echo ($pagina_atual == 3) ? 'active' : ''; ?>">3</a>
                    <span class="px-2 text-gray-400">...</span>
                    <a href="?page=10" class="pagination-link">10</a>
                    <a href="?page=<?php echo $pagina_atual + 1; ?>" class="pagination-link">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Link Voltar -->
        <div class="text-center mb-8">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Voltar para o Dashboard
            </a>
        </div>
    </div>
    
    <script src="js/script.js?v=2.35"></script>
</body>
</html>