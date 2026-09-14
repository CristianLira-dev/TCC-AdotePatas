<?php
session_start();

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/chat-read-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function responderPolling(
    bool $success,
    array $messages = [],
    ?string $error = null,
    int $status = 200,
    array $extra = []
): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'messages' => $messages,
        'error' => $error,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    responderPolling(false, [], 'Não autorizado.', 401);
}

$userId = (int) $_SESSION['user_id'];
$userTipo = (string) $_SESSION['user_tipo'];

if (!in_array($userTipo, ['usuario', 'ong'], true)) {
    responderPolling(false, [], 'Tipo de usuário inválido.', 403);
}

$conversaId = filter_input(INPUT_GET, 'conversa_id', FILTER_VALIDATE_INT);
$ultimoId = filter_input(INPUT_GET, 'ultimo_id', FILTER_VALIDATE_INT);

if (!$conversaId || $ultimoId === false || $ultimoId === null || $ultimoId < 0) {
    responderPolling(false, [], 'Parâmetros inválidos.', 400);
}

try {
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
        responderPolling(false, [], 'Acesso negado.', 403);
    }

    $readReceiptsEnabled = adotePatasEnsureMessageReadSchema($conn);

    if ($readReceiptsEnabled) {
        $stmtRead = $conn->prepare(
            "UPDATE mensagem
             SET lida = 1,
                 data_leitura = COALESCE(data_leitura, NOW())
             WHERE id_conversa_fk = :conversa_id
               AND lida = 0
               AND NOT (id_remetente_fk = :meu_id AND tipo_remetente = :meu_tipo)"
        );
        $stmtRead->execute([
            ':conversa_id' => $conversaId,
            ':meu_id' => $userId,
            ':meu_tipo' => $userTipo,
        ]);
    }

    $selectFields = 'id_mensagem, conteudo, data_envio, id_remetente_fk, tipo_remetente, tipo_conteudo, arquivo_nome';
    if ($readReceiptsEnabled) {
        $selectFields .= ', lida, data_leitura';
    }

    $stmt = $conn->prepare(
        "SELECT {$selectFields}
         FROM mensagem
         WHERE id_conversa_fk = :conversa_id
           AND id_mensagem > :ultimo_id
         ORDER BY id_mensagem ASC"
    );
    $stmt->bindValue(':conversa_id', $conversaId, PDO::PARAM_INT);
    $stmt->bindValue(':ultimo_id', $ultimoId, PDO::PARAM_INT);
    $stmt->execute();

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$message) {
        $message['id_mensagem'] = (int) $message['id_mensagem'];
        $message['id_remetente_fk'] = (int) $message['id_remetente_fk'];
        $message['sou_eu'] = (
            $message['id_remetente_fk'] === $userId
            && $message['tipo_remetente'] === $userTipo
        );
        $message['data_formatada'] = date('H:i, d/m/Y', strtotime($message['data_envio']));
        $message['lida'] = $readReceiptsEnabled ? (bool) ($message['lida'] ?? false) : false;
        $message['data_leitura'] = $readReceiptsEnabled ? ($message['data_leitura'] ?? null) : null;
    }
    unset($message);

    $readIds = [];
    if ($readReceiptsEnabled) {
        $stmtReadIds = $conn->prepare(
            "SELECT id_mensagem
             FROM mensagem
             WHERE id_conversa_fk = :conversa_id
               AND id_remetente_fk = :meu_id
               AND tipo_remetente = :meu_tipo
               AND lida = 1
             ORDER BY id_mensagem DESC
             LIMIT 200"
        );
        $stmtReadIds->execute([
            ':conversa_id' => $conversaId,
            ':meu_id' => $userId,
            ':meu_tipo' => $userTipo,
        ]);
        $readIds = array_map('intval', $stmtReadIds->fetchAll(PDO::FETCH_COLUMN));
    }

    responderPolling(true, $messages, null, 200, [
        'read_ids' => $readIds,
        'read_receipts_enabled' => $readReceiptsEnabled,
    ]);
} catch (Throwable $e) {
    error_log('Erro no polling do chat: ' . $e->getMessage());
    responderPolling(false, [], 'Erro no servidor.', 500);
}
