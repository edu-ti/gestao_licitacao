<?php
// ==============================================
// ARQUIVO: encontrar_licitacoes.php
// BUSCA AVANÇADA DE LICITAÇÕES
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$page_title = 'Encontrar Licitações';

$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$filtros = [
    'objeto' => $_GET['objeto'] ?? '',
    'busca_exata' => $_GET['busca_exata'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'cidade' => $_GET['cidade'] ?? '',
    'numero_edital' => $_GET['numero_edital'] ?? '',
    'modalidade' => $_GET['modalidade'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'conlicitacao' => $_GET['conlicitacao'] ?? '',
    'codigo_orgao' => $_GET['codigo_orgao'] ?? '',
    'esfera' => $_GET['esfera'] ?? '',
    'numero_processo' => $_GET['numero_processo'] ?? '',
    'situacao' => $_GET['situacao'] ?? '',
    'orgao' => $_GET['orgao'] ?? '',
    'itens' => $_GET['itens'] ?? '',
    'observacao' => $_GET['observacao'] ?? '',
    'concorrencia' => $_GET['concorrencia'] ?? '',
    'atividades' => $_GET['atividades'] ?? '',
    'salvar_pesquisa' => isset($_GET['salvar_pesquisa']),
    'mostrar' => $_GET['mostrar'] ?? '20'
];

$estados = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
$modalidades = ['', 'Pregão Eletrônico', 'Pregão Presencial', 'Tomada de Preços', 'Concorrência', 'Convite', 'Dispensa de Licitação', 'Inexigibilidade', 'Chamamento Público'];
$esferas = ['', 'Federal', 'Estadual', 'Municipal'];
$situacoes = ['', 'Aberta', 'Fechada', 'Em Andamento', 'Encerrada', 'Suspensa'];
$concorrencias = ['', 'Nacional', 'Internacional'];
$atividades_opcoes = ['', 'Construção', 'Serviços', 'Fornecimento', 'Locação', 'Obras', 'Serviços de Engenharia', 'Tecnologia da Informação', 'Saúde', 'Educação'];

$licitacoes_mock = [
    [
        'id' => 1,
        'numero' => '001',
        'favorito' => false,
        'visualizacoes' => 45,
        'status_badge' => 'NOVA',
        'status_cor' => 'green',
        'objeto' => 'Contratação de empresa especializada para prestação de serviços de limpeza, conservação e manejo de resíduos sólidos urbanos.',
        'data_publicacao' => '16/05/2026',
        'data_abertura' => '28/05/2026',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'orgao' => 'Prefeitura Municipal de São Paulo',
        'edital' => 'SMS-PMSP/2026/001',
        'valor_estimado' => 'R$ 2.500.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'uasg' => '123456',
        'processo' => '00123.456789/2026-11',
        'conlicitacao' => 'CL-2026-00015',
        'atualizado_em' => '16/05/2026 08:30'
    ],
    [
        'id' => 2,
        'numero' => '002',
        'favorito' => true,
        'visualizacoes' => 128,
        'status_badge' => 'RETIFICAÇÃO',
        'status_cor' => 'orange',
        'objeto' => 'Aquisição de equipamentos de proteção individual (EPIs) e materiais de segurança do trabalho para todas as secretarias.',
        'data_publicacao' => '15/05/2026',
        'data_abertura' => '25/05/2026',
        'cidade' => 'Belo Horizonte',
        'estado' => 'MG',
        'orgao' => 'Governo do Estado de Minas Gerais',
        'edital' => 'SEPLAG-003/2026',
        'valor_estimado' => 'R$ 890.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'uasg' => '987654',
        'processo' => '00100.002345/2026-51',
        'conlicitacao' => 'CL-2026-00022',
        'atualizado_em' => '15/05/2026 16:45', 
    ],
    [
        'id' => 3,
        'numero' => '003',
        'favorito' => false,
        'visualizacoes' => 32,
        'status_badge' => 'NOVA',
        'status_cor' => 'green',
        'objeto' => 'Contratação de serviços de engenharia para elaboração de projetos executivos e execução de obras de pavimentação.',
        'data_publicacao' => '14/05/2026',
        'data_abertura' => '30/05/2026',
        'cidade' => 'Curitiba',
        'estado' => 'PR',
        'orgao' => 'Prefeitura Municipal de Curitiba',
        'edital' => 'SMOP-2026/012',
        'valor_estimado' => 'R$ 5.200.000,00',
        'modalidade' => 'Concorrência',
        'uasg' => '456789',
        'processo' => '00150.789012/2026-00',
        'conlicitacao' => 'CL-2026-00018',
        'atualizado_em' => '14/05/2026 10:15'
    ],
    [
        'id' => 4,
        'numero' => '004',
        'favorito' => false,
        'visualizacoes' => 67,
        'status_badge' => 'URGENTE',
        'status_cor' => 'red',
        'objeto' => 'Fornecimento de medicamentos e insumos farmacéuticos para atendimento das unidades de saúde do município.',
        'data_publicacao' => '16/05/2026',
        'data_abertura' => '20/05/2026',
        'cidade' => 'Fortaleza',
        'estado' => 'CE',
        'orgao' => 'Prefeitura Municipal de Fortaleza',
        'edital' => 'SMS-2026-0005',
        'valor_estimado' => 'R$ 1.850.000,00',
        'modalidade' => 'Pregão Eletrônico',
        'uasg' => '321654',
        'processo' => '00180.654321/2026-77',
        'conlicitacao' => 'CL-2026-00030',
        'atualizado_em' => '16/05/2026 09:00'
    ]
];

$total_resultados = count($licitacoes_mock);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontrar Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-nova { background-color: #16a34a; color: white; }
        .badge-urgente { background-color: #dc2626; color: white; }
        .badge-retificacao { background-color: #f59e0b; color: white; }
        .badge-analise { background-color: #3b82f6; color: white; }
        
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
        
        @media (min-width: 1024px) {
            .filtros-sidebar {
                position: sticky;
                top: 1rem;
                max-height: calc(100vh - 8rem);
                overflow-y: auto;
            }
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
                        <i class="fas fa-search text-blue-900 mr-2"></i>Encontrar Licitações
                    </h2>
                    <p class="text-gray-500 mt-1">Busca avançada com filtros detalhados</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar
                </a>
            </div>
        </div>
        
        <!-- Layout em Duas Colunas -->
        <div class="flex flex-col lg:flex-row gap-6">
            
            <!-- Coluna Esquerda: Painel de Filtros -->
            <div class="lg:w-1/3">
                <div class="bg-white p-5 rounded-lg shadow filtros-sidebar">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                        <h3 class="font-bold text-gray-700">
                            <i class="fas fa-filter mr-2"></i>Filtros de Pesquisa
                        </h3>
                        <button type="button" class="text-sm text-blue-600 hover:underline" onclick="document.querySelector('form').reset();">
                            Limpar tudo
                        </button>
                    </div>
                    
                    <form method="GET" action="encontrar_licitacoes.php" class="space-y-4">
                        <!-- Objeto -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Objeto</label>
                            <input type="text" name="objeto" value="<?php echo htmlspecialchars($filtros['objeto']); ?>" placeholder="Palavras-chave..." class="w-full">
                        </div>
                        
                        <!-- Busca Exata -->
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="busca_exata" name="busca_exata" value="1" <?php echo $filtros['busca_exata'] ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 rounded">
                            <label for="busca_exata" class="text-sm text-gray-700">Busca exata</label>
                        </div>
                        
                        <!-- Estado e Cidade -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select name="estado" class="w-full">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $est): ?>
                                        <option value="<?php echo $est; ?>" <?php echo ($filtros['estado'] === $est) ? 'selected' : ''; ?>><?php echo $est; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" name="cidade" value="<?php echo htmlspecialchars($filtros['cidade']); ?>" class="w-full">
                            </div>
                        </div>
                        
                        <!-- Nº Edital e Modalidade -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nº Edital</label>
                                <input type="text" name="numero_edital" value="<?php echo htmlspecialchars($filtros['numero_edital']); ?>" class="w-full">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                                <select name="modalidade" class="w-full">
                                    <option value="">Todas</option>
                                    <?php foreach ($modalidades as $mod): ?>
                                        <option value="<?php echo $mod; ?>" <?php echo ($filtros['modalidade'] === $mod) ? 'selected' : ''; ?>><?php echo $mod; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Data de Inclusão -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de inclusão (De)</label>
                                <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filtros['data_inicio']); ?>" class="w-full">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de inclusão (Até)</label>
                                <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filtros['data_fim']); ?>" class="w-full">
                            </div>
                        </div>
                        
                        <!-- Nº Conlicitação e Código Órgão -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nº Conlicitação</label>
                                <input type="text" name="conlicitacao" value="<?php echo htmlspecialchars($filtros['conlicitacao']); ?>" class="w-full">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Código do órgão</label>
                                <input type="text" name="codigo_orgao" value="<?php echo htmlspecialchars($filtros['codigo_orgao']); ?>" class="w-full">
                            </div>
                        </div>
                        
                        <!-- Esfera e Nº Processo -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Esfera</label>
                                <select name="esfera" class="w-full">
                                    <option value="">Todas</option>
                                    <?php foreach ($esferas as $esf): ?>
                                        <option value="<?php echo $esf; ?>" <?php echo ($filtros['esfera'] === $esf) ? 'selected' : ''; ?>><?php echo $esf; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nº Processo</label>
                                <input type="text" name="numero_processo" value="<?php echo htmlspecialchars($filtros['numero_processo']); ?>" class="w-full">
                            </div>
                        </div>
                        
                        <!-- Situação e Órgão -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                            <select name="situacao" class="w-full">
                                <option value="">Todas</option>
                                <?php foreach ($situacoes as $sit): ?>
                                    <option value="<?php echo $sit; ?>" <?php echo ($filtros['situacao'] === $sit) ? 'selected' : ''; ?>><?php echo $sit; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Órgão</label>
                            <input type="text" name="orgao" value="<?php echo htmlspecialchars($filtros['orgao']); ?>" class="w-full">
                        </div>
                        
                        <!-- Itens e Observação -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Itens</label>
                            <input type="text" name="itens" value="<?php echo htmlspecialchars($filtros['itens']); ?>" placeholder="Buscar nos itens..." class="w-full">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                            <input type="text" name="observacao" value="<?php echo htmlspecialchars($filtros['observacao']); ?>" class="w-full">
                        </div>
                        
                        <!-- Concorrência e Atividades -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Concorrência</label>
                                <select name="concorrencia" class="w-full">
                                    <option value="">Todas</option>
                                    <?php foreach ($concorrencias as $conc): ?>
                                        <option value="<?php echo $conc; ?>" <?php echo ($filtros['concorrencia'] === $conc) ? 'selected' : ''; ?>><?php echo $conc; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Atividades</label>
                                <select name="atividades" class="w-full">
                                    <option value="">Todas</option>
                                    <?php foreach ($atividades_opcoes as $ativ): ?>
                                        <option value="<?php echo $ativ; ?>" <?php echo ($filtros['atividades'] === $ativ) ? 'selected' : ''; ?>><?php echo $ativ; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Salvar Pesquisa -->
                        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                            <input type="checkbox" id="salvar_pesquisa" name="salvar_pesquisa" <?php echo $filtros['salvar_pesquisa'] ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 rounded">
                            <label for="salvar_pesquisa" class="text-sm text-gray-700">Salvar pesquisa?</label>
                        </div>
                        
                        <!-- Botão Pesquisar -->
                        <button type="submit" class="btn btn-primary w-full mt-2">
                            <i class="fas fa-search mr-2"></i> Pesquisar
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Coluna Direita: Resultados -->
            <div class="lg:w-2/3">
                <!-- Ações e Info Superior -->
                <div class="bg-white p-4 rounded-lg shadow mb-4">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
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
                        
                        <!-- Total e Seletor -->
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-600">
                                <strong><?php echo $total_resultados; ?></strong> licitações encontradas
                            </span>
                            <select name="mostrar" class="w-auto" onchange="this.form.submit()">
                                <option value="20" <?php echo ($filtros['mostrar'] === '20') ? 'selected' : ''; ?>>Mostrar 20</option>
                                <option value="50" <?php echo ($filtros['mostrar'] === '50') ? 'selected' : ''; ?>>Mostrar 50</option>
                                <option value="100" <?php echo ($filtros['mostrar'] === '100') ? 'selected' : ''; ?>>Mostrar 100</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Cards -->
                <div class="space-y-4 mb-6">
                    <?php foreach ($licitacoes_mock as $licitacao): ?>
                    <div class="licitacao-card bg-white p-5 rounded-lg shadow border-l-4 <?php echo $licitacao['status_cor'] === 'red' ? 'border-l-red-500' : ($licitacao['status_cor'] === 'green' ? 'border-l-green-500' : ($licitacao['status_cor'] === 'orange' ? 'border-l-orange-500' : 'border-l-blue-500')); ?>">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <!-- Número e Ações Rápidas -->
                            <div class="flex-shrink-0 flex flex-col items-center gap-2">
                                <span class="text-lg font-bold text-gray-400">#<?php echo $licitacao['numero']; ?></span>
                                <button class="text-<?php echo $licitacao['favorito'] ? 'red' : 'gray'; ?> hover:text-red-600 transition-colors" title="Favorito">
                                    <i class="fas fa-heart fa-lg"></i>
                                </button>
                                <span class="text-xs text-gray-400" title="Visualizações">
                                    <i class="fas fa-eye"></i> <?php echo $licitacao['visualizacoes']; ?>
                                </span>
                            </div>
                            
                            <!-- Conteúdo Principal -->
                            <div class="flex-grow">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <!-- Badge de Status -->
                                    <span class="px-2 py-1 rounded text-xs font-bold uppercase badge-<?php echo $licitacao['status_cor']; ?>">
                                        <?php echo htmlspecialchars($licitacao['status_badge']); ?>
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i> Pub.: <?php echo $licitacao['data_publicacao']; ?>
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i> Ab.: <?php echo $licitacao['data_abertura']; ?>
                                    </span>
                                </div>
                                
                                <!-- Objeto -->
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($licitacao['objeto']); ?>
                                </h3>
                                
                                <!-- Informações do Órgão -->
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
                                        <i class="fas fa-money-bill-wave mr-1 text-gray-400"></i>
                                        <span class="font-medium">Valor:</span> <?php echo $licitacao['valor_estimado']; ?>
                                    </div>
                                    <div class="text-gray-600">
                                        <i class="fas fa-tag mr-1 text-gray-400"></i>
                                        <span class="font-medium">Modalidade:</span> <?php echo htmlspecialchars($licitacao['modalidade']); ?>
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
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-cog"></i> Gerenciar
                                    </button>
                                    <button class="btn btn-secondary btn-sm" title="Ativar monitoramento de chat">
                                        <i class="fas fa-comments"></i> Monitorar Chat
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
                <div class="bg-white p-4 rounded-lg shadow">
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
                            <a href="?page=3" class="pagination-link <?php echo ($pagina_atual == 3) ? 'active' : ''; ?>">3</a>
                            <span class="px-2 text-gray-400">...</span>
                            <a href="?page=10" class="pagination-link">10</a>
                            <a href="?page=<?php echo $pagina_atual + 1; ?>" class="pagination-link">
                                Próximo <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/script.js?v=2.35"></script>
    <script>
        // =====================================================
        // FUTURA INTEGRAÇÃO COM BANCO DE DADOS
        // =====================================================
        /*
        Para conectar com o banco de dados, substitua o array $licitacoes_mock 
        por uma query como esta:
        
        $pdo = (new Database())->connect();
        
        $sql = "SELECT p.*, u.nome as orgao_nome 
                FROM pregoes p 
                LEFT JOIN orgaos u ON p.uasg = u.uasg
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filtros['objeto'])) {
            $sql .= " AND p.objeto LIKE :objeto";
            $params[':objeto'] = '%' . $filtros['objeto'] . '%';
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND p.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }
        // ... adicionar demais filtros
        
        $sql .= " ORDER BY p.data_publicacao DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $licitacoes_mock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
    </script>
</body>
</html>