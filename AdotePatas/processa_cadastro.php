<?php
// Inicia a sessão para poder redirecionar com mensagens se necessário
require_once __DIR__ . '/app/security.php';
appStartSession();

// Inclui a conexão
include_once 'conexao.php';

// Verifica se o método é POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validação básica se todos os campos estão presentes
    if (isset($_POST['nome'], $_POST['email'], $_POST['senha'], $_POST['cpf'], $_POST['confirma_senha'])) {

        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = $_POST['senha'];
        $confirma_senha = $_POST['confirma_senha'];
        $cpf = $_POST['cpf'];

        // Validações adicionais (sugestão)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensagem_status'] = "O formato do e-mail é inválido.";
            $_SESSION['tipo_mensagem'] = 'warning';
            header('Location: pets-adocao.php?modal=show'); // Redireciona de volta
            exit();
        }

        if ($senha !== $confirma_senha) {
            $_SESSION['mensagem_status'] = "As senhas não coincidem. Por favor, tente novamente.";
            $_SESSION['tipo_mensagem'] = 'warning';
            header('Location: pets-adocao.php?modal=show'); // Redireciona de volta
            exit();
        }

        // --- Segurança CRÍTICA: Implementando Hash de Senha ---
        $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);
        
        // --- Prevenção de Injeção SQL com Prepared Statements ---
        try {
            $sql = "INSERT INTO usuario (nome, email, senha, cpf) VALUES (:nome, :email, :senha, :cpf)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => $senha_hashed,
                ':cpf' => $cpf
            ])) {
                // Sucesso! Redireciona para o login com uma mensagem de sucesso
                header('Location: login.php?cadastro=sucesso');
                exit();
            } else {
                $_SESSION['mensagem_status'] = "Não foi possível concluir o cadastro.";
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Erro de chave duplicada (email/cpf já existe)
                $_SESSION['mensagem_status'] = "Este e-mail ou CPF já está cadastrado.";
            } else {
                error_log('Erro no cadastro de usuário: ' . $e->getMessage());
                $_SESSION['mensagem_status'] = "Não foi possível concluir o cadastro.";
            }
            $_SESSION['tipo_mensagem'] = 'danger';
        }

    } else {
        $_SESSION['mensagem_status'] = "Todos os campos obrigatórios precisam ser preenchidos.";
        $_SESSION['tipo_mensagem'] = 'danger';
    }

    // Se chegou até aqui, algo deu errado. Redireciona de volta para a página de pets para mostrar o modal novamente.
    header('Location: pets-adocao.php?modal=show');
    exit();
}
?>
