<?php
session_start();

require_once __DIR__ . '/conexao.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    http_response_code(401);
    exit('Não autorizado.');
}

$userId = (int) $_SESSION['user_id'];
$userTipo = (string) $_SESSION['user_tipo'];
$messageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$download = isset($_GET['download']) && $_GET['download'] === '1';

if (!$messageId || !in_array($userTipo, ['usuario', 'ong'], true)) {
    http_response_code(400);
    exit('Anexo inválido.');
}

try {
    $sql = "
        SELECT
            m.conteudo,
            m.tipo_conteudo,
            m.arquivo_nome,
            c.id_conversa
        FROM mensagem m
        INNER JOIN conversa c ON c.id_conversa = m.id_conversa_fk
        WHERE m.id_mensagem = :mensagem_id
          AND (
                (c.id_adotante_fk = :adotante_id AND :tipo_adotante = 'usuario')
             OR (c.id_protetor_fk = :protetor_id AND c.tipo_protetor = :tipo_protetor)
          )
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':mensagem_id' => $messageId,
        ':adotante_id' => $userId,
        ':tipo_adotante' => $userTipo,
        ':protetor_id' => $userId,
        ':tipo_protetor' => $userTipo,
    ]);

    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message || !in_array($message['tipo_conteudo'], ['imagem', 'video', 'arquivo'], true)) {
        http_response_code(404);
        exit('Anexo não encontrado.');
    }

    $relativePath = ltrim(str_replace('\\', '/', (string) $message['conteudo']), '/');
    if (!str_starts_with($relativePath, 'uploads/chat/')) {
        http_response_code(403);
        exit('Caminho de anexo inválido.');
    }

    $uploadRoot = realpath(__DIR__ . '/uploads/chat');
    $filePath = realpath(__DIR__ . '/' . $relativePath);

    if (!$uploadRoot || !$filePath || !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit('Arquivo não encontrado.');
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8',
        'rtf' => 'application/rtf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $mime = $mimeMap[$extension] ?? 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
                $mime = $detected;
            }
        }
    }

    $originalName = trim((string) ($message['arquivo_nome'] ?? ''));
    if ($originalName === '') {
        $originalName = basename($filePath);
    }
    $safeName = preg_replace('/[^A-Za-z0-9._ -]/u', '_', $originalName) ?: ('anexo.' . $extension);

    $size = filesize($filePath);
    if ($size === false) {
        throw new RuntimeException('Não foi possível obter o tamanho do anexo.');
    }

    $start = 0;
    $end = max(0, $size - 1);
    $status = 200;

    if (!$download && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }
        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $end);
        }

        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }

        $status = 206;
    }

    $length = $end - $start + 1;

    http_response_code($status);
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addcslashes($safeName, '"\\') . '"');
    header('Content-Length: ' . $length);

    if ($status === 206) {
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }

    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        throw new RuntimeException('Não foi possível abrir o anexo.');
    }

    if ($start > 0) {
        fseek($handle, $start);
    }

    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        if (function_exists('fastcgi_finish_request')) {
            // Não finaliza aqui; apenas mantém compatibilidade com ambientes FastCGI.
        }
        flush();
    }

    fclose($handle);
    exit;
} catch (Throwable $e) {
    error_log('Erro ao servir anexo do chat: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('Não foi possível abrir o anexo.');
}
