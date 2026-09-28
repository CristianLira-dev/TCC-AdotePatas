<?php
require_once __DIR__ . '/app/security.php';
appStartSession();

include_once 'conexao.php';

$logado = isset($_SESSION['user_id']);
$usuario = null;
$user_id = null;
$user_tipo = null;
$primeiro_nome = '';
$pagina = "sobre-nos"; // CORREÇÃO: Definindo a página atual

// Carrega dados do usuário se estiver logado (mesma lógica do index.php)
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
    <title>Sobre Nós - Adote Patas</title>
    <link rel="stylesheet" href="assets/css/pages/sobre/sobre.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
                <a class="nav-link navlink active" href="sobre-nos">Sobre Nós</a>
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
                <a class="nav-link navlink active" href="sobre-nos">Sobre Nós</a>
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

<main class="hero-sobre">
</main>

<section class="about container-fluid mt-5 gap-2 p-0" style="margin-bottom: 3rem; margin-left: 3%; margin-right: 3%;">
  <h1 class="titulo-about mb-3" style="max-width: 800px">Como nós surgimos?</h1>

  <div class="row align-items-center">
    <div class="col-lg-8 col-md-12 mb-2 pe-0">
      <div class="about-content">
        <p class="about-text m-0">
          Nosso projeto surgiu do amor pelos animais e do desejo de ajudá-los de forma concreta. Ao percebermos a
          quantidade de cães e gatos abandonados e ignorados pela sociedade, sentimos a necessidade de agir. Muitos desses animais
          não são adotados por falta de informação ou visibilidade. Assim, criamos este espaço com o objetivo de promover a
          adoção responsável, conectando pessoas dispostas a amar com animais que só precisam de uma chance.
        </p>
        <br>
        <h3 class="conclusao-about">
          Aqui, cada adoção é celebrada como uma nova história de
          <span class="destaque-amor">AMOR</span>,
          <span class="destaque-confianca">CONFIANÇA</span>
          e
          <span class="destaque-recomeco">RECOMEÇO</span>
        </h3>
      </div>
    </div>

    <div class="col-lg-4 col-md-12 p-0" style="max-width: 500px">
      <div class="card-carousel-container">
        <div class="card-deck ms-5 mb-5">
          <div class="card-item-deck">
            <div class="card-content" style="background-image: url(./images/index/cacau.webp);"></div>
          </div>
          <div class="card-item-deck">
            <div class="card-content" style="background-image: url(./images/index/zeus.webp);"></div>
          </div>
          <div class="card-item-deck">
            <div class="card-content" style="background-image: url(./images/index/kitty.jpg);"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="valores-section container-fluid">
  <div class="container-fluid">
    <h1 class="titulo-about ms-3">Nossos Valores</h1>
    
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 justify-content-center g-4 mt-4">
      <div class="col d-flex justify-content-center">
        <div class="valor-card-item">
          <div class="card-icon">
            <i class="fa-solid fa-heart"></i>
          </div>
          <h3>Amor</h3>
        </div>
      </div>
      <div class="col d-flex justify-content-center">
        <div class="valor-card-item">
          <div class="card-icon">
            <i class="fa-solid fa-users"></i> 
          </div>
          <h3>Respeito</h3>
        </div>
      </div>
      <div class="col d-flex justify-content-center">
        <div class="valor-card-item">
          <div class="card-icon">
            <i class="fa-solid fa-people-arrows"></i>
          </div>
          <h3>Transparência</h3>
        </div>
      </div>
      <div class="col d-flex justify-content-center">
        <div class="valor-card-item">
          <div class="card-icon">
            <i class="fa-solid fa-volume-high"></i>
          </div>
          <h3>Comunicação</h3>
        </div>
      </div>
      <div class="col d-flex justify-content-center">
        <div class="valor-card-item">
          <div class="card-icon">
            <i class="fa-solid fa-handshake"></i>
          </div>
          <h3>Compromisso</h3>
        </div>
      </div>
    </div>
  </div>
</section>

<h1 class="titulo-about ms-5">Nossa Equipe</h1>

<div class="team-carousel-wrapper container-fluid mt-4">
  <div class="team-carousel" id="teamCarousel" aria-label="Carrossel da equipe">
    <button class="team-prev" aria-label="Anterior">&larr;</button>
    <div class="team-track" id="teamTrack">
      <div class="team-item">
        <img class="team-avatar" src="./images/sobre/cris.jpg" alt="Cristian Lira">
        <div class="team-name">Cristian Lira</div>
      </div>
      <div class="team-item">
        <img class="team-avatar" src="./images/sobre/diogo.jpg" alt="Diogo Rodrigues">
        <div class="team-name">Diogo Rodrigues</div>
      </div>
      <div class="team-item">
        <img class="team-avatar" src="./images/sobre/marcella.jpg" alt="Marcella Rossinoli">
        <div class="team-name">Marcella Rossinoli</div>
      </div>
      <div class="team-item">
        <img class="team-avatar" src="./images/sobre/inacio.jpg" alt="Inácio Andrade">
        <div class="team-name">Inácio Andrade</div>
      </div>
      <div class="team-item">
        <img class="team-avatar" src="./images/sobre/giulia.png" alt="Giulia Baptista">
        <div class="team-name">Giulia Baptista</div>
      </div>
    </div>
    <button class="team-next" aria-label="Próximo">&rarr;</button>
  </div>
</div>

<center>
  <h1 style="color: var(--cor-verde-pastel); font-weight: bold; font-size: 3rem; margin-bottom: 3rem; text-shadow: 0 2px 5px rgba(0, 0, 0, 0.4);">Junte-se a nós nessa causa!</h1>
</center>

<!-- CORREÇÃO CRÍTICA: Offcanvas completo e funcional -->
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
            <a class="nav-link" href="#">
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
          
          <a class="nav-link" href="#">
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

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
<script src="assets/js/pages/index/card-deck.js"></script>
<script src="assets/js/pages/index/offcanvas-fix.js"></script>
<script src="assets/js/pages/sobre/slide-equipe.js"></script>
</body>
</html>
