<?php
// --- CONFIGURAÇÃO E CONEXÃO ---
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth.php';
require_once 'Database.php';
require_once 'config.php';


?>

<!-- CSS CUSTOMIZADO (Design Limpo e Profissional) -->
<style>
    /* Reset básico para esta área */
    .licencas-wrapper {
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        background-color: #f8f9fa;
        padding: 20px;
        min-height: 80vh;
    }

    /* Cabeçalho */
    .page-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
    }

    .page-title {
        font-size: 24px;
        color: #333;
        margin: 0;
        font-weight: 600;
    }

    .page-subtitle {
        color: #6c757d;
        font-size: 14px;
        margin-top: 5px;
    }

    /* Container Branco (Card) */
    .content-box {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid #e9ecef;
    }

    .box-header {
        font-size: 18px;
        color: #495057;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Formulário */
    .form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #555;
        font-size: 13px;
    }

    .custom-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.15s ease-in-out;
        box-sizing: border-box;
        /* Garante que padding não quebre layout */
    }

    .custom-input:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }

    .btn-save {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        height: 42px;
        /* Altura igual aos inputs */
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-save:hover {
        background-color: #218838;
    }

    /* Tabela */
    .table-container {
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .custom-table th {
        background-color: #f1f3f5;
        color: #495057;
        font-weight: 600;
        text-align: left;
        padding: 15px;
        border-bottom: 2px solid #dee2e6;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .custom-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        color: #333;
        vertical-align: middle;
    }

    .custom-table tr:hover {
        background-color: #f8f9fa;
    }

    /* Badges e Ações */
    .status-badge {
        padding: 5px 10px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }

    .badge-success {
        background-color: #d4edda;
        color: #155724;
    }

    .badge-warning {
        background-color: #fff3cd;
        color: #856404;
    }

    .badge-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 4px;
        color: white;
        text-decoration: none;
        margin-right: 4px;
        transition: opacity 0.2s;
        border: none;
    }

    .action-btn:hover {
        opacity: 0.8;
        color: white;
    }

    .btn-view {
        background-color: #17a2b8;
    }

    .btn-down {
        background-color: #6c757d;
    }

    .btn-del {
        background-color: #dc3545;
    }

    /* Mensagens */
    .msg-success {
        padding: 15px;
        background: #d4edda;
        color: #155724;
        border-radius: 6px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
    }

    .msg-error {
        padding: 15px;
        background: #f8d7da;
        color: #721c24;
        border-radius: 6px;
        margin-bottom: 20px;
        border: 1px solid #f5c6cb;
    }

    /* Responsivo */
    @media (max-width: 900px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .btn-save {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }
    }
</style>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento de Licitações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css?v=2.35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/consignado.css?v=1.0">
</head>

<body class="bg-[#d9e3ec] p-4 sm:p-8">
    <div class="container mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-lg">
        <?php
        $page_title = 'Monitoramento de Licitações';
        include 'header.php';
        ?>

        <div class="monitorar-wrapper">
            <!-- Cabeçalho da Página -->
            <div class="page-header-custom">
                <div>
                    <span>Monitoramento Avisos de Licitações</span>
                </div>
                <a href="radar.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">Minhas Licitações</a>
                <a href="radar_config.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">Configuração</a>
                <a href="dashboard.php" class="btn btn-primary bg-blue-900 hover:bg-blue-800">&larr; Voltar ao
                    Painel</a>
            </div>

            <?= $msg ?>
        </div>
        <div class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-md">
            <h1 class="text-2xl font-bold mb-4">Configurações do Monitor</h1>
            <p class="text-gray-600">Página em construção. Aguardando definições de layout.</p>
        </div>
</body>

</html>