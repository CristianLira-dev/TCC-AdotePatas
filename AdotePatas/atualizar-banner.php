<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$banner = $_POST['banner'] ?? '';

if (empty($banner)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Banner não especificado.']);
    exit;
}

// Valida se o banner existe na lista permitida
$bannersPermitidos = ['banner1.jpg', 'banner2.jpg', 'banner3.jpg', 'banner4.jpg', 'banner5.jpg'];
if (!in_array($banner, $bannersPermitidos, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Banner inválido.']);
    exit;
}

try {
    if ($user_tipo == 'usuario') {
        $sql = "UPDATE usuario SET banner_fixo = :banner WHERE id_usuario = :id";
    } elseif ($user_tipo == 'ong') {
        $sql = "UPDATE ong SET banner_fixo = :banner WHERE id_ong = :id";
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido.']);
        exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':banner', $banner);
    $stmt->bindParam(':id', $user_id);
    
    if ($stmt->execute()) {
        appAudit($conn, 'update', 'profile_banner', (int) $user_id, ['account_type' => $user_tipo]);
        echo json_encode(['success' => true, 'message' => 'Banner atualizado com sucesso!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar banner no banco de dados.']);
    }
} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o banner.']);
}
