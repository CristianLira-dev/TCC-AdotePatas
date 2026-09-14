<?php
session_start();

require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

function responderFotoPerfil(bool $success, string $message, ?string $photo = null): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'photo' => $photo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderFotoPerfil(false, 'Método de requisição inválido.');
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    http_response_code(401);
    responderFotoPerfil(false, 'Sessão inválida. Faça login novamente.');
}

$csrf = $_POST['csrf_token'] ?? '';
$sessionCsrf = $_SESSION['profile_photo_csrf'] ?? '';

if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    http_response_code(403);
    responderFotoPerfil(false, 'Não foi possível validar a solicitação. Atualize a página e tente novamente.');
}

$userId = (int) $_SESSION['user_id'];
$userTipo = $_SESSION['user_tipo'];

if (!in_array($userTipo, ['usuario', 'ong'], true)) {
    http_response_code(403);
    responderFotoPerfil(false, 'Este tipo de conta não permite alterar a foto de perfil.');
}

$table = $userTipo === 'usuario' ? 'usuario' : 'ong';
$idColumn = $userTipo === 'usuario' ? 'id_usuario' : 'id_ong';

try {
    $stmt = $conn->prepare("SELECT foto_perfil FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        responderFotoPerfil(false, 'Conta não encontrada.');
    }

    $currentPhoto = $current['foto_perfil'] ?? null;
    $action = $_POST['action'] ?? 'upload';

    if ($action === 'remove') {
        $stmt = $conn->prepare("UPDATE {$table} SET foto_perfil = NULL WHERE {$idColumn} = :id");
        $stmt->execute([':id' => $userId]);

        if (!empty($currentPhoto) && str_starts_with($currentPhoto, 'uploads/perfil/')) {
            $oldPath = __DIR__ . '/' . $currentPhoto;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        responderFotoPerfil(true, 'Foto de perfil removida com sucesso.');
    }

    if (!isset($_FILES['foto_perfil']) || $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
        responderFotoPerfil(false, 'Selecione uma imagem válida para continuar.');
    }

    $file = $_FILES['foto_perfil'];

    if ($file['size'] > 5 * 1024 * 1024) {
        responderFotoPerfil(false, 'A imagem deve ter no máximo 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        responderFotoPerfil(false, 'Formato inválido. Use JPG, PNG ou WEBP.');
    }

    $uploadDir = __DIR__ . '/uploads/perfil';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        responderFotoPerfil(false, 'Não foi possível preparar o diretório da foto.');
    }

    $extension = $allowed[$mime];
    $fileName = sprintf(
        'perfil_%s_%d_%s.%s',
        $userTipo,
        $userId,
        bin2hex(random_bytes(8)),
        $extension
    );

    $relativePath = 'uploads/perfil/' . $fileName;
    $destination = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        responderFotoPerfil(false, 'Não foi possível salvar a imagem enviada.');
    }

    try {
        $stmt = $conn->prepare("UPDATE {$table} SET foto_perfil = :foto WHERE {$idColumn} = :id");
        $stmt->execute([
            ':foto' => $relativePath,
            ':id' => $userId,
        ]);
    } catch (Throwable $e) {
        @unlink($destination);
        throw $e;
    }

    if (!empty($currentPhoto) && str_starts_with($currentPhoto, 'uploads/perfil/')) {
        $oldPath = __DIR__ . '/' . $currentPhoto;
        if (is_file($oldPath) && $oldPath !== $destination) {
            @unlink($oldPath);
        }
    }

    responderFotoPerfil(true, 'Foto de perfil atualizada com sucesso.', $relativePath);
} catch (Throwable $e) {
    error_log('Erro ao atualizar foto de perfil: ' . $e->getMessage());
    http_response_code(500);
    responderFotoPerfil(false, 'Não foi possível atualizar a foto de perfil. Tente novamente.');
}
