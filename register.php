<?php
require_once 'config.php';
require_once 'Database.php';
$mensagem = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $token = $_POST['token'] ?? '';
    
    if (empty($nome) || empty($email) || empty($senha) || empty($token)) {
        $mensagem = "Por favor, preencha todos os campos, incluindo o token de validação.";
    } else {
        try {
            $database = new Database();
            $pdo = $database->connect();
            
            $sql_token = "SELECT id, created_at FROM registration_tokens WHERE token = ? AND is_used = 0";
            $stmt_token = $pdo->prepare($sql_token);
            $stmt_token->execute([$token]);
            $token_data = $stmt_token->fetch();

            if (!$token_data) {
                $mensagem = "Token de validação inválido ou já utilizado.";
            } else {
                $token_time = new DateTime($token_data['created_at'], new DateTimeZone('UTC'));
                $now = new DateTime();
                $interval = $token_time->diff($now);
                $minutes_passed = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

                if ($minutes_passed > 30) {
                    $mensagem = "Token de validação expirado. Por favor, solicite um novo.";
                } else {
                    $sql_check = "SELECT id FROM usuarios WHERE email = ?";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute([$email]);
                    
                    if ($stmt_check->rowCount() > 0) {
                        $mensagem = "Este e-mail já está registado. Tente outro.";
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                            $sql_insert = "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)";
                            $stmt_insert = $pdo->prepare($sql_insert);
                            $stmt_insert->execute([$nome, $email, $senha_hash, PERM_PADRAO]);
                            $usuario_id = $pdo->lastInsertId();

                            $sql_update_token = "UPDATE registration_tokens SET is_used = 1 WHERE id = ?";
                            $stmt_update_token = $pdo->prepare($sql_update_token);
                            $stmt_update_token->execute([$token_data['id']]);

                            $pdo->commit();

                            $mensagem = "Utilizador registado com sucesso! Será redirecionado para a página de login.";
                            logActivity($pdo, $usuario_id, 'autenticacao', 'REGISTER', $usuario_id, "Novo utilizador registado: $nome");
                            header("Refresh: 3; URL=login.html");

                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro no registo: " . $e->getMessage());
            $mensagem = "Ocorreu um erro interno. Tente novamente mais tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Gestão de Pregões</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.28">
    <style>
        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
    </style>
</head>
<body class="bg-[#d9e3ec]">
    <div class="register-container p-4">
        <div class="bg-[#f7f6f6] p-8 rounded-xl shadow-md w-full max-w-sm">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Registro</h1>
            </div>
            <?php if ($mensagem): ?>
                <div class="bg-<?php echo strpos($mensagem, 'sucesso') !== false ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo strpos($mensagem, 'sucesso') !== false ? 'green' : 'red'; ?>-500 text-<?php echo strpos($mensagem, 'sucesso') !== false ? 'green' : 'red'; ?>-700 p-4 mb-4 rounded-md" role="alert">
                    <p><?php echo htmlspecialchars($mensagem); ?></p>
                </div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="nome" class="block text-gray-700 text-sm font-semibold mb-2">Nome de Utilizador</label>
                    <input type="text" id="nome" name="nome" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">E-mail</label>
                    <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="senha" class="block text-gray-700 text-sm font-semibold mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-6">
                    <label for="token" class="block text-gray-700 text-sm font-semibold mb-2">Token de Validação</label>
                    <input type="text" id="token" name="token" placeholder="Código de 6 dígitos" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    Registar
                </button>
            </form>
            <p class="mt-6 text-center text-gray-600">
                Já tem uma conta? <a href="login.html" class="font-semibold text-blue-600 hover:underline">Faça login aqui</a>
            </p>
        </div>
    </div>
</body>
</html>