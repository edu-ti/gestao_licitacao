<?php
require_once 'auth.php';
require_once 'Database.php';

$db = new Database();
$pdo = $db->connect();

// Buscar opções para os filtros
$status_disponiveis = $pdo->query("SELECT DISTINCT status FROM pregoes WHERE status IS NOT NULL AND status != '' ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
$orgaos_disponiveis = $pdo->query("SELECT DISTINCT orgao_comprador FROM pregoes ORDER BY orgao_comprador ASC")->fetchAll(PDO::FETCH_COLUMN);
// **NOVO: Buscar fornecedores para o filtro**
$fornecedores_disponiveis = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Gerenciais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.29">
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
            
            <form action="gerar_pdf.php" method="get" target="_blank">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="tipo_relatorio" class="block text-sm font-medium text-gray-700">Tipo de Relatório</label>
                        <select id="tipo_relatorio" name="tipo_relatorio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                            <option value="geral">Relatório Geral de Pregões</option>
                        </select>
                    </div>
                    <div>
                        <label for="filtro_status" class="block text-sm font-medium text-gray-700">Filtrar por Status</label>
                        <select id="filtro_status" name="filtro_status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Todos os Status</option>
                            <?php foreach ($status_disponiveis as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div>
                        <label for="filtro_orgao" class="block text-sm font-medium text-gray-700">Filtrar por Órgão</label>
                        <select id="filtro_orgao" name="filtro_orgao" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Todos os Órgãos</option>
                            <?php foreach ($orgaos_disponiveis as $orgao): ?>
                                <option value="<?php echo htmlspecialchars($orgao); ?>"><?php echo htmlspecialchars($orgao); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- **NOVO: Campo de filtro por Fornecedor** -->
                    <div>
                        <label for="filtro_fornecedor" class="block text-sm font-medium text-gray-700">Filtrar por Fornecedor</label>
                        <select id="filtro_fornecedor" name="filtro_fornecedor" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Todos os Fornecedores</option>
                            <?php foreach ($fornecedores_disponiveis as $fornecedor): ?>
                                <option value="<?php echo $fornecedor['id']; ?>"><?php echo htmlspecialchars($fornecedor['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="filtro_data_inicio" class="block text-sm font-medium text-gray-700">Período (Data da Disputa) - Início</label>
                        <input type="date" name="filtro_data_inicio" id="filtro_data_inicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="filtro_data_fim" class="block text-sm font-medium text-gray-700">Período (Data da Disputa) - Fim</label>
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
</body>
</html>
