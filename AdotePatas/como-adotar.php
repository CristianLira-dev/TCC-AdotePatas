<?php
require_once __DIR__ . '/app/security.php';
appStartSession();

include_once 'conexao.php';

$logado = isset($_SESSION['user_id']);
$usuario = null;
$user_id = null;
$user_tipo = null;
$primeiro_nome = '';
$pagina = "como-adotar"; // Definindo a página atual como "como-adotar"

// Carrega dados do usuário se estiver logado
if ($logado) {
    $user_id = $_SESSION['user_id'];
    $user_tipo = $_SESSION['user_tipo'] ?? null;

    try {
        if ($user_tipo == 'usuario') {
            $sql = "SELECT nome, email, cpf FROM usuario WHERE id_usuario = :id LIMIT 1";
        } elseif ($user_tipo == 'ong') {
            $sql = "SELECT nome, email, cnpj FROM ong WHERE id_ong = :id LIMIT 1";
        } else {
            $sql = null;
        }

        if (!empty($sql)) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // silencioso — manter UX
    }

    if ($logado && isset($_SESSION['nome'])) {
        $partes = explode(' ', $_SESSION['nome']);
        $primeiro_nome = $partes[0] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Como Adotar - Adote Patas</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/pages/como-adotar/adote.css">
</head>
<body>

<header>
  <nav class="navbar navbar-expand">
    <div class="container">
      <a class="navbar-brand" href="./">
        <img src="./images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
      </a>

      <?php if ($logado): ?>
        <!-- Navbar para usuário LOGADO -->
        <div class="d-flex align-items-center gap-4">
          <!-- Links "Sobre Nós" e "Ajuda" (visíveis em telas grandes) -->
          <div class="d-none d-xl-block">
            <ul class="navbar-nav d-flex flex-row gap-4 mb-0">
              <li class="nav-item">
                <a class="nav-link navlink" href="sobre-nos">Sobre Nós</a>
              </li>
              <li class="nav-item">
                <a class="nav-link navlink" href="ajuda.php">Ajuda</a>
              </li>
            </ul>
          </div>

          <!-- Nome e ícone do usuário -->
          <a href="perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-3 text-decoration-none" title="Ver meu perfil">
            <div class="d-flex align-items-center flex-row-reverse gap-2">
              <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
              <span class="profile-name fs-5" style="color: var(--cor-vermelho);"><?php echo htmlspecialchars($primeiro_nome); ?></span>
            </div>
          </a>

          <!-- Botão do menu (SEMPRE VISÍVEL) -->
          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>

      <?php else: ?>
        <!-- Navbar para usuário NÃO LOGADO -->
        <div class="d-flex align-items-center gap-4">
          <!-- Links visíveis apenas em telas grandes (>1000px) -->
          <div class="d-none d-xl-block">
            <ul class="navbar-nav d-flex flex-row align-items-center gap-4 mb-0">
              <li class="nav-item">
                <a class="nav-link navlink" href="sobre-nos">Sobre Nós</a>
              </li>
              <li class="nav-item">
                <a class="nav-link navlink" href="ajuda.php">Ajuda</a>
              </li>
              <li class="nav-item position-relative">
                <a class="nav-link loginlink" href="login">Entrar</a>
              </li>
            </ul>
          </div>

          <!-- Botão do menu (sempre visível) -->
          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>
      <?php endif; ?>
    </div>
  </nav>
</header>


<!-- Offcanvas completo e funcional -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
  <div class="offcanvas-header border-bottom">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  
  <div class="offcanvas-body p-0">
    <?php if ($logado): ?>
      <!-- Conteúdo para usuário LOGADO -->
      <aside class="profile-sidebar p-3">
        <div class="sidebar-header text-center mb-4">
          <i class="fa-regular fa-circle-user sidebar-profile-icon logged-in"></i>
          <h5 class="mt-2 mb-0">
            <?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário'); ?>
          </h5>
          <small class="text-muted fs-6">
            <?php echo htmlspecialchars(ucfirst($user_tipo ?? '')); ?>
          </small>
        </div>
        
        <nav class="nav nav-pills flex-column profile-nav">
          <!-- Links que aparecem apenas no offcanvas em telas pequenas -->
          <div class="d-xl-none">
            <a class="nav-link" href="sobre-nos">
              <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
            </a>
            <a class="nav-link" href="ajuda.php">
              <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
            </a>
            <hr class="my-2">
          </div>
          
          <a class="nav-link <?php echo ($pagina == 'perfil') ? 'active' : ''; ?>" 
             href="perfil?page=perfil" 
             <?php echo ($pagina == 'perfil') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil
          </a>
          
          <a class="nav-link <?php echo ($pagina == 'meus-pets') ? 'active' : ''; ?>" 
             href="perfil?page=meus-pets"
             <?php echo ($pagina == 'meus-pets') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets
          </a>

          <a class="nav-link <?php echo ($pagina == 'pets-curtidos') ? 'active' : ''; ?>" 
             href="perfil?page=pets-curtidos"
             <?php echo ($pagina == 'pets-curtidos') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-regular fa-heart fa-fw me-2"></i> Pets Curtidos
          </a>

          <a class="nav-link" href="chat.php">
            <i class="fa-regular fa-comments fa-fw me-3"></i> Chats
          </a>

          <hr class="my-2">
          
          <a class="nav-link logout-link-sidebar" href="sair.php">
            <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Sair
          </a>
        </nav>
      </aside>
    <?php else: ?>
      <!-- Conteúdo para usuário NÃO LOGADO -->
      <aside class="profile-sidebar p-3">
        <div class="sidebar-header text-center mb-4">
          <i class="fa-regular fa-circle-user sidebar-profile-icon logged-out"></i>
          <h5 class="mt-2 mb-0">Visitante</h5>
          <small class="text-muted fs-6">Faça login para acessar mais recursos</small>
        </div>
        
        <nav class="nav nav-pills flex-column profile-nav">
          <a class="nav-link" href="sobre-nos">
            <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
          </a>
          
          <a class="nav-link" href="ajuda.php">
            <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
          </a>
          
          <hr class="my-2">
          
          <a class="nav-link loginlink-sidebar" href="login">
            <i class="fa-solid fa-right-to-bracket fa-fw me-2"></i> Entrar
          </a>
        </nav>
      </aside>
    <?php endif; ?>
  </div>
</div>

<main class="como-adotar-section" id="como-adotar">
  <div class="container">
    
    <div class="row">
      <div class="col-12 text-center">
        <h1 class="section-title-kawaii">O Caminho para o seu Novo Amigo</h1>
        <p class="section-subtitle-poppins">Adotar é um ato de amor! Veja como é fácil:</p>
      </div>
    </div>

    <div class="row align-items-center">
      
      <!-- PASSOS - Primeiro em mobile, esquerda em desktop -->
      <div class="col-lg-6 order-1 order-lg-1">
        <ul class="adoption-steps">
          
          <li class="step-item">
            <div class="step-icon-wrapper">
              <i class="bi bi-person-fill"></i> 
            </div>
            <div class="step-content">
              <h3>1. Crie Sua Conta</h3>
              <p>Rápido e seguro.</p>
            </div>
          </li>

          <li class="step-item">
            <div class="step-icon-wrapper">
              <i class="bi bi-search"></i>
            </div>
            <div class="step-content">
              <h3>2. Procure pelo seu Amiguinho</h3>
              <p>Encontre o pet que mais combina com você.</p>
            </div>
          </li>

          <li class="step-item">
            <div class="step-icon-wrapper">
              <i class="bi bi-clipboard2-check-fill"></i>
            </div>
            <div class="step-content">
              <h3>3. Preencha o Formulário</h3>
              <p>Conte sobre você e seu lar.</p>
            </div>
          </li>

          <li class="step-item">
            <div class="step-icon-wrapper">
              <i class="bi bi-chat-dots-fill"></i>
            </div>
            <div class="step-content">
              <h3>4. Converse com a ONG</h3>
              <p>Tire dúvidas e combine.</p>
            </div>
          </li>

          <li class="step-item">
            <div class="step-icon-wrapper">
              <i class="bi bi-house-heart-fill"></i> 
            </div>
            <div class="step-content">
              <h3>5. Combine a Busca</h3>
              <p>Leve seu amiguinho para casa!</p>
            </div>
          </li>

        </ul>
      </div>

      <!-- IMAGEM - Segundo em mobile, direita em desktop -->
      <div class="col-lg-6 order-2 order-lg-2 mt-4 mt-lg-0">
        <div class="adoption-illustration text-center">
          <img src="./images/como-adotar/como-adotar-image.png" 
               alt="Ilustração de uma pessoa feliz abraçando dois cachorrinhos." 
               class="img-fluid">
        </div>
      </div>

    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>
