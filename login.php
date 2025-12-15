<?php
// Arquivo: login.php
// Página de login principal do sistema, corrigida para usar PDO e nomes de colunas corretos.

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui os arquivos de configuração e a classe de conexão com o banco de dados
require_once 'config.php';
require_once 'Database.php';

$mensagem = ''; // Variável para exibir mensagens ao usuário

// Verifica se a requisição é do tipo POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // Validação básica para evitar campos vazios
    if (empty($email) || empty($senha)) {
        $mensagem = "Por favor, preencha todos os campos.";
    } else {
        try {
            // Cria uma nova instância da classe Database e conecta
            $database = new Database();
            $pdo = $database->connect();
            
            // Prepara a consulta SQL para evitar injeção de SQL
            // As colunas 'nome' e 'senha' agora correspondem à sua tabela 'usuarios'
            $sql = "SELECT id, email, nome, senha, perfil FROM usuarios WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            
            // Executa a consulta com o email fornecido
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Verifica a senha digitada com a senha criptografada do banco de dados
                // A função password_verify() é a maneira segura de fazer isso
                if (password_verify($senha, $usuario['senha'])) {
                    // Login bem-sucedido, armazena informações na sessão
                    $_SESSION['user_id'] = $usuario['id'];
                    $_SESSION['user_nome'] = $usuario['nome'];
                    $_SESSION['user_perfil'] = $usuario['perfil'];
                    
                    // Redireciona para a página principal (dashboard)
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $mensagem = "Senha incorreta.";
                }
            } else {
                $mensagem = "Email não encontrado.";
            }
        } catch (PDOException $e) {
            // Captura e loga erros de conexão ou consulta do banco de dados
            error_log("Erro no login: " . $e->getMessage());
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="css/style.css?v=2.29">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen bg-[#d9e3ec]">
    <div class="bg-[#f7f6f6] p-8 rounded-lg shadow-xl w-full max-w-sm">
        <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Login</h1>
        <?php if ($mensagem): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($mensagem); ?></p>
            </div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                <input type="email" id="email" name="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-blue-200" required>
            </div>
            <div class="mb-6">
                <label for="senha" class="block text-gray-700 text-sm font-semibold mb-2">Senha</label>
                <input type="password" id="senha" name="senha"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-blue-200" required>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg shadow-md hover:bg-blue-700 transition-colors">
                Entrar
            </button>
        </form>
        <p class="mt-4 text-center text-gray-500 text-sm">
            Não tem uma conta? <a href="register.php" class="text-blue-600 hover:underline">Cadastre-se aqui</a>.
        </p>
    </div>
</body>

</html>
