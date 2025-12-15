<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';
require_once 'notificacoes.php';

$status_list = ["Em análise", "Acolhimento de propostas", "Homologado", "Revogado", "Fracassado", "Anulado", "Adjudicado", "Suspenso"];
$status_item_list = ["Classificada", "Desclassificada", "Em Negociação", "Aceita", "Adjudicado", "Homologado"];

$mensagem = '';
if (isset($_SESSION['mensagem_detalhes'])) {
    $mensagem = $_SESSION['mensagem_detalhes'];
    unset($_SESSION['mensagem_detalhes']);
}
$erro_fatal = '';
$pregao = null;
$itens_agrupados = [];
$observacoes = [];
$historico = [];
$anexos = [];
$fornecedores_disponiveis = [];
$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: dashboard.php");
    exit();
}
$pregao_id = $_GET['id'];

try {
    $db = new Database();
    $pdo = $db->connect();
    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isAdmin()) {
        $should_redirect = true;

        if (isset($_POST['submit_anexo']) && isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
            $nome_original = basename($_FILES['anexo']['name']);
            $descricao_anexo = !empty($_POST['descricao_anexo']) ? trim($_POST['descricao_anexo']) : $nome_original;
            $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
            $nome_arquivo_unico = uniqid('pregao_' . $pregao_id . '_', true) . '.' . $extensao;
            $caminho_destino = UPLOAD_DIR . $nome_arquivo_unico;
            if (move_uploaded_file($_FILES['anexo']['tmp_name'], $caminho_destino)) {
                $sql = "INSERT INTO anexos_pregao (pregao_id, nome_original, descricao_anexo, nome_arquivo) VALUES (?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$pregao_id, $nome_original, $descricao_anexo, $nome_arquivo_unico]);
                $_SESSION['mensagem_detalhes'] = "Ficheiro enviado com sucesso!";
            } else {
                $_SESSION['mensagem_detalhes'] = "Erro ao mover o ficheiro para o destino.";
            }
        }

        if (isset($_POST['excluir_anexo_id'])) {
            $anexo_id = intval($_POST['excluir_anexo_id']);
            $stmt = $pdo->prepare("SELECT nome_arquivo FROM anexos_pregao WHERE id = ? AND pregao_id = ?");
            $stmt->execute([$anexo_id, $pregao_id]);
            $anexo = $stmt->fetch();
            if ($anexo && file_exists(UPLOAD_DIR . $anexo['nome_arquivo'])) {
                @unlink(UPLOAD_DIR . $anexo['nome_arquivo']);
            }
            $pdo->prepare("DELETE FROM anexos_pregao WHERE id = ?")->execute([$anexo_id]);
            $_SESSION['mensagem_detalhes'] = "Anexo excluído com sucesso!";
        }
        
        // --- ATUALIZAÇÃO PARA LOTES (INSERIR) ---
        if (isset($_POST['submit_item'])) {
            $numero_lote = !empty($_POST['numero_lote']) ? trim($_POST['numero_lote']) : null;
            $sql = "INSERT INTO itens_pregoes (pregao_id, fornecedor_id, numero_lote, numero_item, descricao, fabricante, modelo, quantidade, valor_unitario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$pregao_id, $_POST['fornecedor_id'], $numero_lote, $_POST['numero_item'], $_POST['descricao_item'], $_POST['fabricante_item'], $_POST['modelo_item'], $_POST['quantidade_item'], $_POST['valor_unitario_item']]);
            logActivity($pdo, $current_user_id, 'itens_pregoes', 'ADICIONAR ITEM', $pregao_id, "Item '" . $_POST['descricao_item'] . "' foi adicionado.");
            $_SESSION['mensagem_detalhes'] = "Item adicionado com sucesso!";
        }

        // --- ATUALIZAÇÃO PARA LOTES (EDITAR) ---
        if (isset($_POST['submit_edit_item'])) {
            $edit_numero_lote = !empty($_POST['edit_numero_lote']) ? trim($_POST['edit_numero_lote']) : null;
            $sql = "UPDATE itens_pregoes SET fornecedor_id = ?, numero_lote = ?, numero_item = ?, descricao = ?, fabricante = ?, modelo = ?, quantidade = ?, valor_unitario = ?, status_item = ?, status_motivo = ? WHERE id = ? AND pregao_id = ?";
            $pdo->prepare($sql)->execute([
                $_POST['edit_fornecedor_id'], $edit_numero_lote, $_POST['edit_numero_item'], $_POST['edit_descricao'], 
                $_POST['edit_fabricante'], $_POST['edit_modelo'], $_POST['edit_quantidade'], $_POST['edit_valor_unitario'], 
                $_POST['edit_status_item'], $_POST['edit_status_motivo'], $_POST['edit_item_id'], $pregao_id
            ]);
            logActivity($pdo, $current_user_id, 'itens_pregoes', 'EDITAR ITEM', $pregao_id, "Item '" . $_POST['edit_descricao'] . "' (ID: " . $_POST['edit_item_id'] . ") foi atualizado.");
            $_SESSION['mensagem_detalhes'] = "Item atualizado com sucesso!";
        }

        if (isset($_POST['excluir_id_item'])) {
            $item_id_delete = intval($_POST['excluir_id_item']);
            $stmt_info = $pdo->prepare("SELECT descricao FROM itens_pregoes WHERE id = ?");
            $stmt_info->execute([$item_id_delete]);
            $item_info = $stmt_info->fetch();
            $item_descricao = $item_info ? $item_info['descricao'] : 'ID ' . $item_id_delete;
            $pdo->prepare("DELETE FROM itens_pregoes WHERE id = ? AND pregao_id = ?")->execute([$item_id_delete, $pregao_id]);
            logActivity($pdo, $current_user_id, 'itens_pregoes', 'EXCLUIR ITEM', $pregao_id, "Item '" . $item_descricao . "' foi excluído.");
            $_SESSION['mensagem_detalhes'] = "Item excluído com sucesso!";
        }
        
        if (isset($_POST['submit_observacao'])) {
            if (!empty($_POST['texto_observacao'])) {
                $pdo->prepare("INSERT INTO observacoes_pregao (pregao_id, usuario_id, observacao) VALUES (?, ?, ?)")->execute([$pregao_id, $current_user_id, $_POST['texto_observacao']]);
                $_SESSION['mensagem_detalhes'] = "Observação adicionada com sucesso!";
            }
        }

        if (isset($_POST['submit_status'])) {
            $novo_status = $_POST['status'];
            $pdo->prepare("UPDATE pregoes SET status = ? WHERE id = ?")->execute([$novo_status, $pregao_id]);
            logActivity($pdo, $current_user_id, 'pregoes', 'MUDANÇA DE STATUS', $pregao_id, "Status do pregão alterado para '" . $novo_status . "'.");
            $_SESSION['mensagem_detalhes'] = "Status do pregão atualizado com sucesso!";
        }
        
        if ($should_redirect) {
            header("Location: pregao_detalhes.php?id=" . $pregao_id);
            exit();
        }
    }

    $stmt_pregao = $pdo->prepare("SELECT * FROM pregoes WHERE id = ?");
    $stmt_pregao->execute([$pregao_id]);
    $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

    if (!$pregao) { header("Location: dashboard.php"); exit(); }

    $stmt_anexos = $pdo->prepare("SELECT * FROM anexos_pregao WHERE pregao_id = ? ORDER BY created_at DESC");
    $stmt_anexos->execute([$pregao_id]);
    $anexos = $stmt_anexos->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE AGRUPAMENTO ATUALIZADA (FORNECEDOR -> LOTE -> ITENS) ---
    $itens_agrupados = [];
    $stmt_itens = $pdo->prepare(
        "SELECT i.*, f.nome AS fornecedor_nome 
         FROM itens_pregoes i 
         JOIN fornecedores f ON i.fornecedor_id = f.id 
         WHERE i.pregao_id = ? 
         ORDER BY f.nome ASC, i.numero_lote ASC, CAST(i.numero_item AS UNSIGNED) ASC, i.numero_item ASC"
    );
    $stmt_itens->execute([$pregao_id]);
    foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
        // Usa 'SEM_LOTE' como chave para itens sem lote definido
        $lote_key = !empty($item['numero_lote']) ? $item['numero_lote'] : 'SEM_LOTE';
        $itens_agrupados[$item['fornecedor_nome']][$lote_key][] = $item;
    }

    $fornecedores_disponiveis = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_obs = $pdo->prepare("SELECT o.*, u.nome AS usuario_nome FROM observacoes_pregao o JOIN usuarios u ON o.usuario_id = u.id WHERE o.pregao_id = ? ORDER BY o.created_at DESC");
    $stmt_obs->execute([$pregao_id]);
    $observacoes = $stmt_obs->fetchAll(PDO::FETCH_ASSOC);

    $stmt_hist = $pdo->prepare("SELECT l.*, u.nome as usuario_nome FROM logs_atividades l JOIN usuarios u ON l.usuario_id = u.id WHERE l.registro_id = ? AND l.tabela IN ('itens_pregoes', 'pregoes') ORDER BY l.created_at DESC");
    $stmt_hist->execute([$pregao_id]);
    $historico = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erro_fatal = "Ocorreu um erro crítico. Detalhes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pregão</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.29"> <!-- Versão atualizada -->
    <style>
        @media print { body { background-color: white; padding: 0; } .no-print { display: none !important; } .container { box-shadow: none !important; } .print-header { display: block !important; } }
    </style>
</head>
<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php 
            $page_title = 'Detalhes do Pregão';
            include 'header.php'; 
        ?>
        <div class="print-header hidden text-center mb-8 border-b pb-4">
            <h1 class="text-2xl font-bold">Relatório do Pregão</h1>
            <p class="text-sm text-gray-600">Edital: <?php echo htmlspecialchars($pregao['numero_edital'] ?? 'N/D'); ?></p>
        </div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 no-print gap-4">
            <h1 class="text-3xl font-bold text-gray-800">Detalhes do Pregão</h1>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="btn btn-secondary">Imprimir</button>
                <a href="dashboard.php" class="btn btn-primary">&larr; Voltar ao Painel</a>
            </div>
        </div>
        
        <?php if (!empty($erro_fatal)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert"><p><?php echo htmlspecialchars($erro_fatal); ?></p></div>
        <?php elseif ($pregao): ?>
            <?php if ($mensagem): ?><div class="bg-green-100 text-green-700 p-3 mb-4 rounded-md no-print"><?php echo htmlspecialchars($mensagem); ?></div><?php endif; ?>
            
            <div class="mb-8 p-6 bg-[#f7f6f6] rounded-lg border">
                <h2 class="text-2xl font-bold text-gray-700 mb-4">Informações do Pregão</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-gray-700">
                    <div><strong>Edital:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['numero_edital']); ?></span></div>
                    <div><strong>Processo:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['numero_processo']); ?></span></div>
                    <div><strong>Modalidade:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['modalidade']); ?></span></div>
                    <div class="lg:col-span-2"><strong>Órgão Comprador:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['orgao_comprador']); ?></span></div>
                    <div><strong>UASG:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['uasg']); ?></span></div>
                    <div class="lg:col-span-3"><strong>Local da Disputa:</strong> <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($pregao['local_disputa']); ?></span></div>
                    <div><strong>Data da Disputa:</strong> <span class="text-gray-900 font-medium"><?php echo !empty($pregao['data_sessao']) ? date("d/m/Y", strtotime($pregao['data_sessao'])) : 'N/D'; ?></span></div>
                    <div><strong>Hora da Disputa:</strong> <span class="text-gray-900 font-medium"><?php echo !empty($pregao['hora_sessao']) ? date("H:i", strtotime($pregao['hora_sessao'])) : 'N/D'; ?></span></div>
                    
                    <div class="flex items-center space-x-2">
                        <strong>Status:</strong>
                        <?php if (isAdmin()): ?>
                        <form method="POST" class="flex-grow flex items-center space-x-2 no-print">
                            <select name="status" class="form-input text-sm w-full px-3 py-2 border rounded-lg">
                                <?php
                                foreach ($status_list as $status_item) {
                                    $selected = ($pregao['status'] === $status_item) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($status_item) . "\" $selected>" . htmlspecialchars($status_item) . "</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" name="submit_status" class="btn btn-primary btn-sm">Alterar</button>
                        </form>
                        <?php endif; ?>
                        <span class="font-semibold print:block hidden"><?php echo htmlspecialchars($pregao['status']); ?></span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <h3 class="font-semibold text-gray-700">Objeto:</h3>
                    <p class="text-gray-800 mt-2 whitespace-pre-wrap"><?php echo htmlspecialchars($pregao['objeto']); ?></p>
                </div>
            </div>

            <!-- SECÇÃO DE ANEXOS ATUALIZADA -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-700 mb-4">Anexos</h3>
                <div class="bg-white rounded-lg shadow-md border p-6">
                    <?php if (empty($anexos)): ?>
                        <p class="text-center text-gray-500">Nenhum anexo encontrado.</p>
                    <?php else: ?>
                        <ul class="space-y-3">
                            <?php foreach ($anexos as $anexo): ?>
                                <li class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <a href="uploads/<?php echo htmlspecialchars($anexo['nome_arquivo']); ?>" target="_blank" class="text-blue-600 hover:underline font-medium">
                                        <?php echo htmlspecialchars(!empty($anexo['descricao_anexo']) ? $anexo['descricao_anexo'] : $anexo['nome_original']); ?>
                                    </a>
                                    <?php if (isAdmin()): ?>
                                        <form id="delete-anexo-form-<?php echo $anexo['id']; ?>" method="POST" class="inline-block">
                                            <input type="hidden" name="excluir_anexo_id" value="<?php echo $anexo['id']; ?>">
                                        </form>
                                        <button type="button" class="btn btn-danger btn-sm js-confirm-delete"
                                                data-form-id="delete-anexo-form-<?php echo $anexo['id']; ?>"
                                                data-message="Tem certeza que deseja excluir o anexo '<?php echo htmlspecialchars(!empty($anexo['descricao_anexo']) ? $anexo['descricao_anexo'] : $anexo['nome_original']); ?>'?">
                                            Excluir
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): ?>
                    <div class="mt-6 pt-6 border-t no-print">
                        <form method="POST" enctype="multipart/form-data">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">Adicionar Novo Anexo</h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="descricao_anexo" class="block text-sm font-medium text-gray-700">Nome ou Descrição do Ficheiro</label>
                                    <input type="text" name="descricao_anexo" id="descricao_anexo" class="mt-1 w-full px-3 py-2 border rounded-lg" placeholder="Ex: Edital, Proposta, etc.">
                                    <p class="text-xs text-gray-500 mt-1">Se deixar em branco, será usado o nome original do ficheiro.</p>
                                </div>
                                <div>
                                    <label for="anexo" class="block text-sm font-medium text-gray-700">Selecione o Ficheiro</label>
                                    <input type="file" name="anexo" id="anexo" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="submit" name="submit_anexo" class="btn btn-primary">Enviar Ficheiro</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- --- ITENS E PROPOSTAS COM LÓGICA DE LOTE --- -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-700 mb-4">Itens e Propostas</h3>
                <?php if (empty($itens_agrupados)): ?>
                    <div class="text-center text-gray-500 p-4 border rounded-lg">Nenhum item registado.</div>
                <?php else: ?>
                    <?php foreach ($itens_agrupados as $fornecedor_nome => $lotes_do_fornecedor): ?>
                        <div class="mb-6 break-inside-avoid">
                            <!-- Cabeçalho do Fornecedor -->
                            <h4 class="text-lg font-semibold text-gray-800 mb-2 p-3 bg-gray-100 rounded-t-lg border-b"><?php echo htmlspecialchars($fornecedor_nome); ?></h4>
                            
                            <!-- Loop pelos Lotes (ou 'SEM_LOTE') -->
                            <?php foreach ($lotes_do_fornecedor as $lote_nome => $itens_do_lote): ?>
                                
                                <!-- Cabeçalho do Lote (só aparece se não for 'SEM_LOTE') -->
                                <?php if ($lote_nome !== 'SEM_LOTE'): ?>
                                    <h5 class="text-md font-semibold text-gray-700 mb-2 p-2 bg-blue-100 text-center rounded-md border"><?php echo htmlspecialchars($lote_nome); ?></h5>
                                <?php endif; ?>

                                <!-- Tabela de Itens -->
                                <div class="overflow-x-auto bg-white rounded-b-lg shadow-md border mb-4">
                                    <table class="min-w-full leading-normal">
                                        <thead class="bg-[#f7f6f6]">
                                            <tr>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nº</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Descrição</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fabricante</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Modelo</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Qtd.</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Valor Unit.</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Valor Total</th>
                                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                                <th class="no-print px-5 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($itens_do_lote as $item): ?>
                                                <tr>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['numero_item']); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['descricao']); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['fabricante'] ?? 'N/D'); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['modelo'] ?? 'N/D'); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['quantidade']); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm font-semibold">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($item['status_item'] ?? 'Classificada'); ?></td>
                                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm no-print">
                                                        <?php if (isAdmin()): ?>
                                                        <div class="flex items-center justify-center gap-2">
                                                            <button class="btn btn-secondary btn-sm edit-item-btn" 
                                                                    data-id="<?php echo $item['id']; ?>" 
                                                                    data-fornecedor_id="<?php echo $item['fornecedor_id']; ?>" 
                                                                    data-numero_lote="<?php echo htmlspecialchars($item['numero_lote'] ?? ''); ?>"
                                                                    data-numero_item="<?php echo htmlspecialchars($item['numero_item']); ?>" 
                                                                    data-descricao="<?php echo htmlspecialchars($item['descricao']); ?>" 
                                                                    data-fabricante="<?php echo htmlspecialchars($item['fabricante'] ?? ''); ?>" 
                                                                    data-modelo="<?php echo htmlspecialchars($item['modelo'] ?? ''); ?>" 
                                                                    data-quantidade="<?php echo $item['quantidade']; ?>" 
                                                                    data-valor_unitario="<?php echo $item['valor_unitario']; ?>"
                                                                    data-status_item="<?php echo htmlspecialchars($item['status_item'] ?? 'Classificada'); ?>"
                                                                    data-status_motivo="<?php echo htmlspecialchars($item['status_motivo'] ?? ''); ?>">Editar</button>
                                                            <form id="delete-item-form-<?php echo $item['id']; ?>" method="POST" class="inline-block"><input type="hidden" name="excluir_id_item" value="<?php echo $item['id']; ?>"></form>
                                                            <button type="button" class="btn btn-danger btn-sm js-confirm-delete" data-form-id="delete-item-form-<?php echo $item['id']; ?>" data-message="Tem certeza que deseja excluir o item '<?php echo htmlspecialchars($item['descricao']); ?>'?">Excluir</button>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if (!empty($item['status_motivo'])): ?>
                                                <tr class="bg-gray-50">
                                                    <td colspan="9" class="px-5 py-2 border-b border-gray-200 text-sm">
                                                        <strong>Motivo:</strong> <?php echo htmlspecialchars($item['status_motivo']); ?>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- --- FORMULÁRIO DE ADIÇÃO COM CAMPO LOTE --- -->
                <?php if (isAdmin()): ?>
                <form method="POST" class="bg-[#f7f6f6] p-6 rounded-lg border mt-8 no-print">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">Adicionar Nova Proposta de Item</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="md:col-span-2"><label for="fornecedor_id">Fornecedor</label><select name="fornecedor_id" id="fornecedor_id" class="form-input w-full px-3 py-2 border rounded-lg" required><option value="">Selecione...</option><?php foreach ($fornecedores_disponiveis as $fornecedor): ?><option value="<?php echo $fornecedor['id']; ?>"><?php echo htmlspecialchars($fornecedor['nome']); ?></option><?php endforeach; ?></select></div>
                        <div><label for="numero_lote">Nº do Lote (Opcional)</label><input type="text" name="numero_lote" id="numero_lote" class="form-input w-full px-3 py-2 border rounded-lg" placeholder="Ex: Lote 01"></div>
                        <div><label for="numero_item">Nº do Item</label><input type="text" name="numero_item" id="numero_item" class="form-input w-full px-3 py-2 border rounded-lg" placeholder="Ex: 1, 2, 3..."></div>
                        <div class="md:col-span-2"><label for="descricao_item">Descrição</label><input type="text" name="descricao_item" id="descricao_item" class="form-input w-full px-3 py-2 border rounded-lg" required></div>
                        <div><label for="fabricante_item">Fabricante/Marca</label><input type="text" name="fabricante_item" id="fabricante_item" class="form-input w-full px-3 py-2 border rounded-lg"></div>
                        <div><label for="modelo_item">Modelo</label><input type="text" name="modelo_item" id="modelo_item" class="form-input w-full px-3 py-2 border rounded-lg"></div>
                        <div><label for="quantidade_item">Quantidade</label><input type="number" name="quantidade_item" id="quantidade_item" class="form-input w-full px-3 py-2 border rounded-lg" required></div>
                        <div><label for="valor_unitario_item">Valor Unitário (R$)</label><input type="number" step="0.01" name="valor_unitario_item" id="valor_unitario_item" class="form-input w-full px-3 py-2 border rounded-lg" required></div>
                    </div>
                    <div class="flex justify-end mt-4"><button type="submit" name="submit_item" class="btn btn-primary">Adicionar</button></div>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-700 mb-4">Observações e Pareceres</h3>
                <?php if (isAdmin()): ?>
                <form method="POST" class="bg-[#f7f6f6] p-6 rounded-lg border mb-6 no-print">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">Adicionar Nova Observação</h4>
                    <div><textarea name="texto_observacao" rows="4" class="w-full px-3 py-2 border rounded-lg" placeholder="Digite seu parecer ou observação aqui..." required></textarea></div>
                    <div class="flex justify-end mt-4"><button type="submit" name="submit_observacao" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md">Salvar Observação</button></div>
                </form>
                <?php endif; ?>
                <?php foreach ($observacoes as $obs): ?>
                <div class="border-b p-4 break-inside-avoid">
                    <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($obs['observacao'])); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Por <?php echo htmlspecialchars($obs['usuario_nome']); ?> em <?php echo converterTimestampParaLocal($obs['created_at']); ?></p>
                </div>
                <?php endforeach; ?>
                <?php if (empty($observacoes)): ?>
                    <p class="text-center text-gray-500 p-4">Nenhuma observação registada.</p>
                <?php endif; ?>
            </div>

           <div>
                <h3 class="text-xl font-bold text-gray-700 mb-4">Histórico de Atividades</h3>
                <div class="overflow-x-auto bg-white rounded-lg shadow-md border">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr class="bg-[#d9e3ec]">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Usuário</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($historico)): ?>
                                <tr><td colspan="3" class="text-center p-4 text-gray-500">Nenhum histórico de atividade para este pregão.</td></tr>
                            <?php else: ?>
                                <?php foreach($historico as $log): ?>
                                    <tr>
                                        <td class="px-5 py-4 border-b border-gray-200 text-sm"><?php echo converterTimestampParaLocal($log['created_at']); ?></td>
                                        <td class="px-5 py-4 border-b border-gray-200 text-sm"><?php echo htmlspecialchars($log['usuario_nome']); ?></td>
                                        <td class="px-5 py-4 border-b border-gray-200 text-sm"><?php echo htmlspecialchars($log['detalhes']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
    
    <!-- --- MODAL DE EDIÇÃO COM CAMPO LOTE --- -->
    <?php if (isAdmin()): ?>
    <div id="modal-edit-item" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden overflow-y-auto no-print">
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-2xl my-8">
            <div class="flex justify-between items-center"><h2 class="text-2xl font-bold mb-6">Editar Item da Proposta</h2><button class="close-modal-btn text-gray-500 text-3xl mb-6">&times;</button></div>
            <form id="form-edit-item" method="POST">
                <input type="hidden" name="edit_item_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="md:col-span-2"><label>Fornecedor</label>
                        <select name="edit_fornecedor_id" class="form-input" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($fornecedores_disponiveis as $fornecedor): ?><option value="<?php echo $fornecedor['id']; ?>"><?php echo htmlspecialchars($fornecedor['nome']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Nº do Lote (Opcional)</label><input type="text" name="edit_numero_lote" class="form-input" placeholder="Ex: Lote 01"></div>
                    <div><label>Nº do Item</label><input type="text" name="edit_numero_item" class="form-input" placeholder="Ex: 1, 2, 3..."></div>
                    <div class="md:col-span-2"><label>Descrição</label><input type="text" name="edit_descricao" class="form-input" required></div>
                    <div><label>Fabricante/Marca</label><input type="text" name="edit_fabricante" class="form-input"></div>
                    <div><label>Modelo</label><input type="text" name="edit_modelo" class="form-input"></div>
                    <div><label>Quantidade</label><input type="number" name="edit_quantidade" class="form-input" required></div>
                    <div><label>Valor Unitário (R$)</label><input type="number" step="0.01" name="edit_valor_unitario" class="form-input" required></div>
                    
                    <div class="md:col-span-2"><hr class="my-4"></div>

                    <div>
                        <label>Status do Item</label>
                        <select name="edit_status_item" class="form-input">
                            <?php foreach($status_item_list as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label>Motivo do Status (opcional)</label>
                        <textarea name="edit_status_motivo" rows="3" class="form-input"></textarea>
                    </div>
                </div>
                <div class="flex justify-end mt-4"><button type="submit" name="submit_edit_item" class="btn btn-success">Salvar Alterações</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="modal-confirm" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden no-print"><div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md"><h3 class="text-xl font-bold mb-4">Confirmar Ação</h3><p id="modal-confirm-message" class="text-gray-600 mb-6"></p><div class="flex justify-end gap-4"><button id="modal-confirm-cancel" class="btn btn-secondary">Cancelar</button><button id="modal-confirm-ok" class="btn btn-danger">Confirmar</button></div></div></div>
    <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-2 no-print"></div>
    <script src="js/script.js?v=2.29"></script> <!-- ATENÇÃO: Atualize a versão do JS se necessário -->
</body>
</html>