<?php
// Arquivo: logout.php
// Encerra a sessão do usuário e o redireciona para a página de login.

session_start();

// Destrói todas as variáveis de sessão.
$_SESSION = array();

// Destrói o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão.
session_destroy();

// Redireciona para a página de login correta (login.html).
header("Location: login.html");
exit();
?>
