<?php
/**
 * session.php - Gerenciamento de Sessão
 * * Contém a lógica para iniciar a sessão e uma função para garantir
 * que apenas usuários logados acessem certas páginas.
 */

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redireciona o usuário para a página de login se não houver um ID
 * de usuário válido na sessão.
 */
function requerer_login() {
    if (empty($_SESSION['id_usuario'])) {
        // Redireciona para a página de login
        header('Location: login.php');
        exit;
    }
}

/**
 * Redireciona o usuário para o dashboard se ele já estiver logado.
 * Útil para páginas de login e cadastro.
 */
function impedir_acesso_logado() {
    if (!empty($_SESSION['id_usuario'])) {
        // Define o destino padrão, que pode ser ajustado com base no tipo
        $destino = 'home.php'; 
        
        // Em um projeto real, você faria um switch/case ou if/else para 
        // direcionar para o dashboard específico (ex: adotante_dashboard.php, protetor_dashboard.php, etc.)
        
        header("Location: $destino");
        exit;
    }
}

?>
