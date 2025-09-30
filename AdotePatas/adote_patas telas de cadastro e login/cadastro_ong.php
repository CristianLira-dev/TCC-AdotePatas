<?php
// Inclui a conexão com o banco de dados
include_once 'conexao.php';

// Variáveis para mensagem de status
$mensagem_status = '';
$tipo_mensagem = ''; // success, danger, warning

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validação básica se todos os campos estão presentes
    if (isset($_POST['nome_ong'], $_POST['cnpj'], $_POST['email_ong'], $_POST['senha_ong'], $_POST['confirma_senha'])) {

        $nome_ong = $_POST['nome_ong'];
        $cnpj = $_POST['cnpj'];
        $email_ong = $_POST['email_ong'];
        $senha_ong = $_POST['senha_ong'];
        $confirma_senha = $_POST['confirma_senha'];

        if ($senha_ong !== $confirma_senha) {
            $mensagem_status = "As senhas não coincidem. Por favor, tente novamente.";
            $tipo_mensagem = 'warning';
        } else {
            // --- Segurança CRÍTICA: Implementando Hash de Senha ---
            $senha_hashed = password_hash($senha_ong, PASSWORD_DEFAULT);
            
            // --- Prevenção de Injeção SQL com Prepared Statements ---
            try {
                $sql = "INSERT INTO ong (nome, cnpj, email, senha) 
                        VALUES (:nome, :cnpj, :email, :senha)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt->execute([
                    ':nome' => $nome_ong,
                    ':cnpj' => $cnpj,
                    ':email' => $email_ong,
                    ':senha' => $senha_hashed,
                ])) {
                    $mensagem_status = "Cadastro da ONG realizado com sucesso! Você pode fazer login.";
                    $tipo_mensagem = 'success';
                } else {
                    $mensagem_status = "Erro ao cadastrar: " . implode(" ", $stmt->errorInfo());
                    $tipo_mensagem = 'danger';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $mensagem_status = "Este e-mail ou CNPJ já está cadastrado.";
                } else {
                    $mensagem_status = "Falha no banco de dados: " . $e->getMessage();
                }
                $tipo_mensagem = 'danger';
            }
        }
    } else {
        $mensagem_status = "Todos os campos obrigatórios precisam ser preenchidos.";
        $tipo_mensagem = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro ONG - Adote Patas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="assents/logo_simplificada.png"/>
    <link rel="stylesheet" href="css/variables.css">
    
    <style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital@0;1&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-image: url('assents/background.png');
            background-attachment: fixed;
            background-size: cover;
        }
        /* Estilos do Card (Largura Aumentada) */
        .container-card {
            background-color: rgba(180, 100, 89, 0.35); /* Fundo do card */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 10; 
            position: relative; 
        }
        /* Estilos dos Inputs (Ajustado o padding para aumentar o campo e a letra) */
        .input-style {
            background-color: rgba(180, 100, 89, 0.58); /* Cor do input de fundo (rosa escuro) */
            color: white;
            font-weight: 500;
            padding: 1.15rem; /* Aumento do padding */
            border: none;
            border-radius: 0.75rem; /* rounded-xl */
            transition: background-color 0.3s;
        }
        .input-style::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Estilos do Botão (Replicando o estilo do Login) */
         .adopt-btn-logar {
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            cursor: pointer;
        }

        .adopt-btn-logar:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25), 0 10px 25px rgba(0, 0, 0, 0.22);
        }

        .adopt-btn-logar:active {
            transform: translateY(1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* O coração com efeito de baixo-relevo (inset) */
        .heart-background {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); 
            z-index: 1; 
            font-size: 4rem; 
            line-height: 1;
            color: #a82626; 
            text-shadow: 
                1px 1px 2px rgba(0, 0, 0, 0.3),
                -1px -1px 2px rgba(255, 255, 255, 0.15); 
        }

        /* Texto (Cadastrar) */
        .adopt-btn-logar span {
            position: relative;
            z-index: 2; 
            text-shadow: 0px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* Classe para a Pata de Fundo */
        .pata-fundo {
            position: fixed;
            bottom: 0;
            right: 0;
            height: 24rem; /* h-96 */
            width: 40rem; /* w-96 */
            opacity: 0.4; /* opacity-40 */
            z-index: 0;
            pointer-events: none;
        }
        .cadastro-pessoal-link{
            position: fixed;
            bottom: 1rem; /* Espaçamento do topo */
            right: 8rem; /* Espaçamento da direita */
            z-index: 20; /* Acima de outros elementos */
        }
        /* Aplica a visibilidade responsiva (hidden lg:block) */
        @media (max-width: 1023px) { 
            .pata-fundo {
                display: none;
            }
        }
        @media (min-width: 1024px) { 
            .pata-fundo {
                display: block;
            }
        }

        /* Responsividade do Coração */
        @media (max-width: 575px) {
            .heart-background {
                font-size: 3rem; 
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

<!-- Pata Grande Fixa no Canto Inferior Direito -->
<img src="assents/pata.png" alt="Desenho de Pata" 
     class="pata-fundo">

<div class="text-center pt-2 cadastro-pessoal-link">
            <a href="cadastro.php" class="text-base text-[#980403] hover:text-[#980403] font-semibold border-b border-[#980403] hover:border-[#980403] transition duration-300">
                Cadastrar como conta pessoal
            </a>
        </div>
<div class="w-full flex items-center justify-between mb-8 relative">
        <!-- 1. Logo (Canto Esquerdo) -->
        <div> 
            <img src="assents/logo_simplificada.png" alt="Logo Adote Patas" class="h-30 w-40">
        </div>
        
        <!-- 2. Título (Centro Absoluto) -->
        <div class="absolute inset-x-0 bottom-0 text-center flex flex-col items-center">
            <h1 class="text-4xl font-extrabold text-[#666662]">
                Cadastro ONG
            </h1>
            <div class="w-12 h-1 bg-[#666662] mx-auto mt-1 rounded-full"></div>
        </div>

        <!-- 3. Espaço Vazio para Equilibrar -->
        <div class="h-16 w-16 invisible"></div> 
    </div>

<!-- Card Principal (Largura Aumentada e padding p-10) -->
<div class="container-card w-full max-w-md p-10 rounded-3xl shadow-xl">
    
    <!-- Logo e Título -->
    

    <!-- Mensagem de Status (sucesso/erro) -->
    <?php if (!empty($mensagem_status)): ?>
        <?php
            // Define classes de cor com base no tipo de mensagem
            $bg_class = $tipo_mensagem == 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        ?>
        <div class="<?php echo $bg_class; ?> border px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $mensagem_status; ?></span>
        </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form action="cadastro_ong.php" method="post" class="space-y-6"> 
        
        <!-- Nome da ONG -->
        <input type="text" id="nome_ong" name="nome_ong" placeholder="Nome Oficial da ONG" required 
               class="input-style w-full focus:ring-2 focus:ring-[#b34848] focus:outline-none">

        <!-- CNPJ -->
        <input type="text" id="cnpj" name="cnpj" placeholder="CNPJ" required 
               class="input-style w-full focus:ring-2 focus:ring-[#b34848] focus:outline-none">

        <!-- Email da ONG -->
        <input type="email" id="email_ong" name="email_ong" placeholder="E-mail de Contato da ONG" required 
               class="input-style w-full focus:ring-2 focus:ring-[#b34848] focus:outline-none">

        <!-- Senha -->
        <div class="relative">
            <input type="password" id="senha_ong" name="senha_ong" placeholder="Senha" required 
                   class="input-style w-full pr-12 focus:ring-2 focus:ring-[#b34848] focus:outline-none">
            <i id="toggleSenha" class="fas fa-eye absolute right-4 top-1/2 transform -translate-y-1/2 text-white cursor-pointer opacity-80 hover:opacity-100"></i>
        </div>
        
        <!-- Confirme a Senha -->
        <div class="relative">
            <input type="password" id="confirma_senha" name="confirma_senha" placeholder="Confirme a Senha" required 
                   class="input-style w-full pr-12 focus:ring-2 focus:ring-[#b34848] focus:outline-none">
            <i id="toggleConfirmaSenha" class="fas fa-eye absolute right-4 top-1/2 transform -translate-y-1/2 text-white cursor-pointer opacity-80 hover:opacity-100"></i>
        </div>
        

        
        <!-- Botão Cadastrar (LARGURA TOTAL E ESTILO LOGIN) -->
         <div class="flex justify-center w-full pt-4"> 
            <button type="submit" 
                    class="adopt-btn-logar bg-[#b92b2b] text-[#f0e9e9] font-bold text-2xl py-4 w-full rounded-2xl shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all duration-300 ease-in-out">
                <div class="heart-background" style="user-select: none;">❤</div>
                <span class="text-shadow">Cadastrar ONG</span>
            </button>
        </div>
        
        <!-- Link para voltar ao cadastro PF/Protetor -->
        
        
    </form>
    
    <!-- Link para voltar ao Login -->
    <div class="text-center mt-6">
        <a href="login.php" class="text-base text-[#980403] hover:text-[#980403] font-semibold border-b border-[#980403] hover:border-[#980403] transition duration-300">
            Já tem conta? Fazer Login
        </a>
    </div>
</div>

<!-- Script para Mostrar/Esconder Senha -->
<script>
    function setupPasswordToggle(inputId, toggleId) {
        document.getElementById(toggleId).addEventListener('click', function (e) {
            const senhaInput = document.getElementById(inputId);
            const type = senhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
            senhaInput.setAttribute('type', type);
            // Altera o ícone
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
    }

    // Configura os toggles de senha
    setupPasswordToggle('senha_ong', 'toggleSenha');
    setupPasswordToggle('confirma_senha', 'toggleConfirmaSenha');
</script>
</body>
</html>
