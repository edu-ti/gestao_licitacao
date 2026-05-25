<?php
require_once 'auth.php';
require_once 'Database.php';

$db = new Database();
$pdo = $db->connect();

$status_disponiveis = [
    "Acolhimento de Proposta",
    "Em Analise",
    "Homologando",
    "Revogado",
    "Fracassado",
    "Anulado",
    "Suspenso",
    "Adjudicado", // Corrigido de 'Adjuncado'
    "Deserto"
];

// Combine os do banco de dados para não perder antigos caso o usuário busque por eles
$status_db = $pdo->query("SELECT DISTINCT status FROM pregoes WHERE status IS NOT NULL AND status != '' ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
$status_disponiveis = array_unique(array_merge($status_disponiveis, $status_db));
sort($status_disponiveis);

$orgaos_disponiveis = $pdo->query("SELECT DISTINCT orgao_comprador FROM pregoes ORDER BY orgao_comprador ASC")->fetchAll(PDO::FETCH_COLUMN);
$fornecedores_disponiveis = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$pregoes_disponiveis = $pdo->query("SELECT id, numero_edital, orgao_comprador, data_sessao FROM pregoes ORDER BY data_sessao DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Gerenciais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <link rel="stylesheet" href="css/style.css?v=2.30">
    <style>
        .cb-scroll {
            max-height: 140px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.375rem;
            background-color: #fff;
        }
        .cb-scroll label {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 1px 4px;
            cursor: pointer;
            font-size: 0.875rem;
            line-height: 1.4;
            border-radius: 0.125rem;
        }
        .cb-scroll label:hover {
            background-color: #f3f4f6;
        }
        .cb-scroll input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="container mx-auto">
        <?php 
            $page_title = 'Relatórios Gerenciais';
            include 'header.php'; 
        ?>
        <div class="bg-[#f7f6f6] p-8 rounded-lg shadow-lg">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-2xl font-bold text-gray-800">Gerar Relatório de Pregões</h2>
                <a href="dashboard.php" class="btn btn-primary">&larr; Voltar para o Painel</a>
            </div>

            <form action="gerar_pdf.php" method="get" target="_blank" id="form-relatorio">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>
                        <label for="tipo_relatorio" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Relatório</label>
                        <select id="tipo_relatorio" name="tipo_relatorio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                            <option value="geral">Relatório Geral de Pregões</option>
                            <option value="especifico">Relatório por Pregão Específico</option>
                        </select>
                    </div>

                    <div id="div-pregao" style="display:none;">
                        <label for="filtro_pregao" class="block text-sm font-medium text-gray-700 mb-1">Selecionar Pregão</label>
                        <select id="filtro_pregao" name="filtro_pregao" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Selecione um pregão...</option>
                            <?php foreach ($pregoes_disponiveis as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['numero_edital'] . ' - ' . $p['orgao_comprador'] . ' (' . date('d/m/Y', strtotime($p['data_sessao'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Filtrar por Status</label>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="text-blue-600 hover:underline cursor-pointer select-all-btn" data-group="group-status">Todos</button>
                                <button type="button" class="text-blue-600 hover:underline cursor-pointer clear-all-btn" data-group="group-status">Limpar</button>
                            </div>
                        </div>
                        <input type="text" id="search-status" placeholder="Pesquisar status..." class="mb-2 w-full px-2 py-1 text-sm border border-gray-300 rounded shadow-sm focus:outline-none focus:border-indigo-500">
                        <div class="cb-scroll" id="group-status">
                            <?php foreach ($status_disponiveis as $s): ?>
                                <label><input type="checkbox" name="filtro_status[]" value="<?php echo htmlspecialchars($s); ?>"> <span class="cb-label-text"><?php echo htmlspecialchars($s); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <label for="filtro_orgao" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Órgão</label>
                        <select id="filtro_orgao" name="filtro_orgao" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Todos os Órgãos</option>
                            <?php foreach ($orgaos_disponiveis as $orgao): ?>
                                <option value="<?php echo htmlspecialchars($orgao); ?>"><?php echo htmlspecialchars($orgao); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Filtrar por Fornecedor</label>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="text-blue-600 hover:underline cursor-pointer select-all-btn" data-group="group-fornecedor">Todos</button>
                                <button type="button" class="text-blue-600 hover:underline cursor-pointer clear-all-btn" data-group="group-fornecedor">Limpar</button>
                            </div>
                        </div>
                        <input type="text" id="search-fornecedor" placeholder="Pesquisar fornecedor..." class="mb-2 w-full px-2 py-1 text-sm border border-gray-300 rounded shadow-sm focus:outline-none focus:border-indigo-500">
                        <div class="cb-scroll" id="group-fornecedor">
                            <?php foreach ($fornecedores_disponiveis as $f): ?>
                                <label><input type="checkbox" name="filtro_fornecedor[]" value="<?php echo $f['id']; ?>"> <span class="cb-label-text"><?php echo htmlspecialchars($f['nome']); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <label for="filtro_data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Período (Data da Disputa) - Início</label>
                        <input type="date" name="filtro_data_inicio" id="filtro_data_inicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="filtro_data_fim" class="block text-sm font-medium text-gray-700 mb-1">Período (Data da Disputa) - Fim</label>
                        <input type="date" name="filtro_data_fim" id="filtro_data_fim" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="mt-8 text-right">
                    <button type="submit" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Gerar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Inicializando o Tom Select para facilitar a pesquisa em selects longos
            new TomSelect("#filtro_orgao", {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });

            var selectPregaoInstance = new TomSelect("#filtro_pregao", {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });

            var tipoSelect = document.getElementById('tipo_relatorio');
            var divPregao = document.getElementById('div-pregao');

            tipoSelect.addEventListener('change', function() {
                if (this.value === 'especifico') {
                    divPregao.style.display = 'block';
                } else {
                    divPregao.style.display = 'none';
                    selectPregaoInstance.clear();
                }
            });

            document.getElementById('form-relatorio').addEventListener('submit', function(e) {
                if (tipoSelect.value === 'especifico') {
                    if (!selectPregaoInstance.getValue()) {
                        e.preventDefault();
                        alert('Selecione um pregão para o relatório específico.');
                        return false;
                    }
                }
            });

            document.querySelectorAll('.select-all-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var groupId = this.getAttribute('data-group');
                    var checkboxes = document.querySelectorAll('#' + groupId + ' input[type="checkbox"]');
                    checkboxes.forEach(function(cb) { 
                        if(cb.closest('label').style.display !== 'none') {
                            cb.checked = true; 
                        }
                    });
                });
            });

            document.querySelectorAll('.clear-all-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var groupId = this.getAttribute('data-group');
                    var checkboxes = document.querySelectorAll('#' + groupId + ' input[type="checkbox"]');
                    checkboxes.forEach(function(cb) { cb.checked = false; });
                });
            });

            // Função de busca para checkboxes
            function setupCheckboxSearch(inputId, groupId) {
                var input = document.getElementById(inputId);
                var group = document.getElementById(groupId);
                if(!input || !group) return;

                var labels = group.querySelectorAll('label');

                input.addEventListener('input', function() {
                    var filter = this.value.toLowerCase();
                    labels.forEach(function(label) {
                        var textElement = label.querySelector('.cb-label-text');
                        if (textElement) {
                            var text = textElement.textContent.toLowerCase();
                            if (text.indexOf(filter) > -1) {
                                label.style.display = 'flex';
                            } else {
                                label.style.display = 'none';
                            }
                        }
                    });
                });
            }

            setupCheckboxSearch('search-status', 'group-status');
            setupCheckboxSearch('search-fornecedor', 'group-fornecedor');

        });
    </script>
</body>
</html>
