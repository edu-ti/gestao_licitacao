<?php
// ==============================================
// ARQUIVO: licencas.php
// GESTÃO DE LICENÇAS E CERTIDÕES POR EMPRESA
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';

$mensagem = '';
$erro = '';

try {
    $db = new Database();
    $pdo = $db->connect();

    $current_user_id = $_SESSION['user_id'];
    $is_admin = isAdmin();

    $upload_dir = __DIR__ . '/uploads/licencas/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filtro_fornecedor = isset($_GET['fornecedor']) ? intval($_GET['fornecedor']) : 0;

    if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_admin) {

        if (isset($_POST['submit_licenca'])) {
            $fornecedor_id = !empty($_POST['fornecedor_id']) ? intval($_POST['fornecedor_id']) : null;
            $titulo = trim($_POST['titulo'] ?? '');
            $data_vencimento = !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null;
            $sem_validade = isset($_POST['sem_validade']) ? 1 : 0;

            if ($sem_validade) {
                $data_vencimento = null;
            }

            if (!empty($_FILES['arquivo']['name']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
                $nome_arquivo = 'licenca_' . time() . '_' . uniqid() . '.' . $ext;
                $destino = $upload_dir . $nome_arquivo;
                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                    $arquivo_path = 'uploads/licencas/' . $nome_arquivo;
                }
            }

            if (!empty($titulo) && $fornecedor_id) {
                $sql = "INSERT INTO licencas_certidoes (fornecedor_id, titulo, arquivo_path, data_vencimento, sem_validade) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fornecedor_id, $titulo, $arquivo_path, $data_vencimento, $sem_validade]);
                $mensagem = "Documento cadastrado com sucesso!";
            } else {
                $erro = "Preencha o titulo e selecione a empresa.";
            }
        }

        if (isset($_POST['excluir_id'])) {
            $id = intval($_POST['excluir_id']);
            $stmt = $pdo->prepare("SELECT arquivo_path FROM licencas_certidoes WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc && !empty($doc['arquivo_path'])) {
                $caminho = __DIR__ . '/' . $doc['arquivo_path'];
                if (file_exists($caminho)) {
                    unlink($caminho);
                }
            }
            $pdo->prepare("DELETE FROM licencas_certidoes WHERE id = ?")->execute([$id]);
            $mensagem = "Documento excluido com sucesso!";
        }

        if (isset($_POST['submit_editar'])) {
            $id = intval($_POST['edit_id']);
            $fornecedor_id = !empty($_POST['fornecedor_id']) ? intval($_POST['fornecedor_id']) : null;
            $titulo = trim($_POST['titulo'] ?? '');
            $data_vencimento = !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null;
            $sem_validade = isset($_POST['sem_validade']) ? 1 : 0;

            if ($sem_validade) {
                $data_vencimento = null;
            }

            $sql = "UPDATE licencas_certidoes SET fornecedor_id = ?, titulo = ?, data_vencimento = ?, sem_validade = ?, notificado = 0, notificado_vencido = 0 WHERE id = ?";
            $pdo->prepare($sql)->execute([$fornecedor_id, $titulo, $data_vencimento, $sem_validade, $id]);

            if (!empty($_FILES['arquivo']['name']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                $stmt = $pdo->prepare("SELECT arquivo_path FROM licencas_certidoes WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old && !empty($old['arquivo_path'])) {
                    $caminho = __DIR__ . '/' . $old['arquivo_path'];
                    if (file_exists($caminho)) {
                        unlink($caminho);
                    }
                }
                $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
                $nome_arquivo = 'licenca_' . time() . '_' . uniqid() . '.' . $ext;
                $destino = $upload_dir . $nome_arquivo;
                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) {
                    $pdo->prepare("UPDATE licencas_certidoes SET arquivo_path = ? WHERE id = ?")->execute(['uploads/licencas/' . $nome_arquivo, $id]);
                }
            }
            $mensagem = "Documento atualizado com sucesso!";
        }

        header("Location: licencas.php?fornecedor=" . $filtro_fornecedor);
        exit();
    }

    $fornecedores = $pdo->query("SELECT id, nome, nome_fantasia, cnpj FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    $where = '';
    $params = [];
    if ($filtro_fornecedor > 0) {
        $where = "WHERE l.fornecedor_id = ?";
        $params[] = $filtro_fornecedor;
    }

    $sql_licencas = "
        SELECT l.*, f.nome AS empresa_nome, f.nome_fantasia, f.cnpj
        FROM licencas_certidoes l
        LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
        $where
        ORDER BY f.nome ASC, l.data_vencimento ASC
    ";
    $stmt = $pdo->prepare($sql_licencas);
    $stmt->execute($params);
    $licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hoje = date('Y-m-d');
    $daqui_30 = date('Y-m-d', strtotime('+30 days'));

    $sql_vencidas = "SELECT COUNT(*) FROM licencas_certidoes WHERE fornecedor_id IS NOT NULL AND sem_validade = 0 AND data_vencimento IS NOT NULL AND data_vencimento < ?";
    $count_vencidas = $pdo->prepare($sql_vencidas);
    $count_vencidas->execute([$hoje]);
    $total_vencidas = $filtro_fornecedor > 0 ? 0 : $count_vencidas->fetchColumn();

    $sql_vencendo = "SELECT COUNT(*) FROM licencas_certidoes WHERE fornecedor_id IS NOT NULL AND sem_validade = 0 AND data_vencimento IS NOT NULL AND data_vencimento BETWEEN ? AND ?";
    $count_vencendo = $pdo->prepare($sql_vencendo);
    $count_vencendo->execute([$hoje, $daqui_30]);
    $total_vencendo = $filtro_fornecedor > 0 ? 0 : $count_vencendo->fetchColumn();

    $sql_ultimos_alertas = "SELECT mensagem, data_criacao FROM alertas_licencas ORDER BY data_criacao DESC LIMIT 10";
    $ultimos_alertas = $pdo->query($sql_ultimos_alertas)->fetchAll(PDO::FETCH_ASSOC);

    $agrupadas = [];
    foreach ($licencas as $lic) {
        $key = $lic['fornecedor_id'] ?? 'sem_empresa';
        if (!isset($agrupadas[$key])) {
            $agrupadas[$key] = [
                'empresa_nome' => $lic['empresa_nome'] ?? 'Sem empresa definida',
                'nome_fantasia' => $lic['nome_fantasia'] ?? '',
                'cnpj' => $lic['cnpj'] ?? '',
                'fornecedor_id' => $lic['fornecedor_id'],
                'documentos' => []
            ];
        }
        $agrupadas[$key]['documentos'][] = $lic;
    }

} catch (\Throwable $e) {
    $erro = "Erro ao carregar dados: " . $e->getMessage();
    error_log("licencas.php: " . $e->getMessage());
    $fornecedores = [];
    $agrupadas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licencas & Certidoes - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.36">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grupo-card {
            transition: all 0.2s ease;
        }

        .grupo-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .status-vencido {
            border-left: 4px solid #ef4444;
        }

        .status-atenção {
            border-left: 4px solid #f59e0b;
        }

        .status-ok {
            border-left: 4px solid #16a34a;
        }

        .sem-vencimento {
            border-left: 4px solid #9ca3af;
        }
    </style>
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto">
        <?php include 'header.php'; ?>

        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-certificate text-blue-900 mr-2"></i>Licencas & Certidoes
                    </h2>
                    <p class="text-gray-500 mt-1">Gerencie documentos por empresa</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="abrirModal()" class="btn btn-primary" <?php echo !$is_admin ? 'disabled title="Apenas administradores"' : ''; ?>>
                        <i class="fas fa-plus mr-2"></i> Nova Licenca/Certidao
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar
                    </a>
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="mt-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg"><?php echo $erro; ?></div>
            <?php endif; ?>
            <?php if ($mensagem): ?>
                <div class="mt-4 bg-green-50 border border-green-200 text-green-700 p-3 rounded-lg"><?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <?php if ($total_vencidas > 0): ?>
                <div
                    class="mt-4 bg-red-50 border border-red-300 text-red-800 p-4 rounded-lg flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    <div>
                        <strong>Atencao!</strong> Existem <strong><?php echo $total_vencidas; ?></strong>
                        licenca(s)/certidao(oes) <strong>vencida(s)</strong>.
                        <?php if ($total_vencendo > 0): ?>
                            E <strong><?php echo $total_vencendo; ?></strong> documento(s) vence(m) nos proximos 30 dias.
                        <?php endif; ?>
                        <!--<a href="cron_alertas_licencas.php" class="text-red-600 underline text-sm ml-2">Forcar notificacao agora</a>-->
                    </div>
                </div>
            <?php elseif ($total_vencendo > 0): ?>
                <div
                    class="mt-4 bg-yellow-50 border border-yellow-300 text-yellow-800 p-4 rounded-lg flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    <div>
                        <strong>Atencao!</strong> <strong><?php echo $total_vencendo; ?></strong> licenca(s)/certidao(oes)
                        vence(m) nos proximos 30 dias.
                        <!--<a href="cron_alertas_licencas.php" class="text-yellow-600 underline text-sm ml-2">Forcar notificacao agora</a>-->
                    </div>
                </div>
            <?php endif; ?>

            <form method="GET" class="flex flex-wrap items-end gap-3 mt-4 pt-4 border-t border-gray-100">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Filtrar por empresa</label>
                    <select name="fornecedor" onchange="this.form.submit()"
                        class="px-3 py-2 border rounded-lg bg-white">
                        <option value="0">Todas as empresas</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?php echo $f['id']; ?>" <?php echo $filtro_fornecedor == $f['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filtro_fornecedor > 0): ?>
                    <a href="licencas.php" class="text-sm text-blue-600 hover:underline mb-1">Limpar filtro</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($agrupadas)): ?>
            <div class="bg-white p-10 rounded-lg shadow text-center text-gray-500">
                <i class="fas fa-folder-open text-4xl mb-4 text-gray-300"></i>
                <p class="text-lg">Nenhum documento cadastrado.</p>
                <p class="text-sm mt-2">Clique em "Nova Licenca/Certidao" para adicionar.</p>
            </div>
        <?php else: ?>
            <?php foreach ($agrupadas as $grupo): ?>
                <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                    <div
                        class="bg-gray-50 px-6 py-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-building text-blue-600 mr-2"></i>
                                <?php echo htmlspecialchars($grupo['empresa_nome']); ?>
                            </h3>
                            <?php if (!empty($grupo['cnpj'])): ?>
                                <span class="text-xs text-gray-500 ml-8">CNPJ:
                                    <?php echo htmlspecialchars($grupo['cnpj']); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm text-gray-500 bg-white px-3 py-1 rounded-full border">
                            <?php echo count($grupo['documentos']); ?> documento(s)
                        </span>
                    </div>
                    <div class="p-4 space-y-2">
                        <?php foreach ($grupo['documentos'] as $doc):
                            $hoje = new DateTime('today');
                            $status_classe = 'sem-vencimento';
                            $badge_texto = 'Sem vencimento';
                            $badge_cor = 'bg-gray-100 text-gray-600';

                            if (!empty($doc['sem_validade'])) {
                                $status_classe = 'status-ok';
                                $badge_texto = 'Prazo indeterminado';
                                $badge_cor = 'bg-blue-100 text-blue-700';
                            } elseif (!empty($doc['data_vencimento'])) {
                                try {
                                    $vencimento = new DateTime($doc['data_vencimento']);
                                    $dias = $hoje->diff($vencimento)->days;
                                    $dias = $vencimento < $hoje ? -$dias : $dias;

                                    if ($vencimento < $hoje) {
                                        $status_classe = 'status-vencido';
                                        $badge_texto = 'VENCIDO ha ' . abs($dias) . ' dia(s)';
                                        $badge_cor = 'bg-red-100 text-red-700';
                                    } elseif ($dias <= 30) {
                                        $status_classe = 'status-atencao';
                                        $badge_texto = 'Vence em ' . $dias . ' dia(s)';
                                        $badge_cor = 'bg-yellow-100 text-yellow-700';
                                    } else {
                                        $status_classe = 'status-ok';
                                        $badge_texto = 'Vence em ' . $dias . ' dia(s)';
                                        $badge_cor = 'bg-green-100 text-green-700';
                                    }
                                } catch (\Throwable $e) {
                                }
                            }
                            ?>
                            <div
                                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 p-3 rounded-lg <?php echo $status_classe; ?> bg-gray-50">
                                <div class="flex-grow">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="font-semibold text-gray-800"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full font-medium <?php echo $badge_cor; ?>"><?php echo $badge_texto; ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php if (!empty($doc['data_vencimento'])): ?>
                                            Vencimento: <?php echo date('d/m/Y', strtotime($doc['data_vencimento'])); ?> &middot;
                                        <?php endif; ?>
                                        Cadastrado em: <?php echo date('d/m/Y H:i', strtotime($doc['data_upload'])); ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <?php if (!empty($doc['arquivo_path'])): ?>
                                        <a href="<?php echo $doc['arquivo_path']; ?>" target="_blank" class="btn btn-secondary btn-sm"
                                            title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic mr-1">Sem arquivo</span>
                                    <?php endif; ?>
                                    <?php if ($is_admin): ?>
                                        <button
                                            onclick="abrirEdicao(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titulo'], ENT_QUOTES); ?>', '<?php echo $doc['data_vencimento'] ?? ''; ?>', <?php echo $doc['fornecedor_id'] ?? 0; ?>, <?php echo $doc['sem_validade'] ?? 0; ?>)"
                                            class="btn btn-secondary btn-sm" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirmarExclusao(<?php echo $doc['id']; ?>)" class="btn btn-danger btn-sm"
                                            title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="modal-licenca"
        class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg mx-4">
            <h3 id="modal-titulo" class="text-xl font-bold mb-4">Nova Licenca/Certidao</h3>
            <form method="POST" enctype="multipart/form-data" id="form-licenca">
                <input type="hidden" name="edit_id" id="edit_id" value="">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa *</label>
                    <select name="fornecedor_id" id="fornecedor_id_select" required
                        class="w-full px-3 py-2 border rounded-lg bg-white">
                        <option value="">Selecione a empresa</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome']); ?>
                                <?php echo !empty($f['cnpj']) ? ' (' . htmlspecialchars($f['cnpj']) . ')' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titulo do Documento *</label>
                    <input type="text" name="titulo" id="titulo_input" required
                        class="w-full px-3 py-2 border rounded-lg" placeholder="Ex: Alvara de Localizacao, CNDT...">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Vencimento</label>
                    <input type="date" name="data_vencimento" id="data_vencimento_input"
                        class="w-full px-3 py-2 border rounded-lg">
                </div>

                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="sem_validade" id="sem_validade_check" value="1" onchange="toggleDataVencimento()"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                        <span class="text-sm font-medium text-gray-700">Nao tem validade (prazo indeterminado)</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo (PDF, imagem)</label>
                    <input type="file" name="arquivo" id="arquivo_input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        class="w-full px-3 py-2 border rounded-lg bg-white">
                    <p id="arquivo_atual" class="text-xs text-gray-400 mt-1 hidden"></p>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" name="submit_licenca" id="btn_submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <form id="form-excluir" method="POST" class="hidden">
        <input type="hidden" name="excluir_id" id="excluir_id_input">
    </form>

    <script>
        function toggleDataVencimento() {
            var checked = document.getElementById('sem_validade_check').checked;
            document.getElementById('data_vencimento_input').disabled = checked;
            if (checked) {
                document.getElementById('data_vencimento_input').value = '';
            }
        }

        function abrirModal() {
            document.getElementById('form-licenca').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('modal-titulo').textContent = 'Nova Licenca/Certidao';
            document.getElementById('btn_submit').name = 'submit_licenca';
            document.getElementById('btn_submit').textContent = 'Salvar';
            document.getElementById('arquivo_atual').classList.add('hidden');
            document.getElementById('fornecedor_id_select').value = '<?php echo $filtro_fornecedor; ?>';
            document.getElementById('sem_validade_check').checked = false;
            document.getElementById('data_vencimento_input').disabled = false;
            document.getElementById('modal-licenca').classList.remove('hidden');
        }

        function abrirEdicao(id, titulo, vencimento, fornecedorId, semValidade) {
            document.getElementById('edit_id').value = id;
            document.getElementById('titulo_input').value = titulo;
            document.getElementById('data_vencimento_input').value = vencimento;
            document.getElementById('fornecedor_id_select').value = fornecedorId;
            document.getElementById('modal-titulo').textContent = 'Editar Licenca/Certidao';
            document.getElementById('btn_submit').name = 'submit_editar';
            document.getElementById('btn_submit').textContent = 'Atualizar';
            document.getElementById('form-licenca').setAttribute('action', 'licencas.php?fornecedor=<?php echo $filtro_fornecedor; ?>');

            var semVal = (semValidade == 1);
            document.getElementById('sem_validade_check').checked = semVal;
            document.getElementById('data_vencimento_input').disabled = semVal;
            if (semVal) {
                document.getElementById('data_vencimento_input').value = '';
            }

            document.getElementById('modal-licenca').classList.remove('hidden');
        }

        function fecharModal() {
            document.getElementById('modal-licenca').classList.add('hidden');
            document.getElementById('form-licenca').setAttribute('action', '');
        }

        function confirmarExclusao(id) {
            if (confirm('Tem certeza que deseja excluir este documento?')) {
                document.getElementById('excluir_id_input').value = id;
                document.getElementById('form-excluir').submit();
            }
        }

        document.getElementById('modal-licenca').addEventListener('click', function (e) {
            if (e.target === this) {
                fecharModal();
            }
        });
    </script>
</body>

</html>