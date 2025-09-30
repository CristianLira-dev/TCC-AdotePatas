<?php
// Inclui a conexão com o banco de dados
include_once 'conexao.php';

// Variáveis para mensagem de status
$mensagem_status = '';
$tipo_mensagem = ''; // success, danger, warning

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta de dados
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    // O campo 'tipo' foi removido do formulário, então ele não existe mais aqui.

    if (empty($email) || empty($senha)) {
        $mensagem_status = "Por favor, preencha o e-mail e a senha.";
        $tipo_mensagem = 'danger';
    } else {
        $logado = false;

        // --- Tenta logar como Adotante (Tabela: usuario) ---
        try {
            $sql = "SELECT id_usuario, senha, email, nome FROM usuario WHERE email = :email LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                if (password_verify($senha, $usuario['senha'])) {
                    session_start();
                    $_SESSION['nome'] = $usuario['nome'];
                    $_SESSION['user_id'] = $usuario['id_usuario'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_tipo'] = 'adotante'; // Define o tipo aqui, após o sucesso
                    $logado = true;
                    header("Location: home.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Em caso de erro na primeira tentativa, registra o erro mas tenta a próxima
            error_log("Erro ao tentar logar como adotante: " . $e->getMessage());
        }

        // --- Se não logou, tenta logar como Protetor (Tabela: ong) ---
        if (!$logado) {
            try {
                // A tabela ong usa colunas diferentes: email_ong, senha_ong, id_ong
                $sql = "SELECT id_ong, senha, email, nome FROM ong WHERE email = :email LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    if (password_verify($senha, $usuario['senha'])) {
                        session_start();
                        $_SESSION['nome'] = $usuario['nome'];
                        $_SESSION['user_id'] = $usuario['id_ong'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_tipo'] = 'protetor'; // Define o tipo aqui, após o sucesso
                        $logado = true;
                        header("Location: home.php");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log("Erro ao tentar logar como protetor: " . $e->getMessage());
            }
        }

        // --- Mensagem de erro final se nenhuma das tentativas funcionou ---
        if (!$logado) {
            $mensagem_status = "E-mail ou senha incorretos.";
            $tipo_mensagem = 'warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Adote Patas</title>
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
        }
        /* Estilos do Card */
        .container-card {
            background-color: rgba(180, 100, 89, 0.35); /* Fundo do card */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 10; 
            position: relative; 
        }
        /* Estilos dos Inputs (Ajustado o padding para aumentar o campo e a letra) */
        .input-style {
            color: white;
            font-weight: 500;
            padding: 1.15rem; /* Aumento do padding */
            border: none;
            border-radius: 0.75rem; /* rounded-xl */
            transition: background-color 0.3s;
        }

        .email-input{
            text-transform: lowercase;
            letter-spacing: 0.05em; /* Espaçamento entre letras */
            font-size: 1.1rem; /* Aumento do tamanho da fonte */
            background-color: rgba(180, 100, 89, 0.58); /* Cor do input de fundo (rosa escuro) */
        }

        .senha-input{
            font-size: 1.1rem; /* Aumento do tamanho da fonte */
            background-color: rgba(152, 4, 3, 0.58); /* Cor do input de fundo (rosa escuro) */
    
        }
        .input-style::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }
        


        /* Estilos do Botão */
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

        /* Texto (Logar) */
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

        .criar-conta-link {
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


<div class="text-center mt-6 criar-conta-link">
        <a href="cadastro.php" class="text-base text-[#980403] hover:text-[#b34848] font-semibold border-b border-[#980403] hover:border-gray-800 transition duration-300">
            Criar conta
        </a>


    </div>     
     <div class="w-full flex items-center justify-between mb-8 relative">
        <!-- 1. Logo (Canto Esquerdo) -->
        <div> 
            <img src="assents/logo_simplificada.png" alt="Logo Adote Patas" class="h-30 w-40">
        </div>
        
        <!-- 2. Título (Centro Absoluto) (Aumento: text-3xl -> text-4xl) -->
        <div class="absolute inset-x-0 bottom-0 text-center flex flex-col items-center">
            <h1 class="text-4xl font-extrabold text-[#666662]">
                Login
            </h1>
            <div class="w-12 h-1 bg-[#666662] mx-auto mt-1 rounded-full"></div>
        </div>

        <!-- 3. Espaço Vazio para Equilibrar -->
        <div class="h-16 w-16 invisible"></div> 
    </div>


<div class="container-card w-full max-w-md p-10 rounded-3xl shadow-xl">

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
    <form action="login.php" method="post" class="space-y-6"> <!-- Aumento do espaço vertical -->
        
        <!-- E-mail -->
        <input type="email" id="email" name="email" placeholder="E-mail" required 
               class="input-style w-full focus:ring-2 focus:ring-[#b34848] focus:outline-none email-input">

        <!-- Senha -->
        <div class="relative">
            <input type="password" id="senha" name="senha" placeholder="Senha" required 
                   class="input-style w-full pr-12 focus:ring-2 focus:ring-[#b34848] focus:outline-none senha-input">
            <i id="toggleSenha" class="fas fa-eye absolute right-4 top-1/2 transform -translate-y-1/2 text-white cursor-pointer opacity-80 hover:opacity-100"></i>
        </div>
        
        
        <!-- Link Esqueci a Senha -->
        <div class="flex justify-end pt-2">
            <a href="#" class="text-base text-[#980403] border-b border-[#980403] hover:text-[#b34848] font-medium transition duration-300">
                Esqueci a senha
            </a>
        </div>

        <!-- Botão Entrar -->
         <div class="flex justify-center w-full pt-4"> 
            <button type="submit" 
                    class="adopt-btn-logar bg-[#b92b2b] text-[#f0e9e9] font-bold text-2xl py-4  w-full rounded-2xl shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all duration-300 ease-in-out">
                <div class="heart-background" style="user-select: none;">❤</div>
                <span class="text-shadow">Entrar</span>
            </button>
        </div>
    </form>
    
</div>

<!-- Script para Mostrar/Esconder Senha -->
<script>
    document.getElementById('toggleSenha').addEventListener('click', function (e) {
        const senhaInput = document.getElementById('senha');
        const type = senhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
        senhaInput.setAttribute('type', type);
        // Altera o ícone
        this.classList.toggle('fa-eye-slash');
        this.classList.toggle('fa-eye');
    });
</script>
</body>
</html>
