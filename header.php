<?php
// ==============================================
// ARQUIVO: header.php
// CABEÇALHO REUTILIZÁVEL COM SISTEMA DE NOTIFICAÇÃO
// ==============================================

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inicializa as variáveis de notificação
$notificacoes_nao_lidas = [];
$contagem_nao_lidas = 0;

// Verifica se o usuário está logado para buscar as notificações
if (isset($_SESSION['user_id'])) {
    try {
        // A conexão PDO deve ser passada do arquivo que inclui este header
        // Mas, para garantir, podemos criar uma nova conexão se não existir
        if (!isset($pdo)) {
            $db_header = new Database();
            $pdo_header = $db_header->connect();
        } else {
            $pdo_header = $pdo;
        }
        
        $user_id_header = $_SESSION['user_id'];
        
        $stmt_notif = $pdo_header->prepare(
            "SELECT id, mensagem, link, created_at FROM notificacoes WHERE usuario_destino_id = ? AND lida = 0 ORDER BY created_at DESC LIMIT 5"
        );
        $stmt_notif->execute([$user_id_header]);
        $notificacoes_nao_lidas = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);

        $stmt_count = $pdo_header->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_destino_id = ? AND lida = 0");
        $stmt_count->execute([$user_id_header]);
        $contagem_nao_lidas = $stmt_count->fetchColumn();

    } catch (Exception $e) {
        error_log("Erro ao buscar notificações no header: " . $e->getMessage());
    }
}
?>

<header class="flex flex-wrap justify-between items-center mb-6">
    <img src="imagens/LOGO-FR.webp" alt="logo FR" width="140" height="75">
    <h1 class="text-3xl font-bold text-gray-800">
        <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Painel de Gestão'; ?>
    </h1>
    <div class="flex items-center space-x-4">
        <span>Olá, <strong><?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário'); ?>!</strong></span>

       <!-- Ícone de Notificações -->
        <!-- ============================================== -->
        <!-- ADICIONADO data-count PARA O JAVASCRIPT -->
        <!-- ============================================== -->
        <div class="relative" id="notificacoes-container" data-count="<?php echo $contagem_nao_lidas; ?>">
            <button id="notificacoes-btn" class="relative text-gray-600 hover:text-blue-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <?php if ($contagem_nao_lidas > 0): ?>
                    <span id="notificacoes-badge" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                        <?php echo $contagem_nao_lidas; ?>
                    </span>
                <?php endif; ?>
            </button>
            <!-- Dropdown de Notificações -->
            <div id="notificacoes-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl overflow-hidden z-20 hidden">
                <div class="py-2 px-4 text-sm font-semibold text-gray-700 border-b">Notificações</div>
                <div class="divide-y">
                    <?php if (empty($notificacoes_nao_lidas)): ?>
                        <p class="text-center text-gray-500 py-4">Nenhuma nova notificação.</p>
                    <?php else: ?>
                        <?php foreach ($notificacoes_nao_lidas as $notif): ?>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="block py-3 px-4 hover:bg-gray-100">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($notif['mensagem']); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo converterTimestampParaLocal($notif['created_at']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a href="../logout.php" class="btn btn-danger btn-sm">Sair</a>
        
    </div>
</header>
