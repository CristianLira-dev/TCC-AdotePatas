<?php
session_start();

require_once __DIR__ . '/route-bootstrap.php';
require_once __DIR__ . '/conexao.php';

function finalizarFotoPerfil(bool $success, string $message): void
{
    $_SESSION['toast_message'] = $message;
    $_SESSION['toast_type'] = $success ? 'success' : 'error';

    header('Location: ' . ADOTE_PATAS_BASE_URL . 'perfil/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finalizarFotoPerfil(false, 'Método de requisição inválido.');
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    header('Location: ' . ADOTE_PATAS_BASE_URL . 'login/');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
$sessionCsrf = $_SESSION['profile_photo_csrf'] ?? '';

if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    finalizarFotoPerfil(false, 'Não foi possível validar a solicitação. Atualize a página e tente novamente.');
}

$userId = (int) $_SESSION['user_id'];
$userTipo = $_SESSION['user_tipo'];

if (!in_array($userTipo, ['usuario', 'ong'], true)) {
    finalizarFotoPerfil(false, 'Este tipo de conta não permite alterar a foto de perfil.');
}

$table = $userTipo === 'usuario' ? 'usuario' : 'ong';
$idColumn = $userTipo === 'usuario' ? 'id_usuario' : 'id_ong';

try {
    $stmt = $conn->prepare("SELECT foto_perfil FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        finalizarFotoPerfil(false, 'Conta não encontrada.');
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

        finalizarFotoPerfil(true, 'Foto de perfil removida com sucesso.');
    }

    if (!isset($_FILES['foto_perfil'])) {
        finalizarFotoPerfil(false, 'Selecione uma imagem para continuar.');
    }

    $file = $_FILES['foto_perfil'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'A imagem ultrapassa o limite permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'A imagem ultrapassa o limite permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O upload da imagem foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Selecione uma imagem para continuar.',
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor não possui diretório temporário disponível.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a imagem.',
            UPLOAD_ERR_EXTENSION => 'O upload foi bloqueado por uma extensão do servidor.',
        ];

        finalizarFotoPerfil(false, $uploadErrors[$file['error']] ?? 'Não foi possível receber a imagem enviada.');
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        finalizarFotoPerfil(false, 'A imagem deve ter no máximo 5 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = $imageInfo['mime'] ?? '';

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        finalizarFotoPerfil(false, 'Formato inválido. Use JPG, PNG ou WEBP.');
    }

    $uploadDir = __DIR__ . '/uploads/perfil';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        finalizarFotoPerfil(false, 'Não foi possível preparar a pasta da foto no servidor.');
    }

    if (!is_writable($uploadDir)) {
        finalizarFotoPerfil(false, 'A pasta de fotos não possui permissão de escrita no servidor.');
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
        finalizarFotoPerfil(false, 'Não foi possível salvar a imagem no servidor.');
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

    finalizarFotoPerfil(true, 'Foto de perfil atualizada com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao atualizar foto de perfil: ' . $e->getMessage());
    finalizarFotoPerfil(false, 'Não foi possível atualizar a foto de perfil. Tente novamente.');
}
