<?php
/* Arquivo: logout.php */
/* Versão: Destrói Sessão E Cookies */

session_start();

// 1. Destruir todas as variáveis da sessão
$_SESSION = array();

// 2. Apagar o cookie da sessão PHP (se existir)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. APAGAR O COOKIE "MANTER CONECTADO" (ESSENCIAL)
// Define o tempo para o passado para o navegador excluir
setcookie('bliss_remember', '', time() - 3600, "/");

// 4. Destruir a sessão
session_destroy();

// 5. Redirecionar para login
header("Location: login.php");
exit;
?>