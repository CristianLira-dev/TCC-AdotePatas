<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=UTF-8');

function responderStatusAdocao(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderStatusAdocao(false, 'Método não permitido.', 405);
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    responderStatusAdocao(false, 'Não autorizado.', 401);
}

$solicitacaoId = filter_input(INPUT_POST, 'solicitacao_id', FILTER_VALIDATE_INT);
$newStatus = trim((string) ($_POST['status'] ?? ''));
$allowedStatuses = ['pendente', 'em_conversa', 'entrevista', 'aprovado', 'recusado', 'concluido', 'cancelado'];
if (!$solicitacaoId || !in_array($newStatus, $allowedStatuses, true)) {
    responderStatusAdocao(false, 'Status ou solicitação inválida.', 422);
}

try {
    $statement = $conn->prepare(
        'SELECT s.id_solicitacao, s.id_usuario, s.id_pet, s.status_solicitacao,'
        . ' c.id_conversa, c.id_protetor_fk, c.tipo_protetor, p.nome AS pet_nome'
        . ' FROM solicitacao s'
        . ' INNER JOIN conversa c ON c.id_solicitacao_fk = s.id_solicitacao'
        . ' INNER JOIN pet p ON p.id_pet = s.id_pet'
        . ' WHERE s.id_solicitacao = :id LIMIT 1'
    );
    $statement->execute([':id' => $solicitacaoId]);
    $request = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        responderStatusAdocao(false, 'Solicitação não encontrada.', 404);
    }

    $userId = (int) $_SESSION['user_id'];
    $userType = (string) $_SESSION['user_tipo'];
    $isAdmin = $userType === 'admin';
    $isProtector = $userId === (int) $request['id_protetor_fk'] && $userType === $request['tipo_protetor'];
    $isAdopter = $userType === 'usuario' && $userId === (int) $request['id_usuario'];

    if (!$isAdmin && !$isProtector && !($isAdopter && $newStatus === 'cancelado')) {
        responderStatusAdocao(false, 'Você não pode realizar esta alteração.', 403);
    }

    $terminalStatuses = ['recusado', 'concluido', 'cancelado'];
    if (in_array($request['status_solicitacao'], $terminalStatuses, true)) {
        responderStatusAdocao(false, 'Esta solicitação já foi encerrada.', 409);
    }

    $conn->beginTransaction();
    $update = $conn->prepare(
        'UPDATE solicitacao SET status_solicitacao = :status WHERE id_solicitacao = :id'
    );
    $update->execute([':status' => $newStatus, ':id' => $solicitacaoId]);

    if ($newStatus === 'concluido') {
        $petUpdate = $conn->prepare("UPDATE pet SET status_disponibilidade = 'adotado' WHERE id_pet = :id");
        $petUpdate->execute([':id' => (int) $request['id_pet']]);
    }
    $conn->commit();

    appAudit($conn, 'status_change', 'solicitacao', (int) $solicitacaoId, [
        'from' => $request['status_solicitacao'],
        'to' => $newStatus,
    ]);

    appNotify(
        $conn,
        (int) $request['id_usuario'],
        'usuario',
        'Atualização na adoção',
        'A solicitação para ' . $request['pet_nome'] . ' agora está como ' . str_replace('_', ' ', $newStatus) . '.',
        'chat/?id=' . (int) $request['id_conversa']
    );
    appNotify(
        $conn,
        (int) $request['id_protetor_fk'],
        (string) $request['tipo_protetor'],
        'Atualização na adoção',
        'A solicitação para ' . $request['pet_nome'] . ' agora está como ' . str_replace('_', ' ', $newStatus) . '.',
        'chat/?id=' . (int) $request['id_conversa']
    );

    responderStatusAdocao(true, 'Status da adoção atualizado com sucesso.');
} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Erro ao atualizar status da adoção: ' . $error->getMessage());
    responderStatusAdocao(false, 'Não foi possível atualizar o status.', 500);
}
