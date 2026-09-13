<?php
$envPath = __DIR__ . '/.env';
$envLoaded = false;
$env = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        $env[$name] = $value;
        $_ENV[$name] = $value;
    }

    $envLoaded = true;
}

$servername = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? '3306';
$username = $env['DB_USER'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';
$dbname = $env['DB_NAME'] ?? 'adote_patas';
$apiTinyMCE = $env['TINYMCE_API_KEY'] ?? '';

try {
    $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());

    http_response_code(500);

    $pdoDrivers = PDO::getAvailableDrivers();
    $hasMysqlDriver = in_array('mysql', $pdoDrivers, true) ? 'SIM' : 'NÃO';
    $envStatus = $envLoaded ? 'SIM' : 'NÃO';
    $hostStatus = !empty($env['DB_HOST']) ? 'SIM' : 'NÃO';
    $nameStatus = !empty($env['DB_NAME']) ? 'SIM' : 'NÃO';
    $userStatus = !empty($env['DB_USER']) ? 'SIM' : 'NÃO';
    $passwordStatus = isset($env['DB_PASSWORD']) && $env['DB_PASSWORD'] !== '' ? 'SIM' : 'NÃO';

    $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $errorCode = htmlspecialchars((string) $e->getCode(), ENT_QUOTES, 'UTF-8');

    die("<div style='font-family: Arial, sans-serif; max-width: 760px; margin: 40px auto; padding: 24px; background:#fff3cd; color:#664d03; border:1px solid #ffecb5; border-radius:10px;'>
        <h2 style='margin-top:0;'>TESTE DE DEPLOY 2 ATIVO</h2>
        <p><strong>Este texto confirma que a correção de leitura do .env chegou ao servidor.</strong></p>
        <hr>
        <p><strong>Diagnóstico da conexão:</strong></p>
        <ul>
            <li>Arquivo .env encontrado: {$envStatus}</li>
            <li>DB_HOST carregado: {$hostStatus}</li>
            <li>DB_NAME carregado: {$nameStatus}</li>
            <li>DB_USER carregado: {$userStatus}</li>
            <li>DB_PASSWORD carregado: {$passwordStatus}</li>
            <li>Driver PDO MySQL disponível: {$hasMysqlDriver}</li>
        </ul>
        <p><strong>Código do erro PDO:</strong> {$errorCode}</p>
        <p><strong>Mensagem técnica:</strong> {$errorMessage}</p>
        <p style='margin-bottom:0;'><strong>Resultado:</strong> o .env foi lido diretamente pelo PHP, mas a conexão com o banco ainda falhou.</p>
    </div>");
}
