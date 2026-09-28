<?php
require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/upload.php';
require_once __DIR__ . '/app/audit.php';
require_once __DIR__ . '/app/notification.php';

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

$readEnvironment = static function (string $key, string $default = '') use ($env): string {
    if (array_key_exists($key, $env)) {
        return (string) $env[$key];
    }
    $value = getenv($key);
    return $value !== false ? (string) $value : $default;
};

$servername = $readEnvironment('DB_HOST', 'localhost');
$port = $readEnvironment('DB_PORT', '3306');
$username = $readEnvironment('DB_USER', 'root');
$password = $readEnvironment('DB_PASSWORD');
$dbname = $readEnvironment('DB_NAME', 'adote_patas');
$apiTinyMCE = $readEnvironment('TINYMCE_API_KEY');

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
    die('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Serviço indisponível</title>'
        . '<body><main><h1>Serviço temporariamente indisponível</h1>'
        . '<p>Não foi possível concluir a conexão. Tente novamente em alguns instantes.</p></main></body></html>');
}

appEnforceCsrf();
