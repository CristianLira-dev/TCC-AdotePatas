<?php
session_start();

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/email-service.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$petId = filter_input(INPUT_POST, 'id_pet', FILTER_VALIDATE_INT);
if (!$petId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do pet não fornecido.']);
    exit;
}

try {
    $stmtPet = $conn->prepare(
        "SELECT p.id_pet,
                p.nome,
                p.status_disponibilidade,
                COALESCE(u.nome, o.nome) AS responsavel_nome,
                COALESCE(u.email, o.email) AS responsavel_email
         FROM pet p
         LEFT JOIN usuario u ON p.id_usuario_fk = u.id_usuario
         LEFT JOIN ong o ON p.id_ong_fk = o.id_ong
         WHERE p.id_pet = :id_pet
         LIMIT 1"
    );
    $stmtPet->execute([':id_pet' => $petId]);
    $pet = $stmtPet->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pet não encontrado.']);
        exit;
    }

    if ($pet['status_disponibilidade'] !== 'Em Analise') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este pet não está em análise.']);
        exit;
    }

    $stmtUpdate = $conn->prepare(
        "UPDATE pet
         SET status_disponibilidade = 'recusado'
         WHERE id_pet = :id_pet"
    );
    $stmtUpdate->execute([':id_pet' => $petId]);

    $emailSent = false;
    if (!empty($pet['responsavel_email'])) {
        $emailSent = adotePatasSendEmail(
            (string) $pet['responsavel_email'],
            (string) ($pet['responsavel_nome'] ?? ''),
            'Atualização sobre a análise de ' . $pet['nome'],
            'Cadastro do pet não aprovado',
            'A análise de ' . $pet['nome'] . ' foi concluída e, neste momento, o cadastro não pôde ser aprovado. Revise as informações do pet antes de enviá-lo novamente para análise.',
            'Ver meus pets',
            adotePatasAppUrl() . '/perfil/?page=meus-pets'
        );
    }

    echo json_encode([
        'success' => true,
        'message' => $emailSent
            ? 'Pet recusado e responsável notificado por e-mail.'
            : 'Pet recusado com sucesso. O e-mail de notificação não pôde ser enviado.',
    ]);
} catch (Throwable $e) {
    error_log('Erro ao recusar pet: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
}
