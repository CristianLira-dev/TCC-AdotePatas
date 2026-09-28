<?php
// Inicia a sessão para poder acessá-la
require_once __DIR__ . '/app/security.php';
appStartSession();

// Remove todas as variáveis de sessão
$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

// Destrói a sessão
session_destroy();

// Redireciona o usuário para a página inicial
header("Location: ./");
exit;
?>
