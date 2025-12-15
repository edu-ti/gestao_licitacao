<?php
// Arquivo: auth.php
// Responsável por verificar se um utilizador está logado e carregar funções essenciais.

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ==============================================
// INCLUSÃO DO ARQUIVO DE FUNÇÕES
// ==============================================
require_once 'functions.php';

// NOVA VERIFICAÇÃO: Checa se o usuário está logado E se logou pelo sistema correto (Licitação)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in_system']) || $_SESSION['logged_in_system'] !== 'licitacao') {
    // Se não estiver, redireciona para a página de login unificada
    header("Location: ../portal_login/index.php");
    exit();
}