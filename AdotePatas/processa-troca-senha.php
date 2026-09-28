<?php
include_once 'conexao.php'; // Sua conexão PDO

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// --- FUNÇÃO DE VALIDAÇÃO DE SENHA (copiada de autenticacao.php) ---
function validarForcaSenha($senha) {
    $erros = [];
    if (strlen($senha) < 8) $erros[] = "A senha deve ter no mínimo 8 caracteres.";
    if (!preg_match('/[A-Z]/', $senha)) $erros[] = "A senha deve conter ao menos uma letra maiúscula.";
    if (!preg_match('/[0-9]/', $senha)) $erros[] = "A senha deve conter ao menos um número.";
    if (!preg_match('/[\W_]/', $senha)) $erros[] = "A senha deve conter ao menos um caractere especial.";
    return $erros;
}
// -----------------------------------------------------------------

$response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'] ?? null;
    $tokenHash = $token ? hash('sha256', (string) $token) : null;
    $nova_senha = $_POST['nova_senha'] ?? null;
    $confirma_senha = $_POST['confirma_senha'] ?? null;

    // 1. Validação de campos
    if (empty($token) || empty($nova_senha) || empty($confirma_senha)) {
        $response['message'] = 'Todos os campos são obrigatórios.';
        echo json_encode($response);
        exit;
    }

    // 2. Validação de Senha (PHP)
    if ($nova_senha !== $confirma_senha) {
        $response['message'] = 'As senhas não coincidem.';
        echo json_encode($response);
        exit;
    }

    // *** VALIDAÇÃO DE FORÇA DA SENHA NO BACKEND ***
    $erros_senha = validarForcaSenha($nova_senha);
    if (!empty($erros_senha)) {
        // Retorna o primeiro erro de força da senha
        $response['message'] = $erros_senha[0]; 
        echo json_encode($response);
        exit;
    }
    // *** FIM DA VALIDAÇÃO DE FORÇA ***

    // 3. Validação do Token e Atualização (Segurança)
    try {
        $now = date("Y-m-d H:i:s");
        $sql = "SELECT email FROM recuperar_senha_tolken WHERE (token = :token_hash OR token = :legacy_token) AND expires_at > :now LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':token_hash' => $tokenHash, ':legacy_token' => $token, ':now' => $now]);
        $reset_request = $stmt->fetch();
        if (!$reset_request) {
            $response['message'] = 'Token inválido ou expirado. Por favor, solicite um novo link.';
            echo json_encode($response);
            exit;
        }

        $email_para_atualizar = $reset_request['email'];
        $senha_hashed = password_hash($nova_senha, PASSWORD_DEFAULT);
        $conn->beginTransaction();

        
        // Tenta atualizar na tabela 'usuario'
        $sql_user = "UPDATE usuario SET senha = :senha WHERE email = :email";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->execute([':senha' => $senha_hashed, ':email' => $email_para_atualizar]);
        
        // Tenta atualizar na tabela 'ong'
        $sql_ong = "UPDATE ong SET senha = :senha WHERE email = :email";
        $stmt_ong = $conn->prepare($sql_ong);
        $stmt_ong->execute([':senha' => $senha_hashed, ':email' => $email_para_atualizar]);

        // Verifica se alguma linha foi afetada em qualquer uma das tabelas
        if ($stmt_user->rowCount() === 0 && $stmt_ong->rowCount() === 0) {
             // Se nenhuma linha foi afetada, algo está errado (e-mail não encontrado?)
             // Embora o token exista, o e-mail associado pode ter sido alterado/removido.
             $conn->rollBack(); // Desfaz a transação
             $response['message'] = 'Não foi possível encontrar o usuário associado a este token.';
             echo json_encode($response);
             exit;
        }

        // 5. Invalida o token (excluindo-o)
        $sql_delete = "DELETE FROM recuperar_senha_tolken WHERE token = :token_hash OR token = :legacy_token";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([':token_hash' => $tokenHash, ':legacy_token' => $token]);

        $conn->commit();
        appAudit($conn, 'password_reset', 'account', null, ['email_hash' => hash('sha256', $email_para_atualizar)]);

        $response['success'] = true;
        $response['message'] = 'Senha atualizada com sucesso!';

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Erro ao processar troca de senha: " . $e->getMessage());
        $response['message'] = 'Erro no banco de dados. Tente novamente.';
    }
} else {
    $response['message'] = 'Método de requisição inválido.';
}

echo json_encode($response);
exit;
?>
