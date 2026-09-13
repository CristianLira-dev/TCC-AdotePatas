<?php
session_start();

include_once 'conexao.php'; // 1. Inclui a conexão com o banco

// LÓGICA DO LOADER
$show_loader = false; // Por padrão, não mostramos o loader

// Verifica se a flag 'loader_shown' AINDA NÃO FOI SETADA nesta sessão
if (!isset($_SESSION['loader_shown'])) {
    $show_loader = true; // Se não foi, ativamos para mostrar
    $_SESSION['loader_shown'] = true; // E imediatamente setamos a flag
}

// Verifica se o usuário está logado ANTES de tentar acessar as variáveis de sessão
$logado = isset($_SESSION['user_id']);
$usuario = null;
$erro = '';
$user_id = null;
$user_tipo = null;

// SÓ busca dados do usuário se estiver logado
if ($logado) {
    // 3. Pega os dados básicos da sessão (agora com segurança)
    $user_id = $_SESSION['user_id'];
    $user_tipo = $_SESSION['user_tipo'];

    // 4. Busca os dados completos do usuário no banco
    try {
        if ($user_tipo == 'usuario') {
            $sql = "SELECT nome, email, cpf FROM usuario WHERE id_usuario = :id LIMIT 1";
        } elseif ($user_tipo == 'ong') {
            $sql = "SELECT nome, email, cnpj FROM ong WHERE id_ong = :id LIMIT 1";
        } else {
            $erro = "Tipo de usuário inválido.";
        }

        if (empty($erro)) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $erro = "Usuário não encontrado no banco de dados.";
            }
        }
    } catch (PDOException $e) {
        $erro = "Ocorreu um erro ao buscar seus dados. Tente novamente.";
        // Para debug: error_log("Erro no perfil.php: " . $e->getMessage());
    }
}

// Define o link do botão principal com base no status de login
if ($logado) {
  $acesso = "pets";
} else {
  $acesso = "login";// Se não estiver logado, o botão "Quero Adotar" leva para a tela de login
}

// Pega o primeiro nome do usuário se estiver logado
$primeiro_nome = '';
if ($logado && isset($_SESSION['nome'])) {
    $partes_nome = explode(' ', $_SESSION['nome']);
    $primeiro_nome = $partes_nome[0];
}

$pagina = "";

// BUSCAR PETS DO BANCO DE DADOS (similar ao pets.php)
$pets = [];
$favoritos_usuario = [];

try {
    // Buscar pets disponíveis
    $sql = "SELECT 
                p.id_pet, p.nome, p.sexo,
                pf.caminho_foto AS foto
            FROM 
                pet AS p
            LEFT JOIN (
                -- Subquery para achar a primeira foto
                SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                FROM pet_fotos
                GROUP BY id_pet_fk
            ) pf_min ON p.id_pet = pf_min.id_pet_fk
            LEFT JOIN 
                pet_fotos AS pf ON pf.id_foto = pf_min.min_id_foto
            WHERE 
                p.status_disponibilidade = 'disponivel'
            ORDER BY p.id_pet DESC
            LIMIT 3"; // Limitando a 3 pets no index

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro = "Erro ao buscar os pets. Tente novamente mais tarde.";
    error_log("Erro em index.php ao buscar pets: " . $e->getMessage());
}

// Buscar favoritos se estiver logado
if ($logado && $user_tipo == 'usuario') {
    try {
        $sql_fav = "SELECT id_pet FROM favorito WHERE id_usuario = :id_usuario";
        $stmt_fav = $conn->prepare($sql_fav);
        $stmt_fav->execute([':id_usuario' => $user_id]);
        $favoritos_usuario = $stmt_fav->fetchAll(PDO::FETCH_COLUMN, 0);
        $favoritos_usuario = array_map('intval', $favoritos_usuario);
    } catch (PDOException $e) {
        error_log("Erro ao buscar favoritos no index: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Adote Patas</title>
  <link rel="stylesheet" href="assets/css/pages/index/index.css">
  <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>

<?php if ($show_loader): ?>
  <div class="loading-screen" id="loading">
    <lottie-player src="animações/loading.json" background="transparent" speed="1" style="width: 250px; height: 250px;"
    loop autoplay>
  </lottie-player>
</div>
<?php endif; ?>

  <header>
    <nav class="navbar navbar-expand">
      <div class="container">
        <a class="navbar-brand" href="#">
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
                <li class="nav-item">
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

 <main class="hero">
    <div class="container hero-conteudo">
      <div class="row align-items-center">

        <div class="col-lg-6 order-1"> <div class="hero-text-content">
            <h1 class="adote-patas">Adote Patas</h1>
            <h2 class="adote-vidas">Adote Vidas</h2>
            <p>
              Adotar é um gesto de amor e responsabilidade, capaz de transformar vidas e criar laços de confiança e
              felicidade. Mais do que uma escolha, a adoção é amor e compromisso.
            </p>
          </div>

          <img src="images/index/cabeca-cachorro.png" class="img-fluid d-none" id="cachorro" alt="cachorro fofo deitado olhando para frente">

<div class="btn-container"> <a href="<?php echo $acesso ?>" class="adopt-btn" id="adoptBtn" style="text-decoration: none">
    <div class="heart-background" aria-hidden="true">
      <i class="bi bi-heart-fill"></i>
    </div>
    <span>Quero adotar</span>
  </a>
  <div class="paw-prints" id="pawPrints"></div>
</div>

        </div> </div>
    </div>
  </main>

  <section class="image-background">

    <section class="cards-section">
      <div class="container text-center">
        <div class="row justify-content-center">

          <div class="col-4">
            <a href="pets" style="text-decoration: none;">
              <div class="card-item">
                <div class="card-icon">
                  <i class="fa-solid fa-paw"></i>
                </div>
                <h3>Adote</h3>
              </div>
            </a>
          </div>

          <div class="col-4">
            <a href="ongs-parceiras.php"  style="text-decoration: none;">
              <div class="card-item">
                <div class="card-icon">
                  <img src="./images/index/icone-ong.png" alt="Ícone de ONGs">
                </div>
                <h3>ONGs</h3>
              </div>
            </a>
          </div>

          <div class="col-4">
            <div class="card-item">
              <a href="como-adotar.php"  style="text-decoration: none;">
                <div class="card-icon">
                  <i class="fa-solid fa-heart"></i>
                </div>
                <h4>Como Adotar</h4>
              </div>
            </a>
          </div>

        </div>
      </div>
    </section>

    <section class="pets-section">
      <div class="container">
        <div class="row mb-4">
          <div class="col-12">
            <h1 class="titulo-adocao">Animais para Adoção</h1>
          </div>
        </div>
        
        <?php if (!empty($pets)): ?>
          <div class="row justify-content-between">
            <?php foreach ($pets as $pet): ?>
              <?php
                // Verifica se este pet está favoritado
                $is_favorito = in_array($pet['id_pet'], $favoritos_usuario);
                // Define um caminho padrão para a foto se não houver uma específica
                $foto_path = !empty($pet['foto']) ? $pet['foto'] : 'images/placeholder-pet.png';
                $alt_text = "Foto de " . htmlspecialchars($pet['nome']);
              ?>
              <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <a href="pet-detalhe/<?php echo $pet['id_pet']; ?>" class="pet-card-link" style="text-decoration: none; color: inherit;">
                  <div class="pet-card">
                    <div class="pet-card-img">
                      <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="<?php echo $alt_text; ?>">
                    </div>
                    <div class="pet-card-body">
                      <h2 class="pet-name"><?php echo htmlspecialchars($pet['nome']); ?></h2>
                      <?php if (!empty($pet['sexo'])): ?>
                        <?php if ($pet['sexo'] == 'femea'): ?>
                          <i class="fa-solid fa-venus pet-gender-female" aria-label="Fêmea" title="Fêmea"></i>
                        <?php else: // 'macho' ?>
                          <i class="fa-solid fa-mars pet-gender-male" aria-label="Macho" title="Macho"></i>
                        <?php endif; ?>
                      <?php endif; ?>
                      <i class="pet-like <?php echo $is_favorito ? 'fa-solid fa-heart favorited' : 'fa-regular fa-heart'; ?>"
                         data-pet-id="<?php echo $pet['id_pet']; ?>"
                         aria-label="Favoritar"
                         role="button">
                      </i>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center">
            <p>Nenhum pet disponível para adoção no momento.</p>
          </div>
        <?php endif; ?>

        <center>
<div class="btn-container"> <a href="<?php echo $acesso ?>" class="adopt-btn" id="adoptBtn" style="text-decoration: none">
    <div class="heart-background" aria-hidden="true">
      <i class="bi bi-heart-fill"></i>
    </div>
    <span>Quero adotar</span>
  </a>
  <div class="paw-prints" id="pawPrints"></div>
</div>
        </center>

      </div>
    </section>

    <div class="cachorro">
      <lottie-player class="dog-walking" src="animações/animacao.json" background="transparent" speed="1"
        style="width: 200px; height: 200px;" loop autoplay>
      </lottie-player>
    </div>

  </section>

    <section class="image-background-2">

      <section class="about container mt-5 gap-3" style="margin-bottom: 3rem;">
        <h1 class="titulo-about" style="margin-bottom: 1rem">Como nós surgimos?</h1>
        
        <div class="row align-items-center" style="margin-bottom: 10rem;">
          <div class="col-lg-8 col-md-12 mb-2">
            <div class="about-content">
              <p class="about-text">
                Nosso projeto surgiu do amor pelos animais e do desejo de ajudá-los de forma concreta. Ao percebermos a
                quantidade de
                cães e gatos abandonados e ignorados pela sociedade, sentimos a necessidade de agir. Muitos desses animais
                não são
                adotados por falta de informação ou visibilidade. Assim, criamos este espaço com o objetivo de promover a
                adoção
                responsável, conectando pessoas dispostas a amar com animais que só precisam de uma chance.
              </p>
              
              <h3 class="conclusao-about">
                Aqui, cada adoção é celebrada como uma nova história de
                <span class="destaque-amor">AMOR</span>,
                <span class="destaque-confianca">CONFIANÇA</span>
                e
                <span class="destaque-recomeco">RECOMEÇO</span>
              </h3>
            </div>
          </div>
          
          <div class="col-lg-4 col-md-12">
            
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
    </section>
      
  <svg width="0" height="0" style="position: absolute;">
    <defs>
      <clipPath id="footerConcavity" clipPathUnits="objectBoundingBox">
        <path d="M0,0 Q 0.5,0.35 1,0 L1,1 L0,1 Z" />
      </clipPath>
      <clipPath id="footerConcavityShallow" clipPathUnits="objectBoundingBox">
        <path d="M0,0 Q 0.5,0.15 1,0 L1,1 L0,1 Z" />
      </clipPath>
      <clipPath id="footerConcavityDeep" clipPathUnits="objectBoundingBox">
        <path d="M0,0 Q 0.5,0.25 1,0 L1,1 L0,1 Z" />
      </clipPath>
      <clipPath id="footerConcavityExtraDeep" clipPathUnits="objectBoundingBox">
        <path d="M0,0 Q 0.5,0.45 1,0 L1,1 L0,1 Z" />
      </clipPath>
    </defs>
  </svg>

  <footer class="site-footer">
    <div class="container footer-container">
      <div class="row">

        <div class="col-lg-4 col-md-12 footer-column">
          <h3>Links</h3>
          <ul>
            <li><a href="#">Home</a></li>
            <li><a href="pets">Adote</a></li>
            <li><a href="sobre-nos">Sobre nós</a></li>
            <li><a href="ongs">ONGs</a></li>
            <li><a href="politicas-privacidade.php">Política de privacidade</a></li>
            <li><a href="termos-de-uso.php">Termos de uso</a></li>
          </ul>
        </div>

        <div class="col-lg-4 col-md-12 footer-column">
          <h3>Contato</h3>
          <p><a href="mailto:adotepatas@gmail.com">adotepatas@gmail.com</a></p>
          <p><a href="tel:+5513992007065">(13) 99200-7065</a></p>
        </div>

        <div class="col-lg-4 col-md-12 footer-column">
          <h3>Redes Sociais</h3>
          <p><a href="https://wa.me/5513992007065" target="_blank"><i class="fab fa-whatsapp"></i> 13992007065</a></p>
          <p><a href="https://instagram.com/adotepatas.tcc" target="_blank"><i class="fab fa-instagram"></i>
              @adotepatas.tcc</a></p>
        </div>

      </div>
    </div>  
  </footer>
  
  <section id="direitos" class="direitos container p-3 d-flex align-items-center">
    <img src="images/index/logo-nome.svg" alt="logo da marca adote patas" class="img-fluid" width="150">
      <div class="text-center" style="margin-left: 7rem;">
        <h1 class="titulo-direitos" style="color: var(--cor-vermelho-claro); font-weight: bold;">Conectando pets a lares cheios de amor.</h1>
        <p style="color: var(--cor-cinza-texto);">Adote Patas © <?php echo date("Y") ?>. Todos os direitos reservados </p>
      </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
    integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
    crossorigin="anonymous"></script>
<script src="https://unpkg.com/scrollreveal"></script>
    <script src="assets/js/pages/index/patinhas.js"></script>
  <script src="assets/js/pages/index/card-deck.js"></script>
  <script src="assets/js/pages/index/loading.js"></script>
  <script src="assets/js/pages/index/offcanvas-fix.js"></script>
  <script src="assets/js/pages/index/scroll-reveal-init.js"></script>

  <!-- TOAST NOTIFICATION -->
  <div id="toast-notification" class="adp-toast p-0" style="display: none;">
    <div id="toast-icon" class="adp-toast-icon" style="font-size: 1.6rem"></div>
    <div class="adp-toast-content">
        <p id="toast-message" class="adp-toast-message text-center">Pet Favoritado</p>
    </div>
    <div class="adp-toast-progress-bar"></div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Função para mostrar toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-notification');
            const toastIcon = document.getElementById('toast-icon');
            const toastMessage = document.getElementById('toast-message');
            if (!toast || !toastIcon || !toastMessage) return;

            toastMessage.textContent = message;
            toast.classList.remove('adp-toast--success', 'adp-toast--danger', 'adp-toast--warning', 'show', 'hide');
            toastIcon.className = 'adp-toast-icon';
            toast.classList.add('adp-toast--' + type);

            if (type === 'success') toastIcon.classList.add('fas', 'fa-check');
            else if (type === 'danger') toastIcon.classList.add('fas', 'fa-times');
            else if (type === 'warning') toastIcon.classList.add('fas', 'fa-exclamation-triangle');

            toast.style.display = 'flex';
            const progressBar = toast.querySelector('.adp-toast-progress-bar');
            if (progressBar) {
                progressBar.style.animation = 'none';
                void progressBar.offsetWidth;
                progressBar.style.animation = 'shrink 3s linear forwards';
            }

            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    toast.style.display = 'none';
                    toast.classList.remove('hide', 'adp-toast--' + type);
                    toastIcon.className = 'adp-toast-icon';
                    if (progressBar) progressBar.style.animation = 'none';
                }, 500);
            }, 3000);
        }

        // Função para favoritar/desfavoritar pet
        async function toggleFavorite(petId, iconElement) {
            try {
                const response = await fetch('favoritar-pet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ id_pet: petId })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    if (result.action === 'favorited') {
                        iconElement.classList.remove('fa-regular');
                        iconElement.classList.add('fa-solid', 'favorited');
                        showToast(result.message, 'success');
                    } else if (result.action === 'unfavorited') {
                        iconElement.classList.remove('fa-solid', 'favorited');
                        iconElement.classList.add('fa-regular');
                        showToast(result.message, 'warning');
                    }
                } else {
                    if (response.status === 403) {
                        showToast(result.message, 'danger');
                        setTimeout(() => { window.location.href = 'login'; }, 1500);
                    } else {
                        showToast(result.message || 'Erro ao favoritar.', 'danger');
                    }
                }
            } catch (error) {
                console.error('Erro no fetch:', error);
                showToast('Erro de conexão. Tente novamente.', 'danger');
            }
        }

        // Adicionar event listener para os corações
        document.querySelectorAll('.pet-like').forEach(heartIcon => {
            heartIcon.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const petId = this.dataset.petId;
                toggleFavorite(petId, this);
            });
        });
    });
  </script>

  <script>
    // Seleciona o elemento que você quer modificar
    const elemento = document.querySelector('#cachorro'); // troque pelo seletor desejado
    const direitos = document.querySelector('#direitos');

    // Função que verifica a largura da tela
    function verificarLarguraTela() {
      if (window.innerWidth < 992) {
        elemento.classList.remove('d-none'); // remove o d-none em telas menores que 992px
      } else {
        elemento.classList.add('d-none'); // adiciona novamente quando for maior (opcional)
      }

      if (window.innerWidth < 500) {
        direitos.classList.add('d-none'); // adiciona o d-none em telas menores que 500px
      }
    }

    // Executa ao carregar a página
    verificarLarguraTela();

    // Executa toda vez que a janela for redimensionada
    window.addEventListener('resize', verificarLarguraTela);
  </script>

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
              <?php echo htmlspecialchars(ucfirst($user_tipo)); ?>
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

    <div vw class="enabled">
  <div vw-access-button class="active"></div>
  <div vw-plugin-wrapper>
    <div class="vw-plugin-top-wrapper"></div>
  </div>
</div>

    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
<script>
  new window.VLibras.Widget('https://vlibras.gov.br/app');
</script>
</body>

</html>