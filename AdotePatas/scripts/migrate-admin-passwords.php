<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/conexao.php';

$statement = $conn->query('SELECT id_admin, senha FROM administrador');
$updated = 0;

foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $admin) {
    $storedPassword = (string) $admin['senha'];
    if ((password_get_info($storedPassword)['algo'] ?? 0) !== 0) {
        continue;
    }

    $update = $conn->prepare('UPDATE administrador SET senha = :senha WHERE id_admin = :id');
    $update->execute([
        ':senha' => password_hash($storedPassword, PASSWORD_DEFAULT),
        ':id' => (int) $admin['id_admin'],
    ]);
    $updated++;
}

fwrite(STDOUT, "Administradores migrados: {$updated}\n");
