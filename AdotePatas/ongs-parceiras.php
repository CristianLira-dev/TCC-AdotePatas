<?php
require_once __DIR__ . '/app/security.php';
appStartSession();

include_once 'conexao.php';

$logado = isset($_SESSION['user_id']);
$usuario = null;
$user_id = null;
$user_tipo = null;
$primeiro_nome = '';
$pagina = "ongs-parceiras";

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

// Dados das ONGs parceiras
$parceiros = [
    [
        "id" => 1,
        "nome" => "Associação de Amparo aos Animais de Praia Grande",
        "sigla" => "AAAPG",
        "dataParceria" => "17/11/2025",
        "redeSocial" => [
            "instagram" => "https://www.instagram.com/aaapgoficial/",
            "facebook" => "#"
        ],
        "fotoPerfil" => "./images/ongs/logo-aaapg.webp",
        "descricao" => "Fundada em 1986, a AAAPG é uma ONG sem fins lucrativos dedicada a oferecer refúgio seguro a animais vítimas de abandono e maus-tratos, promovendo cuidados físicos e emocionais.",
        "email" => "atendimento.aaapg@gmail.com",
        "local" => "https://www.google.com/maps/place/Associa%C3%A7%C3%A3o+de+Amparo+aos+Animais+de+Praia+Grande/@-24.0362163,-46.5256932,17z/data=!3m1!4b1!4m6!3m5!1s0x94ce21d10f36845b:0x39cbef7c1446cd63!8m2!3d-24.0362212!4d-46.5231183!16s%2Fg%2F11c30x_jtv?entry=ttu&g_ep=EgoyMDI1MTExNi4wIKXMDSoASAFQAw%3D%3D"
    ],
    [
        "id" => 2,
        "nome" => "Projeto Benigna",
        "sigla" => "Benigna",
        "dataParceria" => "10/8/2025",
        "redeSocial" => [
            "instagram" => "https://www.instagram.com/ebenezer.tcc/",
            "facebook" => "#"
        ],
        "fotoPerfil" => "./images/ongs/benigna.webp",
        "descricao" => "O projeto Benigna é um sistema intermediador de doações que conecta de forma digital e eficiente doadores a instituições beneficentes que atuam em causas alinhadas aos Objetivos de Desenvolvimento Sustentável (ODS) da ONU.",
        "email" => "ebenezer.tcc@gmail.com",
        "site" => "benigna.org"
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONGs Parceiras - Adote Patas</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/pages/ongs/ongs.css">
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

          <a href="perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-3 text-decoration-none" title="Ver meu perfil">
            <div class="d-flex align-items-center flex-row-reverse gap-2">
              <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
              <span class="profile-name fs-5" style="color: var(--cor-vermelho);"><?php echo htmlspecialchars($primeiro_nome); ?></span>
            </div>
          </a>

          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>

      <?php else: ?>
        <!-- Navbar para usuário NÃO LOGADO -->
        <div class="d-flex align-items-center gap-4">
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

          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>
      <?php endif; ?>
    </div>
  </nav>
</header>

<!-- Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
  <div class="offcanvas-header border-bottom">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  
  <div class="offcanvas-body p-0">
    <?php if ($logado): ?>
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

<main class="ongs-section" id="ongs-parceiras">
  <div class="container">
    
    <div class="row">
      <div class="col-12 text-center mb-5">
        <h1 class="titulo-pagina">Nossas ONGs Parceiras</h1>
        <p class="subtitulo-pagina">Conheça as organizações que tornam possível a adoção de pets</p>
      </div>
    </div>

    <div class="row" id="ongs-container">
      <?php foreach ($parceiros as $ong): ?>
        <div class="col-12 mb-4">
          <article class="card ong-card-horizontal w-100">
            <div class="row g-0 h-100">
              <!-- Imagem -->
              <div class="col-md-3">
                <div class="card-img-container h-100">
                  <img src="<?php echo htmlspecialchars($ong['fotoPerfil']); ?>" 
                       alt="Logo da <?php echo htmlspecialchars($ong['nome']); ?>" 
                       class="ong-img-horizontal"
                       loading="lazy">
                </div>
              </div>
              
              <!-- Conteúdo -->
              <div class="col-md-9">
                <div class="card-body-horizontal d-flex flex-column h-100 p-4">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="ong-title-horizontal"><?php echo htmlspecialchars($ong['nome']); ?></h2>
                    <span class="badge bg-partner" title="Data de Parceria">
                      Desde <?php echo explode('/', $ong['dataParceria'])[2]; ?>
                    </span>
                  </div>
                  
                  <p class="ong-desc-horizontal flex-grow-1">
                    <?php echo htmlspecialchars($ong['descricao']); ?>
                  </p>
                  
                  <div class="ong-footer-horizontal mt-auto pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                      <!-- Lado ESQUERDO - Localização ou Site -->
                      <div class="location-section">
                        <?php if (!empty($ong['local'])): ?>
                          <a href="<?php echo htmlspecialchars($ong['local']); ?>" 
                             target="_blank" 
                             rel="noopener noreferrer" 
                             class="location-btn" 
                             aria-label="Ver localização no mapa">
                            <i class="bi bi-geo-alt-fill"></i> Localização
                          </a>
                        <?php elseif (!empty($ong['site'])): ?>
                          <a href="<?php echo htmlspecialchars($ong['site']); ?>" 
                             target="_blank" 
                             rel="noopener noreferrer" 
                             class="location-btn" 
                             aria-label="Visitar site">
                            <i class="bi bi-globe"></i> <?php echo htmlspecialchars($ong['site']); ?>
                          </a>
                        <?php endif; ?>
                      </div>
                      
                      <!-- Lado DIREITO - Email e Instagram -->
                      <div class="contact-section d-flex gap-2">
                        <a href="mailto:<?php echo htmlspecialchars($ong['email']); ?>" 
                           class="email-btn btn-contact">
                          <i class="bi bi-envelope-fill"></i> Email
                        </a>
                        
                        <?php if (!empty($ong['redeSocial']['instagram'])): ?>
                          <a href="<?php echo htmlspecialchars($ong['redeSocial']['instagram']); ?>" 
                             target="_blank" 
                             rel="noopener noreferrer" 
                             class="social-btn social-icon insta" 
                             aria-label="Instagram">
                            <i class="bi bi-instagram"></i> Instagram
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>
