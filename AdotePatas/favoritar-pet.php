<?php
// Inclui a conexão e a sessão
include_once 'conexao.php';
include_once 'session.php'; // Garante que session_start() seja chamado

// Define o cabeçalho como JSON
header('Content-Type: application/json');

// --- 1. Resposta Padrão ---
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

// --- 2. Segurança: Verifica se o usuário está logado ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido (Forbidden)
    $response['message'] = 'Login necessário para favoritar.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 3. Segurança: Verifica o Método ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 4. Coleta de Dados ---
// Pega os dados enviados pelo JavaScript (fetch)
$data = json_decode(file_get_contents('php://input'));

$id_usuario = $_SESSION['user_id'];
$id_pet = isset($data->id_pet) ? (int)$data->id_pet : 0;

if (empty($id_pet)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'ID do pet não fornecido.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 5. Lógica de Favoritar/Desfavoritar ---
try {
    // Tenta encontrar um favorito existente
    $sql_check = "SELECT id_favorito FROM favorito WHERE id_usuario = :id_usuario AND id_pet = :id_pet LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':id_usuario' => $id_usuario, ':id_pet' => $id_pet]);
    
    $favorito_existente = $stmt_check->fetch();

    if ($favorito_existente) {
        // --- Ação: Desfavoritar (DELETE) ---
        $sql_delete = "DELETE FROM favorito WHERE id_favorito = :id_favorito";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([':id_favorito' => $favorito_existente['id_favorito']]);
        appAudit($conn, 'unfavorite', 'pet', $id_pet);
        
        $response['success'] = true;
        $response['action'] = 'unfavorited';
        $response['message'] = 'Pet removido dos favoritos.';
        
    } else {
        // --- Ação: Favoritar (INSERT) ---
        $sql_insert = "INSERT INTO favorito (id_usuario, id_pet) VALUES (:id_usuario, :id_pet)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->execute([':id_usuario' => $id_usuario, ':id_pet' => $id_pet]);
        appAudit($conn, 'favorite', 'pet', $id_pet);

        $response['success'] = true;
        $response['action'] = 'favorited';
        $response['message'] = 'Pet adicionado aos favoritos!';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500); // Erro interno do servidor
    error_log('Erro ao alterar favorito: ' . $e->getMessage());
    $response['message'] = 'Não foi possível atualizar o favorito.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
