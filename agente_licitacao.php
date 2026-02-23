<?php
require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';

if (!isset($_GET['anexo_id']) || !filter_var($_GET['anexo_id'], FILTER_VALIDATE_INT)) {
    header("Location: dashboard.php");
    exit();
}

$anexo_id = $_GET['anexo_id'];
$anexo = null;
$pregao = null;
$erro_fatal = '';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Buscar anexo
    $stmt = $pdo->prepare("SELECT * FROM anexos_pregao WHERE id = ?");
    $stmt->execute([$anexo_id]);
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        $erro_fatal = "Anexo não encontrado.";
    } else {
        // Buscar dados do pregão relacionado
        $stmt_pregao = $pdo->prepare("SELECT numero_edital, orgao_comprador FROM pregoes WHERE id = ?");
        $stmt_pregao->execute([$anexo['pregao_id']]);
        $pregao = $stmt_pregao->fetch(PDO::FETCH_ASSOC);

        // Buscar histórico
        $usuario_id = $_SESSION['user_id'] ?? 0;
        $stmt_hist = $pdo->prepare("SELECT * FROM agente_historico WHERE anexo_id = ? AND usuario_id = ? ORDER BY data_hora DESC");
        $stmt_hist->execute([$anexo_id, $usuario_id]);
        $historico = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $erro_fatal = "Erro no banco de dados: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agente Licitatório IA -
        <?php echo APP_NAME; ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.29">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Marked.js para renderizar Markdown da IA -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- SweetAlert2 para alertas bonitos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .markdown-body h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .markdown-body h2 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .markdown-body h3 {
            font-size: 1.125rem;
            font-weight: bold;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .markdown-body p {
            margin-bottom: 0.75rem;
        }

        .markdown-body ul {
            list-style-type: disc;
            margin-left: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .markdown-body ol {
            list-style-type: decimal;
            margin-left: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .markdown-body strong {
            font-weight: 600;
            color: #111827;
        }

        .markdown-body pre {
            background-color: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin-bottom: 0.75rem;
        }

        .markdown-body code {
            font-family: monospace;
            background-color: #f3f4f6;
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
        }

        .markdown-body blockquote {
            border-left: 4px solid #e5e7eb;
            padding-left: 1rem;
            color: #4b5563;
        }

        .loader {
            border-top-color: #3b82f6;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }

        @keyframes spinner {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8 flex flex-col min-h-screen">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg flex-grow flex flex-col">

        <?php
        $page_title = 'Agente Licitatório (IA)';
        include 'header.php';
        ?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4 gap-4">
            <div>
                <h1 class="text-3xl font-bold font-sans text-gray-800 flex items-center gap-2">
                    <i class="fas fa-robot text-purple-600"></i> Agente Licitatório
                </h1>
                <?php if ($anexo): ?>
                    <p class="text-gray-600 mt-1">
                        Analisando arquivo: <strong>
                            <?php echo htmlspecialchars($anexo['nome_original']); ?>
                        </strong>
                        (Pregão:
                        <?php echo htmlspecialchars($pregao['numero_edital'] ?? ''); ?> -
                        <?php echo htmlspecialchars($pregao['orgao_comprador'] ?? ''); ?>)
                    </p>
                <?php endif; ?>
            </div>
            <a href="pregao_detalhes.php?id=<?php echo htmlspecialchars($anexo['pregao_id'] ?? ''); ?>"
                class="btn btn-secondary whitespace-nowrap">&larr; Voltar ao Pregão</a>
        </div>

        <?php if (!empty($erro_fatal)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                <p>
                    <?php echo htmlspecialchars($erro_fatal); ?>
                </p>
            </div>
        <?php elseif ($anexo): ?>

            <div class="flex flex-col md:flex-row gap-6 flex-grow">
                <!-- COLUNA ESQUERDA: Controles e Prompts -->
                <div class="w-full md:w-1/3 flex flex-col gap-4">

                    <!-- Ações Rápidas -->
                    <div class="bg-gray-50 border rounded-lg p-5">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 border-b pb-2">Ações Rápidas
                        </h3>
                        <div class="flex flex-col gap-2">
                            <button onclick="enviarPrompt('resumo')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-purple-400 hover:bg-purple-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-file-alt text-purple-500 w-5"></i>
                                <span>Resumir Edital<br><small class="text-gray-500">Principais prazos e
                                        objetos</small></span>
                            </button>
                            <button onclick="enviarPrompt('riscos')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-red-400 hover:bg-red-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-exclamation-triangle text-red-500 w-5"></i>
                                <span>Análise de Riscos<br><small class="text-gray-500">Pontos de atenção e
                                        exigências</small></span>
                            </button>
                            <button onclick="enviarPrompt('esclarecimento')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-question-circle text-blue-500 w-5"></i>
                                <span>Pedido de Esclarecimento<br><small class="text-gray-500">Minuta para tirar
                                        dúvidas</small></span>
                            </button>
                            <button onclick="enviarPrompt('impugnacao')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-orange-400 hover:bg-orange-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-gavel text-orange-500 w-5"></i>
                                <span>Elaborar Impugnação<br><small class="text-gray-500">Minuta contra falhas do
                                        edital</small></span>
                            </button>
                            <button onclick="enviarPrompt('intencao_recurso')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-hand-paper text-indigo-500 w-5"></i>
                                <span>Intenção de Recurso<br><small class="text-gray-500">Texto curto para
                                        intenção</small></span>
                            </button>
                            <button onclick="enviarPrompt('recurso')"
                                class="prompt-btn text-left px-4 py-3 bg-white border border-gray-200 hover:border-teal-400 hover:bg-teal-50 rounded-lg shadow-sm transition-all focus:outline-none flex items-center gap-3">
                                <i class="fas fa-balance-scale text-teal-500 w-5"></i>
                                <span>Recurso Administrativo<br><small class="text-gray-500">Peça recursal
                                        completa</small></span>
                            </button>
                        </div>
                    </div>

                    <!-- Análise de Catálogo (Especialista em Produtos) -->
                    <div class="bg-gray-50 border rounded-lg p-5 mt-4">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 border-b pb-2">Análise de
                            Catálogo (Produto)</h3>
                        <p class="text-xs text-gray-500 mb-3">Valide se o catálogo atende ao Edital e gere
                            impugnação/recurso.</p>

                        <div class="flex flex-col gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Descritivo exigido no
                                    Edital/TR</label>
                                <textarea id="descritivo_produto"
                                    class="w-full min-h-[80px] p-2 border rounded-lg resize-none focus:ring-2 focus:ring-indigo-500 outline-none select-text text-sm"
                                    placeholder="Ex: Cadeira ergonômica com regulagem de altura e encosto inclinado..."></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Catálogo do Produto
                                    (PDF/Imagem/Doc)</label>
                                <input type="file" id="arquivo_catalogo" accept=".pdf,.doc,.docx,.txt"
                                    class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border p-1 rounded-lg bg-white">
                            </div>

                            <button onclick="analisarCatalogo()"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg font-semibold flex justify-center items-center gap-2 transition-colors mt-1">
                                <i class="fas fa-search-plus"></i> Analisar Produto & Gerar Peça
                            </button>
                        </div>
                    </div>

                    <!-- Chat Livre -->
                    <div class="bg-gray-50 border rounded-lg p-5 mt-4">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 border-b pb-2">Gerador
                            Personalizado</h3>
                        <p class="text-xs text-gray-500 mb-3">Converse com a IA para esclarecer dúvidas específicas ou pedir
                            um documento moldado.</p>

                        <textarea id="chat_input"
                            class="w-full min-h-[120px] p-3 border rounded-lg resize-none focus:ring-2 focus:ring-purple-500 outline-none select-text text-sm"
                            placeholder="O que você precisa sobre este edital?"></textarea>

                        <button onclick="enviarPromptLivre()"
                            class="mt-3 w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg font-semibold flex justify-center items-center gap-2 transition-colors">
                            <i class="fas fa-paper-plane"></i> Enviar para a IA
                        </button>
                    </div>

                    <!-- Histórico -->
                    <?php if (!empty($historico)): ?>
                        <div class="bg-gray-50 border rounded-lg p-5 mt-4 flex-grow overflow-y-auto max-h-[400px]">
                            <h3
                                class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 border-b pb-2 flex items-center gap-2">
                                <i class="fas fa-history text-gray-500"></i> Histórico de Conversas
                            </h3>
                            <div class="flex flex-col gap-3">
                                <?php foreach ($historico as $item): ?>
                                    <div class="bg-white border rounded-lg p-3 text-sm cursor-pointer hover:bg-gray-100 transition-colors"
                                        onclick="carregarHistorico(<?php echo htmlspecialchars(json_encode($item['resposta_ia'])); ?>)">
                                        <div class="text-xs text-gray-400 mb-1 flex justify-between">
                                            <span><i class="fas fa-calendar-alt"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($item['data_hora'])); ?></span>
                                        </div>
                                        <p class="text-gray-700 line-clamp-2 italic">
                                            "<?php echo htmlspecialchars(substr($item['prompt_usuario'], 0, 150)) . (strlen($item['prompt_usuario']) > 150 ? '...' : ''); ?>"
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- COLUNA DIREITA: Área de Resposta -->
                <div class="w-full md:w-2/3 flex flex-col h-full min-h-[500px]">
                    <div class="bg-white border rounded-lg shadow-sm relative flex-grow flex flex-col overflow-hidden">

                        <!-- Header da Resposta -->
                        <div class="bg-gray-100 px-4 py-3 border-b flex justify-between items-center z-10">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2">
                                <i class="fas fa-laptop-code text-gray-500"></i> Parecer da Inteligência Artificial
                            </h3>
                            <button id="btn_copy" onclick="copiarResposta()"
                                class="hidden btn btn-sm bg-white border border-gray-300 shadow-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-copy"></i> Copiar Texto
                            </button>
                        </div>

                        <!-- Área de Loading Overlay -->
                        <div id="loading_overlay"
                            class="hidden absolute inset-0 bg-white/90 z-20 flex flex-col items-center justify-center">
                            <div class="w-16 h-16 border-4 border-gray-200 rounded-full loader mb-4"></div>
                            <h4 class="text-xl font-bold text-gray-800">A IA está trabalhando...</h4>
                            <p id="loading_message" class="text-gray-500 mt-2 text-center px-4">Lendo o edital e elaborando
                                a resposta. Isso pode levar de 15 a 45 segundos dependendo do tamanho do PDF.</p>
                        </div>

                        <!-- Conteúdo da Resposta (Markdown) -->
                        <div id="resposta_content"
                            class="p-6 flex-grow overflow-y-auto markdown-body text-gray-800 bg-[#fbfbfe]">
                            <div class="h-full flex flex-col items-center justify-center text-center text-gray-400 p-8">
                                <i class="fas fa-robot text-6xl mb-4 text-gray-300"></i>
                                <h2 class="text-xl font-bold text-gray-500">Agente Pronto!</h2>
                                <p class="mt-2 max-w-md">Selecione uma Ação Rápida ao lado ou digite sua solicitação para a
                                    inteligência artificial analisar o documento "<strong>
                                        <?php echo htmlspecialchars($anexo['nome_original']); ?>
                                    </strong>".</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Dados Ocultos para o JS -->
            <input type="hidden" id="anexo_id" value="<?php echo $anexo_id; ?>">

        <?php endif; ?>

    </div>

    <!-- Scripts do Agente -->
    <script src="js/agente.js?v=<?php echo time(); ?>"></script>
</body>

</html>