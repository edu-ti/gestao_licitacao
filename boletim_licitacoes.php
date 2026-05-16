<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$page_title = 'Boletim de Licitações';

$estado_selecionado = $_GET['estado'] ?? '';
$status_selecionado = $_GET['status'] ?? '';
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$por_pagina = 20;

$db = new Database();
$pdo = $db->connect();

$where = [];
$params = [];

if (!empty($estado_selecionado) && $estado_selecionado !== 'Todos') {
    $where[] = 'bl.estado = ?';
    $params[] = $estado_selecionado;
}
if (!empty($status_selecionado) && $status_selecionado !== 'Todos') {
    $where[] = 'bl.status_badge = ?';
    $params[] = strtoupper($status_selecionado);
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql = "SELECT COUNT(*) FROM boletim_licitacoes bl $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_licitacoes = (int)$stmt->fetchColumn();

$total_paginas = max(1, ceil($total_licitacoes / $por_pagina));
$offset = ($pagina_atual - 1) * $por_pagina;

$query = "SELECT bl.*, b.data_boletim FROM boletim_licitacoes bl LEFT JOIN boletins b ON b.id = bl.boletim_id $where_sql ORDER BY bl.data_publicacao DESC, bl.id DESC LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$licitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$estados = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
$status_opcoes = ['', 'NOVA', 'URGENTE', 'EM ANÁLISE', 'DESTAQUE', 'VENCIDA'];
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
        .badge-yellow { background-color: #f59e0b; color: white; }
        .badge-vencida { background-color: #6b7280; color: white; }
        .badge-gray { background-color: #6b7280; color: white; }
        
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $est): ?>
                            <?php if ($est): ?>
                            <option value="<?php echo $est; ?>" <?php echo ($estado_selecionado === $est) ? 'selected' : ''; ?>><?php echo $est; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full">
                        <option value="">Todos</option>
                        <?php foreach ($status_opcoes as $st): ?>
                            <?php if ($st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($status_selecionado === $st) ? 'selected' : ''; ?>><?php echo ucfirst(strtolower($st)); ?></option>
                            <?php endif; ?>
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
            <?php if (empty($licitacoes)): ?>
            <div class="bg-white p-8 rounded-lg shadow text-center text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p class="text-lg">Nenhuma licitação encontrada.</p>
                <p class="text-sm">Execute <code>php cron_apis.php</code> no servidor para captar dados dos portais.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($licitacoes as $lic): 
                $status = $lic['status_badge'] ?? 'NOVA';
                $status_upper = strtoupper($status);
                $cor_map = ['URGENTE' => 'red', 'NOVA' => 'green', 'EM ANÁLISE' => 'blue', 'DESTAQUE' => 'yellow', 'VENCIDA' => 'gray'];
                $cor = $cor_map[$status_upper] ?? 'blue';
                $border = match($cor) {'red' => 'border-l-red-500', 'green' => 'border-l-green-500', 'blue' => 'border-l-blue-500', 'yellow' => 'border-l-yellow-500', default => 'border-l-gray-500'};

                $valor = $lic['valor_estimado'] ? 'R$ ' . number_format((float)$lic['valor_estimado'], 2, ',', '.') : 'N/I';
                $data_pub = $lic['data_publicacao'] ? date('d/m/Y', strtotime($lic['data_publicacao'])) : 'N/I';
                $data_ab = $lic['data_abertura'] ? date('d/m/Y', strtotime($lic['data_abertura'])) : 'N/I';
                $atualizado = $lic['atualizado_em'] ? date('d/m/Y H:i', strtotime($lic['atualizado_em'])) : 'N/I';

                $link_edital = $lic['link_edital'] ?? '';
                $link_detalhes = $lic['link_detalhes'] ?? '';
                $link_edital_target = $link_edital ?: $link_detalhes;
            ?>
            <div class="licitacao-card bg-white p-5 rounded-lg shadow border-l-4 <?php echo $border; ?>">
                <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                    <div class="flex-shrink-0">
                        <span class="text-lg font-bold text-gray-400">#<?php echo $lic['id']; ?></span>
                    </div>
                    
                    <div class="flex-grow">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase badge-<?php echo $cor; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                            <?php if ($lic['portal_origem']): ?>
                            <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                <?php echo htmlspecialchars($lic['portal_origem']); ?>
                            </span>
                            <?php endif; ?>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-calendar mr-1"></i> Publicação: <?php echo $data_pub; ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i> Abertura: <?php echo $data_ab; ?>
                            </span>
                        </div>
                        
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            <?php echo htmlspecialchars(mb_substr($lic['objeto'] ?? 'Sem objeto', 0, 300)); ?>
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-3">
                            <div class="text-gray-600">
                                <i class="fas fa-building mr-1 text-gray-400"></i>
                                <span class="font-medium">Órgão:</span> <?php echo htmlspecialchars(mb_substr($lic['orgao'] ?? 'N/I', 0, 120)); ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                <span class="font-medium">Cidade/UF:</span> <?php echo htmlspecialchars($lic['cidade'] ?? 'N/I'); ?>/<?php echo $lic['estado'] ?? ''; ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-file-alt mr-1 text-gray-400"></i>
                                <span class="font-medium">Edital:</span> <?php echo htmlspecialchars($lic['edital'] ?? 'N/I'); ?>
                            </div>
                            <div class="text-gray-600">
                                <i class="fas fa-money-bill-wave mr-1 text-gray-400"></i>
                                <span class="font-medium">Valor:</span> <?php echo $valor; ?>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-500 mb-3">
                            <span class="bg-gray-100 px-2 py-1 rounded text-xs">
                                <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($lic['modalidade'] ?? 'N/I'); ?>
                            </span>
                        </div>
                        
                        <?php if ($link_detalhes): ?>
                        <a href="<?php echo htmlspecialchars($link_detalhes); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center">
                            <i class="fas fa-external-link-alt mr-1"></i> Ver mais informações da licitação
                        </a>
                        <?php endif; ?>
                        
                        <div class="annotation-area">
                            <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center mb-2">
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-hashtag mr-1"></i> Nº Processo: <span class="font-mono text-gray-700"><?php echo htmlspecialchars($lic['numero_processo'] ?? 'N/I'); ?></span>
                                </div>
                                <div class="text-xs text-gray-400 sm:ml-auto">
                                    <i class="fas fa-sync-alt mr-1"></i> Atualizado: <?php echo $atualizado; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ações do Card -->
                    <div class="flex-shrink-0 flex flex-col gap-2 lg:min-w-[160px]">
                        <?php if ($link_edital_target): ?>
                        <a href="<?php echo htmlspecialchars($link_edital_target); ?>" target="_blank" class="btn btn-secondary btn-sm w-full inline-flex items-center justify-center">
                            <i class="fas fa-download mr-1"></i> Baixar edital
                        </a>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm w-full opacity-50 cursor-not-allowed" disabled>
                            <i class="fas fa-download mr-1"></i> Sem edital
                        </button>
                        <?php endif; ?>
                        <a href="gerenciar_licitacoes.php?id=<?php echo $lic['id']; ?>" class="btn btn-primary btn-sm w-full inline-flex items-center justify-center">
                            <i class="fas fa-cog mr-1"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-500">
                    Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $por_pagina, $total_licitacoes); ?> de <?php echo $total_licitacoes; ?> resultados
                </div>
                <nav class="flex items-center gap-1">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>&estado=<?php echo urlencode($estado_selecionado); ?>&status=<?php echo urlencode($status_selecionado); ?>" class="pagination-link <?php echo ($pagina_atual <= 1) ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&estado=<?php echo urlencode($estado_selecionado); ?>&status=<?php echo urlencode($status_selecionado); ?>" class="pagination-link <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>&estado=<?php echo urlencode($estado_selecionado); ?>&status=<?php echo urlencode($status_selecionado); ?>" class="pagination-link <?php echo ($pagina_atual >= $total_paginas) ? 'opacity-50 pointer-events-none' : ''; ?>">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </a>
                </nav>
            </div>
        </div>
        <?php endif; ?>
        
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