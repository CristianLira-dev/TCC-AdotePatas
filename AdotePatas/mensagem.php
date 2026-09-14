<?php
session_start();

require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function responderMensagem(bool $success, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderMensagem(false, 'Método não permitido.', [], 405);
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    responderMensagem(false, 'Usuário não autenticado.', [], 401);
}

if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
    responderMensagem(false, 'O conteúdo enviado ultrapassa o limite permitido pelo servidor.', [], 413);
}

$userId = (int) $_SESSION['user_id'];
$userTipo = (string) $_SESSION['user_tipo'];

if (!in_array($userTipo, ['usuario', 'ong'], true)) {
    responderMensagem(false, 'Tipo de usuário inválido para o chat.', [], 403);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonInput = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '', true);
    if (is_array($decoded)) {
        $jsonInput = $decoded;
    }
}

$conversaId = filter_var(
    $jsonInput['conversa_id'] ?? $_POST['conversa_id'] ?? null,
    FILTER_VALIDATE_INT
);
$conteudo = trim((string) ($jsonInput['conteudo'] ?? $_POST['conteudo'] ?? ''));

if (!$conversaId) {
    responderMensagem(false, 'ID da conversa inválido ou ausente.', [], 400);
}

$tipoConteudo = 'texto';
$arquivoNomeOriginal = null;
$arquivoSalvo = null;

try {
    // Placeholders únicos evitam HY093 quando ATTR_EMULATE_PREPARES está desativado.
    $sqlPermissao = "
        SELECT id_conversa
        FROM conversa
        WHERE id_conversa = :conversa_id
          AND (
                (id_adotante_fk = :adotante_id AND :tipo_adotante = 'usuario')
             OR (id_protetor_fk = :protetor_id AND tipo_protetor = :tipo_protetor)
          )
        LIMIT 1
    ";

    $stmtPermissao = $conn->prepare($sqlPermissao);
    $stmtPermissao->execute([
        ':conversa_id' => $conversaId,
        ':adotante_id' => $userId,
        ':tipo_adotante' => $userTipo,
        ':protetor_id' => $userId,
        ':tipo_protetor' => $userTipo,
    ]);

    if (!$stmtPermissao->fetchColumn()) {
        responderMensagem(false, 'Acesso negado à conversa.', [], 403);
    }

    if (isset($_FILES['arquivo'])) {
        $file = $_FILES['arquivo'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido.',
                UPLOAD_ERR_PARTIAL => 'O upload foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'O servidor não possui diretório temporário disponível.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo.',
                UPLOAD_ERR_EXTENSION => 'O upload foi bloqueado pelo servidor.',
            ];

            $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            responderMensagem(false, $uploadErrors[$code] ?? 'Falha ao receber o arquivo.', [], 400);
        }

        if ((int) $file['size'] > 10 * 1024 * 1024) {
            responderMensagem(false, 'O arquivo deve ter no máximo 10 MB.', [], 413);
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $arquivoNomeOriginal = basename((string) $file['name']);

        $imageExtensions = ['webp', 'gif', 'jpg', 'jpeg', 'png'];
        $documentExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf'];

        if (in_array($extension, $imageExtensions, true)) {
            $tipoConteudo = 'imagem';

            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                responderMensagem(false, 'A imagem enviada é inválida.', [], 400);
            }
        } elseif (in_array($extension, $documentExtensions, true)) {
            $tipoConteudo = 'arquivo';
        } else {
            responderMensagem(false, 'Formato de arquivo não suportado.', [], 400);
        }

        $uploadDir = __DIR__ . '/uploads/chat';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            responderMensagem(false, 'Não foi possível preparar a pasta de uploads.', [], 500);
        }

        $fileName = 'chat_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $uploadDir . '/' . $fileName;
        $relativePath = 'uploads/chat/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            responderMensagem(false, 'Não foi possível salvar o arquivo enviado.', [], 500);
        }

        @chmod($destination, 0644);
        $arquivoSalvo = $destination;
        $conteudo = $relativePath;
    } elseif ($conteudo === '') {
        responderMensagem(false, 'Digite uma mensagem para enviar.', [], 400);
    }

    $sqlInsert = "
        INSERT INTO mensagem
            (id_conversa_fk, id_remetente_fk, tipo_remetente, conteudo, tipo_conteudo, arquivo_nome, data_envio)
        VALUES
            (:conversa, :remetente_id, :remetente_tipo, :conteudo, :tipo_conteudo, :arquivo_nome, NOW())
    ";

    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->execute([
        ':conversa' => $conversaId,
        ':remetente_id' => $userId,
        ':remetente_tipo' => $userTipo,
        ':conteudo' => $conteudo,
        ':tipo_conteudo' => $tipoConteudo,
        ':arquivo_nome' => $arquivoNomeOriginal,
    ]);

    $messageId = (int) $conn->lastInsertId();

    $stmtMessage = $conn->prepare(
        'SELECT id_mensagem, conteudo, tipo_conteudo, arquivo_nome, data_envio FROM mensagem WHERE id_mensagem = :id LIMIT 1'
    );
    $stmtMessage->execute([':id' => $messageId]);
    $savedMessage = $stmtMessage->fetch(PDO::FETCH_ASSOC) ?: [];

    $dataEnvio = $savedMessage['data_envio'] ?? date('Y-m-d H:i:s');

    responderMensagem(true, 'Mensagem enviada com sucesso.', [
        'id_mensagem' => $messageId,
        'conteudo' => $savedMessage['conteudo'] ?? $conteudo,
        'tipo_conteudo' => $savedMessage['tipo_conteudo'] ?? $tipoConteudo,
        'arquivo_nome' => $savedMessage['arquivo_nome'] ?? $arquivoNomeOriginal,
        'data_envio' => $dataEnvio,
        'data_formatada' => date('H:i, d/m/Y', strtotime($dataEnvio)),
        'sou_eu' => true,
    ]);
} catch (Throwable $e) {
    if ($arquivoSalvo && is_file($arquivoSalvo)) {
        @unlink($arquivoSalvo);
    }

    error_log('Erro ao enviar mensagem: ' . $e->getMessage());
    responderMensagem(false, 'Não foi possível enviar a mensagem. Tente novamente.', [], 500);
}
