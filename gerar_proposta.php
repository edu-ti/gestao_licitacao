<?php
require_once 'auth.php';
require_once 'Database.php';

if (!isset($_GET['pregao_id']) || !isset($_GET['fornecedor_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pregao_id = (int) $_GET['pregao_id'];
$fornecedor_id = (int) $_GET['fornecedor_id'];

try {
    $db = new Database();
    $pdo = $db->connect();

    // Buscar Pregão
    $stmt_pregao = $pdo->prepare("SELECT * FROM pregoes WHERE id = ?");
    $stmt_pregao->execute([$pregao_id]);
    $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

    // Buscar Fornecedor
    $stmt_fornecedor = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ?");
    $stmt_fornecedor->execute([$fornecedor_id]);
    $fornecedor = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);

    if (!$pregao || !$fornecedor) {
        die("Pregão ou Fornecedor não encontrado.");
    }

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerar Proposta -
        <?php echo htmlspecialchars($fornecedor['nome']); ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8 min-h-screen">
    <div class="container mx-auto max-w-3xl bg-white p-6 sm:p-8 rounded-lg shadow-lg">
        <div class="flex justify-between items-center border-b pb-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Gerar Proposta de Preço</h1>
            <a href="pregao_detalhes.php?id=<?php echo $pregao_id; ?>" class="btn btn-secondary btn-sm">&larr;
                Voltar</a>
        </div>

        <div class="bg-blue-50 text-blue-800 p-4 rounded-lg mb-6 text-sm">
            <p><strong>Edital:</strong>
                <?php echo htmlspecialchars($pregao['numero_edital']); ?> (
                <?php echo htmlspecialchars($pregao['orgao_comprador']); ?>)
            </p>
            <p><strong>Fornecedor:</strong>
                <?php echo htmlspecialchars($fornecedor['nome']); ?>
            </p>
        </div>

        <form action="imprimir_proposta.php" method="POST" target="_blank" class="space-y-6">
            <input type="hidden" name="pregao_id" value="<?php echo $pregao_id; ?>">
            <input type="hidden" name="fornecedor_id" value="<?php echo $fornecedor_id; ?>">

            <div>
                <h3 class="text-lg font-bold text-gray-700 border-b pb-2 mb-4">Dados do Representante Legal</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome do Representante *</label>
                        <input type="text" name="rep_nome" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nacionalidade</label>
                        <input type="text" name="rep_nacionalidade" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            value="BRASILEIRA">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado Civil</label>
                        <input type="text" name="rep_estado_civil" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            value="CASADO">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Função/Cargo *</label>
                        <input type="text" name="rep_funcao" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            value="DIRETOR" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Endereço Residencial *</label>
                        <input type="text" name="rep_endereco" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            placeholder="Rua, Número, Bairro, Cidade, Estado, CEP" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CPF *</label>
                        <input type="text" name="rep_cpf" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">R.G. / Órgão Expedidor *</label>
                        <input type="text" name="rep_rg" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">E-mail do Representante</label>
                        <input type="email" name="rep_email" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold text-gray-700 border-b pb-2 mb-4">Dados Bancários da Empresa e Validade
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Endereço da Empresa *</label>
                        <input type="text" name="emp_endereco" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Telefone(s) da Empresa *</label>
                        <input type="text" name="emp_telefone" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">E-mail da Empresa *</label>
                        <input type="email" name="emp_email" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Banco Nº (Ex: 033) *</label>
                        <input type="text" name="banco_num" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome do Banco *</label>
                        <input type="text" name="banco_nome" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            placeholder="Ex: SANTANDER" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Agência Nº *</label>
                        <input type="text" name="agencia_num" class="mt-1 w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome da Agência</label>
                        <input type="text" name="agencia_nome" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Conta Corrente Nº *</label>
                        <input type="text" name="conta_corrente" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Validade da Proposta *</label>
                        <input type="text" name="validade_proposta" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            value="12 (doze) meses corrigidos" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data e Local da Assinatura *</label>
                        <input type="text" name="data_assinatura" class="mt-1 w-full px-3 py-2 border rounded-lg"
                            value="Recife, <?php setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
                            echo date('d') . ' de ' . gmstrftime('%B') . ' de ' . date('Y'); ?>"
                            required>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t">
                <button type="submit" class="btn btn-primary px-8 py-3 text-lg">
                    <i class="fas fa-print mr-2"></i> Gerar Impressão da Proposta
                </button>
            </div>
        </form>
    </div>
</body>

</html>