<?php
// Inclui a conexão com o banco de dados
include_once 'conexao.php';
appStartSession();

// --- FUNÇÕES DE VALIDAÇÃO (PHP) ---

// Valida a estrutura do CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Valida a estrutura do CNPJ
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);

    // Valida o tamanho e se todos os dígitos são iguais
    if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }

    // Valida o primeiro dígito verificador
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
        return false;
    }

    // Valida o segundo dígito verificador
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    if ($cnpj[13] != ($resto < 2 ? 0 : 11 - $resto)) {
        return false;
    }

    return true;
}

// Valida a força da senha
function validarForcaSenha($senha) {
    $erros = [];
    if (strlen($senha) < 8) $erros[] = "A senha deve ter no mínimo 8 caracteres.";
    if (!preg_match('/[A-Z]/', $senha)) $erros[] = "A senha deve conter ao menos uma letra maiúscula.";
    if (!preg_match('/[0-9]/', $senha)) $erros[] = "A senha deve conter ao menos um número.";
    if (!preg_match('/[\W_]/', $senha)) $erros[] = "A senha deve conter ao menos um caractere especial.";
    return $erros;
}

// --- INÍCIO DA PARTE ALTERADA ---

// --- LÊ MENSAGENS E ABAS DA SESSÃO (PADRÃO PRG) ---
$mensagem_status = $_SESSION['mensagem_status'] ?? '';
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? '';
$active_tab_from_session = $_SESSION['active_tab'] ?? null;

// Limpa as mensagens da sessão para que não apareçam novamente se o usuário atualizar a página
unset($_SESSION['mensagem_status']);
unset($_SESSION['tipo_mensagem']);
unset($_SESSION['active_tab']);

// --- CONFIGURAÇÃO INICIAL ---
// --- Bloco Corrigido (Melhor Prática) ---
// Define a aba ativa, priorizando a URL e usando a sessão como fallback
if (isset($_GET['tab']) && in_array($_GET['tab'], ['login', 'cadastro_usuario', 'cadastro_ong'])) {
    $active_tab = $_GET['tab']; // Prioridade 1: URL
} elseif ($active_tab_from_session) {
    $active_tab = $active_tab_from_session; // Prioridade 2: Sessão (após um POST)
} else {
    $active_tab = 'login'; // Valor padrão
}

// --- PROCESSAMENTO DOS FORMULÁRIOS (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_type = $_POST['form_type'] ?? '';

    switch ($form_type) {
        // --- CASO 1: LOGIN ---
        case 'login':
            $active_tab = 'login';
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $senha = $_POST['senha'] ?? '';

            if (!appRateLimit('login-ip', 50, 3600, appClientIp())
                || !appRateLimit('login-account', 10, 900, $email)) {
                $mensagem_status = "Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.";
                $tipo_mensagem = 'danger';
            } elseif (empty($email) || empty($senha)) {
                $mensagem_status = "Por favor, preencha o e-mail e a senha.";
                $tipo_mensagem = 'danger';
            } else {
                $logado = false;

                // Tenta logar como Adotante (Tabela: usuario)
                try {
                    $sql = "SELECT id_usuario, senha, nome FROM usuario WHERE email = :email LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($usuario && password_verify($senha, $usuario['senha'])) {
                        appCompleteLogin((int) $usuario['id_usuario'], 'usuario', (string) $usuario['nome'], $email);
                        $logado = true;
                        header("Location:  ./");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Erro ao logar como usuario: " . $e->getMessage());
                }

                // Tenta logar como Protetor/ONG (Tabela: ong)
                if (!$logado) {
                    try {
                        $sql = "SELECT id_ong, senha, nome FROM ong WHERE email = :email LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':email', $email);
                        $stmt->execute();
                        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($usuario && password_verify($senha, $usuario['senha'])) {
                            appCompleteLogin((int) $usuario['id_ong'], 'ong', (string) $usuario['nome'], $email);
                            $logado = true;
                            header("Location: ./ ");
                            exit;
                        }
                    } catch (PDOException $e) {
                        error_log("Erro ao logar como ong: " . $e->getMessage());
                    }
                }

                //  Tenta logar como ADMINISTRADOR
                if (!$logado) {
                    try {
                        $sql = "SELECT id_admin, senha, nome FROM administrador WHERE email = :email LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':email', $email);
                        $stmt->execute();
                        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                        $adminPassword = (string) ($admin['senha'] ?? '');
                        $adminAuthenticated = $admin && password_verify($senha, $adminPassword);

                        // Migração segura e automática para instalações antigas que ainda
                        // possuem a senha administrativa em texto puro.
                        if ($admin && !$adminAuthenticated && (password_get_info($adminPassword)['algo'] ?? 0) === 0
                            && hash_equals($adminPassword, $senha)) {
                            $newHash = password_hash($senha, PASSWORD_DEFAULT);
                            $updatePassword = $conn->prepare(
                                'UPDATE administrador SET senha = :senha WHERE id_admin = :id_admin'
                            );
                            $updatePassword->execute([
                                ':senha' => $newHash,
                                ':id_admin' => (int) $admin['id_admin'],
                            ]);
                            $adminAuthenticated = true;
                        }

                        if ($adminAuthenticated) {
                            appCompleteLogin((int) $admin['id_admin'], 'admin', (string) $admin['nome'], $email);
                            $logado = true;
                            
                            // Redireciona direto para o painel admin dentro do perfil
                            header("Location: perfil?page=painel-admin");
                            exit;
                        }
                    } catch (PDOException $e) {
                        error_log("Erro ao logar como admin: " . $e->getMessage());
                    }
                }
                
                if (!$logado) {
                    $mensagem_status = "E-mail ou senha incorretos.";
                    $tipo_mensagem = 'warning';
                }
            }
            break;

    // --- CASO 2: CADASTRO DE USUÁRIO COM VALIDAÇÃO ROBUSTA (VERSÃO CORRIGIDA) ---
case 'cadastro_usuario':
    $active_tab = 'cadastro_usuario';
    $nome = trim($_POST['nome-completo'] ?? '');
    $cpf = trim($_POST['cpf-cadastro'] ?? '');
    $email = trim($_POST['email-cadastro'] ?? '');
    $senha = $_POST['senha-cadastro'] ?? '';
    $confirma_senha = $_POST['confirma-senha-cadastro'] ?? '';
    $cep = trim($_POST['cep-cadastro'] ?? '');
    $logradouro = trim($_POST['logradouro-cadastro'] ?? '');
    $numero = trim($_POST['numero-cadastro'] ?? '');
    $complemento = trim($_POST['complemento-cadastro'] ?? '');
    $bairro = trim($_POST['bairro-cadastro'] ?? '');
    $cidade = trim($_POST['cidade-cadastro'] ?? '');
    $estado = trim($_POST['estado-cadastro'] ?? '');
    
    $erros = [];

    // VALIDAÇÃO EM CASCATA: Se um erro fundamental é encontrado, não continua para o próximo.
    
    // 1. Validação de campos vazios
    if (empty($nome) || empty($cpf) || empty($email) || empty($senha) || empty($confirma_senha) ||
        empty($cep) || empty($logradouro) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
        $erros[] = "Todos os campos, exceto 'complemento', são obrigatórios.";
    } else {
        // Se os campos não estão vazios, prossiga com validações de formato e regras
        
        // 2. Validação do Nome Completo
        if (strpos($nome, ' ') === false) {
            $erros[] = "Por favor, digite seu nome completo.";
        }

        // 3. Validação do E-mail (formato)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = "O formato do e-mail é inválido.";
        }

        // 4. Validação do CPF (formato)
        if (!validarCPF($cpf)) {
            $erros[] = "O CPF informado é inválido.";
        }
        
        // 5. Validação da Senha (força e confirmação)
        $erros_senha = validarForcaSenha($senha);
        if (!empty($erros_senha)) {
            $erros = array_merge($erros, $erros_senha);
        } elseif ($senha !== $confirma_senha) {
            $erros[] = "As senhas não coincidem.";
        }
    }

    // --- Verificação de Duplicidade no Banco (SÓ SE NÃO HOUVER ERROS DE FORMATO) ---
    if (empty($erros)) {
        // Checa E-mail
        $sql = "SELECT id_usuario FROM usuario WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $erros[] = "Este e-mail já está cadastrado.";
        }

        // Checa CPF
        $sql = "SELECT id_usuario FROM usuario WHERE cpf = :cpf LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':cpf' => preg_replace('/[^0-9]/', '', $cpf)]);
        if ($stmt->fetch()) {
            $erros[] = "Este CPF já está cadastrado.";
        }
    }
    
    // Em: login -> case 'cadastro_usuario'

// --- Decisão Final ---
if (!empty($erros)) {
    // Se houver erros, o comportamento atual está correto:
    // mostra a mensagem e mantém os dados na tela.
    $mensagem_status = $erros[0];
    $tipo_mensagem = 'danger';
    $active_tab = 'cadastro_usuario';
} else {
    // Se NÃO houver erros, insere no banco e usa o padrão PRG.
    $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);
        try {
            // --- ATUALIZAR O SQL INSERT ---
            $sql = "INSERT INTO usuario (nome, email, senha, cpf, cep, logradouro, numero, complemento, bairro, cidade, estado) 
                    VALUES (:nome, :email, :senha, :cpf, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':nome' => $nome, 
                ':email' => $email, 
                ':senha' => $senha_hashed, 
                ':cpf' => preg_replace('/[^0-9]/', '', $cpf),
                ':cep' => $cep,
                ':logradouro' => $logradouro,
                ':numero' => $numero,
                ':complemento' => $complemento,
                ':bairro' => $bairro,
                ':cidade' => $cidade,
                ':estado' => $estado
            ]);

        // --- INÍCIO DA LÓGICA PRG ---
        // 1. Salva a mensagem de sucesso e a aba de destino na sessão.
        $_SESSION['mensagem_status'] = "Cadastro realizado com sucesso! Você já pode fazer login.";
        $_SESSION['tipo_mensagem'] = 'success';
        $_SESSION['active_tab'] = 'login';

        // 2. Redireciona para a mesma página para limpar os dados do POST.
        header("Location: login");
        exit();
        // --- FIM DA LÓGICA PRG ---

    } catch (PDOException $e) {
        $mensagem_status = "Ocorreu uma falha no banco de dados. Tente novamente.";
        $tipo_mensagem = 'danger';
        $active_tab = 'cadastro_usuario';
        error_log("Erro no cadastro: " . $e->getMessage());
    }
}
break; // Fim do 'case cadastro_usuario'

        // --- CASO 3: CADASTRO DE ONG ---
        // --- CASO 3: CADASTRO DE ONG COM VALIDAÇÃO ROBUSTA ---
case 'cadastro_ong':
    $active_tab = 'cadastro_ong';
    $nome_ong = trim($_POST['nome_ong'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $email_ong = trim($_POST['email_ong'] ?? '');
    $senha_ong = $_POST['senha_ong'] ?? '';
    $confirma_senha_ong = $_POST['confirma_senha_ong'] ?? '';
    $cep_ong = trim($_POST['cep-ong'] ?? '');
    $logradouro_ong = trim($_POST['logradouro-ong'] ?? '');
    $numero_ong = trim($_POST['numero-ong'] ?? '');
    $complemento_ong = trim($_POST['complemento-ong'] ?? '');
    $bairro_ong = trim($_POST['bairro-ong'] ?? '');
    $cidade_ong = trim($_POST['cidade-ong'] ?? '');
    $estado_ong = trim($_POST['estado-ong'] ?? ''); 

    $erros = [];

    // 1. Validação de campos vazios
    if (empty($nome_ong) || empty($cnpj) || empty($email_ong) || empty($senha_ong) || empty($confirma_senha_ong) ||
        empty($cep_ong) || empty($logradouro_ong) || empty($numero_ong) || empty($bairro_ong) || empty($cidade_ong) || empty($estado_ong)) { // <-- ADICIONADO
        $erros[] = "Todos os campos, exceto 'complemento', são obrigatórios.";
    } else {
        // 2. Validações de formato e regras
        if (!validarCNPJ($cnpj)) {
            $erros[] = "O CNPJ informado é inválido.";
        }
        if (!filter_var($email_ong, FILTER_VALIDATE_EMAIL)) {
            $erros[] = "O formato do e-mail é inválido.";
        }

        // 3. Validação de força e confirmação de senha
        $erros_senha = validarForcaSenha($senha_ong);
        if (!empty($erros_senha)) {
            $erros = array_merge($erros, $erros_senha);
        } elseif ($senha_ong !== $confirma_senha_ong) {
            $erros[] = "As senhas não coincidem.";
        }
    }

    // 4. Verificação de duplicidade no banco (APENAS se não houver erros de formato)
    if (empty($erros)) {
        // Checa E-mail na tabela de ONGs
        $sql = "SELECT id_ong FROM ong WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email_ong]);
        if ($stmt->fetch()) {
            $erros[] = "Este e-mail já está cadastrado para uma ONG.";
        }

        // Checa CNPJ
        $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
        $sql = "SELECT id_ong FROM ong WHERE cnpj = :cnpj LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':cnpj' => $cnpj_limpo]);
        if ($stmt->fetch()) {
            $erros[] = "Este CNPJ já está cadastrado.";
        }
    }

    // 5. Decisão Final: Cadastrar ou mostrar erro
    if (!empty($erros)) {
        $mensagem_status = $erros[0]; // Mostra o primeiro erro encontrado
        $tipo_mensagem = 'danger';
    } else {
        // Tudo certo, prossegue com o cadastro
        $senha_hashed = password_hash($senha_ong, PASSWORD_DEFAULT);
        try {
            // --- ATUALIZAR O SQL INSERT ---
            $sql = "INSERT INTO ong (nome, email, senha, cnpj, cep, logradouro, numero, complemento, bairro, cidade, estado) 
                    VALUES (:nome, :email, :senha, :cnpj, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':nome' => $nome_ong,
                ':email' => $email_ong,
                ':senha' => $senha_hashed,
                ':cnpj' => preg_replace('/[^0-9]/', '', $cnpj),
                ':cep' => $cep_ong,
                ':logradouro' => $logradouro_ong,
                ':numero' => $numero_ong,
                ':complemento' => $complemento_ong,
                ':bairro' => $bairro_ong,
                ':cidade' => $cidade_ong,
                ':estado' => $estado_ong
            ]);

            // Padrão PRG: Salva mensagem na sessão e redireciona
            $_SESSION['mensagem_status'] = "Cadastro de ONG realizado com sucesso! Você já pode fazer login.";
            $_SESSION['tipo_mensagem'] = 'success';
            $_SESSION['active_tab'] = 'login';

            header("Location: login");
            exit();

        } catch (PDOException $e) {
            $mensagem_status = "Ocorreu uma falha no banco de dados. Tente novamente.";
            $tipo_mensagem = 'danger';
            error_log("Erro no cadastro de ONG: " . $e->getMessage());
        }
    }
    break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Adote Patas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="assets/css/pages/autenticacao/autenticacao.css">
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">


<!-- ANIMAÇÃO DE REDIRECIONAMENTO - VERSÃO MELHORADA -->
<?php 
$showAnimation = isset($_GET['animation']) && $_GET['animation'] === 'success';
$animationMessage = $_GET['message'] ?? 'Redirecionando...';
?>

<?php if ($showAnimation): ?>
<div id="redirect-animation" class="fixed inset-0 bg-white z-50 flex items-center justify-center">
    <div class="text-center">
        <lottie-player 
            src="animações/pet-run.json" 
            background="transparent" 
            speed="1" 
            style="width: 500px; height: 500px;" 
            autoplay
            loop>
        </lottie-player>
        <p class="text-[#628e6d] text-xl font-bold" style="margin-top: -4rem;"><?php echo htmlspecialchars($animationMessage); ?></p>
        <div class="w-24 h-1 bg-[#666662] mx-auto mt-2 rounded-full opacity-50"></div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const animationElement = document.getElementById('redirect-animation');
            if (animationElement) {
                animationElement.style.opacity = '0';
                animationElement.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    animationElement.style.display = 'none';
                    
                    // Remove os parâmetros da URL sem recarregar
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('animation');
                        url.searchParams.delete('message');
                        window.history.replaceState({}, '', url);
                    }
                }, 300);
            }
        }, 2300);
    });
</script>
<?php endif; ?>

<!-- Modal de Recuperação de Senha -->
    <div id="recovery-modal" class="fixed inset-0 z-50 bg-black bg-opacity-50 hidden items-center justify-center p-4" style="z-index: 1000;">
        <div class="bg-white shadow-2xl p-6 w-full max-w-md mx-auto" style="margin-top: 10%; border-radius: 16px">
            <!-- Título e Botão de Fechar -->
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h1 class="text-2xl font-bold text-gray-600">Recuperar Senha</h1>
                <button id="close-recovery-modal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- 1. Estado do Formulário de Recuperação -->
            <div id="recovery-form-state">
                <p class="text-md text-gray-600 mb-4">
                    Insira o <strong style="color: var(--cor-vermelho);">e-mail</strong> associado à sua conta para receber um link de redefinição de senha.
                </p>
                <form id="recuperar-form" action="recuperar-senha.php" method="POST">
                    <div class="mb-4">
                        <!-- ID do input é 'email_recuperar' conforme esperado no recuperar-senha.php -->
                        <input type="email" id="email_recuperar" name="email_recuperar" placeholder="E-mail" required
                            class="input-style w-full">
                        <p id="recovery-error-message" class="text-sm text-red-500 mt-1 hidden text-left"></p>
                    </div>

<div class="flex justify-center">
    <button type="submit" class="adopt-btn w-48 justify-center">
        
        <div class="heart-background" aria-hidden="true">
            <i class="bi bi-heart-fill"></i>
        </div>
        
        <span>Enviar Link</span>
    </button>
</div>

                </form>
            </div>

            <!-- 2. Estado de Sucesso (Enviado com sucesso) -->
            <div id="success-state" class="hidden text-center">
            <i class="fas fa-envelope-open-text text-5xl text-pink-500 mb-4" ></i>
            <h3 class="text-2xl font-bold text-gray-800">Verifique seu e-mail</h3>
            <p class="text-gray-600 mt-2">
                Enviamos um link de redefinição para <br>
                <strong id="sent-email-address" class="text-gray-900"></strong>
            </p>
            <div class="mt-8">
                <button id="resend-btn" class="text-gray-600 font-semibold" disabled>
                    Reenviar em (<span id="resend-timer">30</span>s)
                </button>
            </div>
        </div>

             
        </div>
    </div>
    <!-- Fim Modal -->


<div id="toast-notification" class="toast p-0" style="display: none;">
    <div id="toast-icon" class="toast-icon"></div>
    <div class="toast-content">
        <p id="toast-message" class="toast-message">Mensagem de exemplo.</p>
    </div>
    <div class="toast-progress-bar"></div>
</div>

<a href="./" class="btn-voltar" title="Voltar para a página inicial">
    <i class="fa-solid fa-arrow-left"></i>
    <span>Voltar</span>
</a>

<img src="images/cadastro-login/pata.png" alt="Desenho de Pata" class="pata-fundo">

<div class="w-full max-w-lg mx-auto">
    <div class="w-full flex items-center justify-between mb-6 relative">
        <div>
            <a href="./" title="Voltar para a página inicial">
                <img src="images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" width="70" height="70">
            </a>
        </div>
        <div class="absolute inset-x-0 text-center">
            <h1 id="page-title" class="text-xl md:text-4xl font-bold text-[#666662]">Entrar</h1>
            <div class="w-24 h-1 bg-[#666662] mx-auto mt-1 rounded-full"></div>
        </div>
        <div class="h-16 w-16 invisible"></div>
    </div>

    <div class="container-card w-full p-6 sm:p-10 rounded-3xl shadow-xl">
        
        <div class="flex border-b-2 border-white/20 mb-6">
            <button data-tab="login" class="tab-btn flex-1 py-3 text-sm  md:text-xl font-bold text-white/70 transition-all duration-300">Entrar</button>
            <button data-tab="cadastro_usuario" class="tab-btn flex-1 py-3 text-sm  md:text-xl  font-bold text-white/70 transition-all duration-300">Cadastro</button>
            <button data-tab="cadastro_ong" class="tab-btn flex-1 py-3 text-sm  md:text-xl font-bold text-white/70 transition-all duration-300">Cadastro ONG</button>
        </div>

        <?php if (!empty($mensagem_status)): ?>
            <div id="php-data" 
                 data-message="<?php echo htmlspecialchars($mensagem_status); ?>" 
                 data-type="<?php echo htmlspecialchars($tipo_mensagem); ?>" 
                 style="display: none;">
            </div>
        <?php endif; ?>

<div id="login" class="form-container">
    <form action="login" method="post" class="space-y-6">
        <input type="hidden" name="form_type" value="login">
        <div>
            <label for="email_login" class="sr-only">E-mail</label> <input type="email" name="email" id="email_login" placeholder="E-mail" required class="input-style w-full email-input"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="relative">
             <label for="senha_login" class="sr-only">Senha</label> <input type="password" id="senha_login" name="senha" placeholder="Senha" required class="input-style w-full pr-12 senha-input">
            <i class="fas fa-eye toggle-senha" data-target="senha_login"></i>
        </div>
        <div class="flex justify-end pt-2">
            <a href="#" id="open-recovery-modal" class="link-style">Esqueci a senha</a>
        </div>

<div class="flex justify-center w-40 mx-auto">
    <button type="submit" class="adopt-btn">
        
        <div class="heart-background" aria-hidden="true">
            <i class="bi bi-heart-fill"></i>
        </div>
        
        <span>Entrar</span>
    </button>
</div>

    </form>
</div>


<div id="cadastro_usuario" class="form-container">
    <form action="login" method="post" id="form-cadastro" class="space-y-6">
        <input type="hidden" name="form_type" value="cadastro_usuario">
        <div>
            <label for="nome-completo" class="sr-only">Nome Completo</label>
            <input type="text" name="nome-completo" id="nome-completo" placeholder="Nome Completo" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($nome ?? ''); ?>">
            <div id="mensagem-nome-completo" class="mensagem-validacao"></div>
        </div>
        <div>
            <label for="cpf-cadastro" class="sr-only">CPF</label> <input type="text" name="cpf-cadastro" id="cpf-cadastro" placeholder="CPF" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($cpf ?? ''); ?>">
            <div id="mensagem-cpf-cadastro" class="mensagem-validacao"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <label for="cep-cadastro" class="sr-only">CEP</label>
                <input type="text" name="cep-cadastro" id="cep-cadastro" placeholder="CEP" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['cep-cadastro'] ?? ''); ?>">
                <div id="mensagem-cep-cadastro" class="mensagem-validacao"></div>
            </div>
            <div class="md:col-span-2">
                <label for="logradouro-cadastro" class="sr-only">Rua / Logradouro</label>
                <input type="text" name="logradouro-cadastro" id="logradouro-cadastro" placeholder="Rua / Logradouro" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['logradouro-cadastro'] ?? ''); ?>" readonly>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <label for="numero-cadastro" class="sr-only">Número</label>
                <input type="text" name="numero-cadastro" id="numero-cadastro" placeholder="Número" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['numero-cadastro'] ?? ''); ?>">
            </div>
            <div class="md:col-span-2">
                <label for="complemento-cadastro" class="sr-only">Complemento (Opcional)</label>
                <input type="text" name="complemento-cadastro" id="complemento-cadastro" placeholder="Complemento (Opcional)" class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['complemento-cadastro'] ?? ''); ?>">
            </div>
        </div>

        <div>
            <div>
                <label for="bairro-cadastro" class="sr-only">Bairro</label>
                <input type="text" name="bairro-cadastro" id="bairro-cadastro" placeholder="Bairro" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['bairro-cadastro'] ?? ''); ?>" readonly>
            </div>
        </div>

            <!-- No formulário de cadastro_usuario -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
    <div class="md:col-span-3">
        <label for="cidade-cadastro" class="sr-only">Cidade</label>
        <input type="text" name="cidade-cadastro" id="cidade-cadastro" placeholder="Cidade" required 
               class="input-style w-full" value="<?php echo htmlspecialchars($_POST['cidade-cadastro'] ?? ''); ?>" readonly>
    </div>
    <div class="md:col-span-1">
        <label for="estado-cadastro" class="sr-only">UF</label>
        <input type="text" name="estado-cadastro" id="estado-cadastro" placeholder="UF" required 
               class="input-style w-full text-center md:text-left" 
               value="<?php echo htmlspecialchars($_POST['estado-cadastro'] ?? ''); ?>" readonly>
    </div>
</div>

        <div>
            <label for="email-cadastro" class="sr-only">E-mail</label> <input type="email" name="email-cadastro" id="email-cadastro" placeholder="E-mail" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($email ?? ''); ?>">
            <div id="mensagem-email-cadastro" class="mensagem-validacao"></div>
        </div>
        <div>
            <div class="relative">
                <label for="senha-cadastro" class="sr-only">Senha</label> <input type="password" id="senha-cadastro" name="senha-cadastro" placeholder="Senha" required class="input-style w-full pr-12 senha-input">
                <i class="fas fa-eye toggle-senha" data-target="senha-cadastro"></i>
            </div>
            <div id="mensagem-senha-cadastro" class="mensagem-validacao"></div>
        </div>
        <div>
            <div class="relative">
                <label for="confirma-senha-cadastro" class="sr-only">Confirmar a Senha</label> <input type="password" id="confirma-senha-cadastro" name="confirma-senha-cadastro" placeholder="Confirmar a Senha" required class="input-style w-full pr-12 senha-input">
                <i class="fas fa-eye toggle-senha" data-target="confirma-senha-cadastro"></i>
            </div>
            <div id="mensagem-confirma-senha-cadastro" class="mensagem-validacao"></div>
        </div>

        <div class="adotepatas-termos">
           <p style="color: var(--cor-cinza-texto);">
    Ao se cadastrar, você concorda com nossos 
    <a href="termos-de-uso.php" style="color: var(--cor-vermelho); text-decoration: underline;">Termos de Uso</a> 
    e 
    <a href="politicas-privacidade.php" style="color: var(--cor-vermelho); text-decoration: underline;">Políticas de Privacidade</a>. 
</p>
        </div>

<div class="flex justify-center w-40 mx-auto">
    <button type="submit" class="adopt-btn">
        
        <div class="heart-background" aria-hidden="true">
            <i class="bi bi-heart-fill"></i>
        </div>
        
        <span>Cadastrar</span>
    </button>
</div>

    </form>
</div>

 
<div id="cadastro_ong" class="form-container">
    <form action="cadastro-ong" method="post" id="form-cadastro-ong" class="space-y-6">
        <input type="hidden" name="form_type" value="cadastro_ong">
        <div>
            <label for="nome_ong" class="sr-only">Nome Oficial da ONG</label> <input type="text" name="nome_ong" id="nome_ong" placeholder="Nome Oficial da ONG" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($_POST['nome_ong'] ?? ''); ?>">
            <div id="mensagem-nome_ong" class="mensagem-validacao"></div>
        </div>
        <div>
            <label for="cnpj" class="sr-only">CNPJ</label> <input type="text" name="cnpj" id="cnpj" placeholder="CNPJ" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>">
            <div id="mensagem-cnpj" class="mensagem-validacao"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <label for="cep-ong" class="sr-only">CEP</label>
                <input type="text" name="cep-ong" id="cep-ong" placeholder="CEP" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['cep-ong'] ?? ''); ?>">
                <div id="mensagem-cep-ong" class="mensagem-validacao"></div>
            </div>
            <div class="md:col-span-2">
                <label for="logradouro-ong" class="sr-only">Rua / Logradouro</label>
                <input type="text" name="logradouro-ong" id="logradouro-ong" placeholder="Rua / Logradouro" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['logradouro-ong'] ?? ''); ?>" readonly>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <label for="numero-ong" class="sr-only">Número</label>
                <input type="text" name="numero-ong" id="numero-ong" placeholder="Número" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['numero-ong'] ?? ''); ?>">
            </div>
            <div class="md:col-span-2">
                <label for="complemento-ong" class="sr-only">Complemento (Opcional)</label>
                <input type="text" name="complemento-ong" id="complemento-ong" placeholder="Complemento (Opcional)" class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['complemento-ong'] ?? ''); ?>">
            </div>
        </div>

        <div>
            <div>
                <label for="bairro-ong" class="sr-only">Bairro</label>
                <input type="text" name="bairro-ong" id="bairro-ong" placeholder="Bairro" required class="input-style w-full"
                       value="<?php echo htmlspecialchars($_POST['bairro-ong'] ?? ''); ?>" readonly>
            </div>
        </div>
<!-- No formulário de cadastro_ong -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
    <div class="md:col-span-3">
        <label for="cidade-ong" class="sr-only">Cidade</label>
        <input type="text" name="cidade-ong" id="cidade-ong" placeholder="Cidade" required 
               class="input-style w-full" value="<?php echo htmlspecialchars($_POST['cidade-ong'] ?? ''); ?>" readonly>
    </div>
    <div class="md:col-span-1">
        <label for="estado-ong" class="sr-only">UF</label>
        <input type="text" name="estado-ong" id="estado-ong" placeholder="UF" required 
               class="input-style w-full text-center md:text-left" 
               value="<?php echo htmlspecialchars($_POST['estado-ong'] ?? ''); ?>" readonly>
    </div>
</div>
        <div>
            <label for="email_ong" class="sr-only">E-mail</label> <input type="email" name="email_ong" id="email_ong" placeholder="E-mail" required class="input-style w-full"
                   value="<?php echo htmlspecialchars($_POST['email_ong'] ?? ''); ?>">
            <div id="mensagem-email_ong" class="mensagem-validacao"></div>
        </div>
        <div>
            <div class="relative">
                <label for="senha_ong" class="sr-only">Senha</label> <input type="password" id="senha_ong" name="senha_ong" placeholder="Senha" required class="input-style w-full pr-12 senha-input">
                <i class="fas fa-eye toggle-senha" data-target="senha_ong"></i>
            </div>
            <div id="mensagem-senha_ong" class="mensagem-validacao"></div>
        </div>
        <div>
            <div class="relative">
                <label for="confirma_senha_ong" class="sr-only">Confirmar a Senha</label> <input type="password" id="confirma_senha_ong" name="confirma_senha_ong" placeholder="Confirmar a Senha" required class="input-style w-full pr-12 senha-input">
                <i class="fas fa-eye toggle-senha" data-target="confirma_senha_ong"></i>
            </div>
            <div id="mensagem-confirma_senha_ong" class="mensagem-validacao"></div>
        </div>

                <div class="adotepatas-termos">
           <p style="color: var(--cor-cinza-texto);">
    Ao se cadastrar, você concorda com nossos 
    <a href="termos-de-uso.php" style="color: var(--cor-vermelho); text-decoration: underline;">Termos de Uso</a> 
    e 
    <a href="politicas-privacidade.php" style="color: var(--cor-vermelho); text-decoration: underline;">Políticas de Privacidade</a>. 
</p>
        </div>

<div class="flex justify-center w-55 mx-auto">
    <button type="submit" class="adopt-btn">
        
        <div class="heart-background" aria-hidden="true">
            <i class="bi bi-heart-fill"></i>
        </div>
        
        <span>Cadastrar ONG</span>
    </button>
</div>

    </form>
</div>

<script>
    <?php
    // Essa lógica garante que, ao redirecionar após um erro de cadastro de usuário, a aba correta é reaberta
    if (!empty($mensagem_status) && ($_POST['form_type'] ?? '') === 'cadastro_usuario') {
        echo "window.activeTabOnLoad = 'cadastro_usuario';";
    } else {
        // Se a aba 'recuperar' veio da URL, o JS deve tratar o modal, mas a aba principal deve ser 'login'
        $tab_to_load = ($active_tab === 'recuperar') ? 'login' : $active_tab;
        echo "window.activeTabOnLoad = '" . $tab_to_load . "';";
    }
    ?>
</script>



<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
<script src="assets/js/pages/autenticacao/autenticacao.js" type="module"></script>
</body>
</html>
