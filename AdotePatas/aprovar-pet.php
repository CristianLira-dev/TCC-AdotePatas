<?php
session_start();

include_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/email-service.php';

header('Content-Type: application/json; charset=utf-8');

// Verifica se é admin
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

// Verifica se o ID do pet foi enviado
if (!isset($_POST['id_pet']) || empty($_POST['id_pet'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do pet não fornecido.']);
    exit;
}

$pet_id = (int) $_POST['id_pet'];

try {
    // Busca o pet e os dados do responsável para a notificação por e-mail.
    $sql_verifica = "
        SELECT p.id_pet,
               p.nome,
               p.status_disponibilidade,
               COALESCE(u.nome, o.nome) AS responsavel_nome,
               COALESCE(u.email, o.email) AS responsavel_email
        FROM pet p
        LEFT JOIN usuario u ON p.id_usuario_fk = u.id_usuario
        LEFT JOIN ong o ON p.id_ong_fk = o.id_ong
        WHERE p.id_pet = :id_pet
        LIMIT 1
    ";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':id_pet', $pet_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $pet = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

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

    // Atualiza o status para disponível.
    $sql_atualiza = "UPDATE pet SET status_disponibilidade = 'disponivel' WHERE id_pet = :id_pet";
    $stmt_atualiza = $conn->prepare($sql_atualiza);
    $stmt_atualiza->bindParam(':id_pet', $pet_id, PDO::PARAM_INT);

    if (!$stmt_atualiza->execute()) {
        throw new RuntimeException('Não foi possível atualizar o status do pet.');
    }

    if (!empty($pet['responsavel_email'])) {
        adotePatasSendEmail(
            (string) $pet['responsavel_email'],
            (string) ($pet['responsavel_nome'] ?? ''),
            $pet['nome'] . ' foi aprovado no Adote Patas',
            'Pet aprovado!',
            'A análise de ' . $pet['nome'] . ' foi concluída com sucesso. O anúncio já está disponível para as pessoas que procuram um novo companheiro.',
            'Ver meus pets',
            adotePatasAppUrl() . '/perfil/?page=meus-pets'
        );
    }

    echo json_encode(['success' => true, 'message' => 'Pet aprovado com sucesso!']);
} catch (Throwable $e) {
    error_log('Erro ao aprovar pet: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
}
