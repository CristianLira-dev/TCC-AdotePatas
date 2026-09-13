<?php
/**
 * conexao.php - Configuração da conexão PDO com o banco de dados.
 *
 * Em produção, configure as variáveis de ambiente:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASSWORD.
 *
 * Em ambiente local, os valores padrão abaixo mantêm compatibilidade
 * com instalações comuns do XAMPP/Laragon.
 */

$servername = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME') ?: 'adote_patas';

// getenv() retorna false quando a variável não existe.
// No ambiente local, a senha padrão continua vazia.
if ($password === false) {
    $password = '';
}

$apiTinyMCE = getenv('TINYMCE_API_KEY') ?: 'h8krho8x5gu4wxlvavexdy4hb45bm5oq457o94fm0k4o6l07';

try {
    $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Registra o erro técnico no log do servidor sem expor credenciais ao usuário.
    error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());

    http_response_code(500);

    die("<div style='text-align: center; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>
            <strong>Erro Crítico:</strong> Não foi possível conectar ao banco de dados.
            <br>Verifique as configurações do servidor.
          </div>");
}
