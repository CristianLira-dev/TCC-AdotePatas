<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/conexao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    http_response_code(401);
    exit('Não autorizado.');
}

$userId = (int) $_SESSION['user_id'];
$userTipo = (string) $_SESSION['user_tipo'];
$messageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$download = isset($_GET['download']) && $_GET['download'] === '1';
$raw = isset($_GET['raw']) && $_GET['raw'] === '1';

if (!$messageId || !in_array($userTipo, ['usuario', 'ong'], true)) {
    http_response_code(400);
    exit('Anexo inválido.');
}

function loadChatAttachment(PDO $conn, int $messageId, int $userId, string $userTipo): array
{
    $sql = "
        SELECT
            m.id_mensagem,
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
        throw new RuntimeException('Anexo não encontrado.', 404);
    }

    $relativePath = ltrim(str_replace('\\', '/', (string) $message['conteudo']), '/');
    if (!str_starts_with($relativePath, 'uploads/chat/')) {
        throw new RuntimeException('Caminho de anexo inválido.', 403);
    }

    $uploadRoot = realpath(dirname(__DIR__) . '/uploads/chat');
    $filePath = realpath(dirname(__DIR__) . '/' . $relativePath);

    if (!$uploadRoot || !$filePath || !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($filePath) || !is_readable($filePath)) {
        throw new RuntimeException('Arquivo não encontrado.', 404);
    }

    $message['file_path'] = $filePath;
    return $message;
}

function attachmentMime(string $filePath): string
{
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

    return $mime;
}

function streamAttachment(array $message, bool $download): never
{
    $filePath = $message['file_path'];
    $mime = attachmentMime($filePath);
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $originalName = trim((string) ($message['arquivo_nome'] ?? '')) ?: basename($filePath);
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
        flush();
    }

    fclose($handle);
    exit;
}

try {
    $message = loadChatAttachment($conn, $messageId, $userId, $userTipo);

    if ($raw || $download) {
        streamAttachment($message, $download);
    }

    $baseUrl = ADOTE_PATAS_BASE_URL;
    $rawUrl = $baseUrl . 'chat-anexo/?id=' . $messageId . '&raw=1';
    $downloadUrl = $baseUrl . 'chat-anexo/?id=' . $messageId . '&download=1';
    $chatUrl = $baseUrl . 'chat/?id=' . (int) $message['id_conversa'];
    $fileName = trim((string) ($message['arquivo_nome'] ?? '')) ?: 'Anexo';
    $type = (string) $message['tipo_conteudo'];
} catch (Throwable $e) {
    $status = (int) $e->getCode();
    http_response_code(in_array($status, [403, 404], true) ? $status : 500);
    exit($status === 404 ? 'Anexo não encontrado.' : 'Não foi possível abrir o anexo.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fileName); ?> - Adote Patas</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($baseUrl); ?>images/global/Logo-AdotePatas.png">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#111;color:#fff;font-family:Arial,sans-serif;display:flex;flex-direction:column}.top{height:64px;display:flex;align-items:center;gap:12px;padding:0 20px;background:#fff;color:#555}.top img{width:38px;height:38px;object-fit:contain}.name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}.actions{display:flex;gap:8px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;background:#f2f2f2;color:#8f3f38;text-decoration:none;font-weight:700}.viewer{flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:20px}.viewer img,.viewer video{max-width:100%;max-height:calc(100vh - 104px);object-fit:contain;border-radius:12px}.doc{max-width:520px;width:100%;padding:28px;border-radius:18px;background:#fff;color:#555;text-align:center}.doc h1{font-size:1.1rem;word-break:break-word}@media(max-width:640px){.top{height:auto;min-height:64px;padding:10px 12px;flex-wrap:wrap}.name{order:3;flex-basis:100%}.viewer{padding:10px}.btn{padding:9px 11px;font-size:.85rem}}
    </style>
</head>
<body>
    <header class="top">
        <img src="<?php echo htmlspecialchars($baseUrl); ?>images/global/Logo-AdotePatas.png" alt="Adote Patas">
        <div class="name"><?php echo htmlspecialchars($fileName); ?></div>
        <div class="actions">
            <a class="btn" href="<?php echo htmlspecialchars($chatUrl); ?>">Voltar ao chat</a>
            <a class="btn" href="<?php echo htmlspecialchars($downloadUrl); ?>">Baixar</a>
        </div>
    </header>
    <main class="viewer">
        <?php if ($type === 'imagem'): ?>
            <img src="<?php echo htmlspecialchars($rawUrl); ?>" alt="<?php echo htmlspecialchars($fileName); ?>">
        <?php elseif ($type === 'video'): ?>
            <video src="<?php echo htmlspecialchars($rawUrl); ?>" controls playsinline preload="metadata"></video>
        <?php else: ?>
            <div class="doc">
                <h1><?php echo htmlspecialchars($fileName); ?></h1>
                <p>Este documento pode ser aberto ou baixado pelo botão abaixo.</p>
                <a class="btn" href="<?php echo htmlspecialchars($rawUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir documento</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
