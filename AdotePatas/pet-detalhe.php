<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';
include_once 'session.php';



if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $base_path = '/TCC-AdotePatas/AdotePatas/';
} else {
    $base_path = '/'; // Para a Hostinger (adotepatas.com)
}


// 2. Segurança: Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    // Se não estiver logado, redireciona para a página de login
    header("Location: ../login");
    exit;
}
// 3. Pega os dados básicos da sessão
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$usuario = null;
$erro = '';


$pagina = "pet-detalhe";

$id_pet = $_GET['id'] ?? 0;
$id_pet = (int)$id_pet;

$id_usuario_logado = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$usuario = null;
$primeiro_nome = '';

try {
    if ($user_tipo == 'usuario') {
        $sql_user = "SELECT nome, email, cpf FROM usuario WHERE id_usuario = :id LIMIT 1";
    } elseif ($user_tipo == 'ong') {
        $sql_user = "SELECT nome, email, cnpj FROM ong WHERE id_ong = :id LIMIT 1";
    } else {
        $sql_user = null;
    }

    if (!empty($sql_user)) {
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bindParam(':id', $id_usuario_logado, PDO::PARAM_INT);
        $stmt_user->execute();
        $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
}

if (isset($_SESSION['nome'])) {
    $partes = explode(' ', $_SESSION['nome']);
    $primeiro_nome = $partes[0] ?? '';
} elseif ($usuario && isset($usuario['nome'])) {
     $partes = explode(' ', $usuario['nome']);
     $primeiro_nome = $partes[0] ?? '';
}

$pet = null;
$doador = null;
$caracteristicas = [];
$outros_pets = [];
$pet_fotos = [];

if (empty($id_pet)) {
    header('Location: ' . $base_path . 'pets');
    exit;
}

try {
    // SQL Novo (com endereço estruturado)
$sql = "SELECT 
            p.*, 
            COALESCE(o.nome, u.nome) as doador_nome,
            
            -- Usa COALESCE para pegar o endereço da fonte correta (ONG ou Usuário)
            COALESCE(o.logradouro, u.logradouro) as doador_logradouro,
            COALESCE(o.numero, u.numero) as doador_numero,
            COALESCE(o.bairro, u.bairro) as doador_bairro,
            COALESCE(o.cidade, u.cidade) as doador_cidade,
            COALESCE(o.estado, u.estado) as doador_estado,
            COALESCE(o.cep, u.cep) as doador_cep
            
        FROM pet AS p
        LEFT JOIN ong AS o ON p.id_ong_fk = o.id_ong
        LEFT JOIN usuario AS u ON p.id_usuario_fk = u.id_usuario
        WHERE p.id_pet = :id_pet AND p.status_disponibilidade = 'disponivel'";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_pet' => $id_pet]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header('Location: ' . $base_path . 'pets');
        exit;
    }

    $sql_fotos = "SELECT id_foto, caminho_foto FROM pet_fotos WHERE id_pet_fk = :id_pet ORDER BY id_foto ASC";
    $stmt_fotos = $conn->prepare($sql_fotos);
    $stmt_fotos->execute([':id_pet' => $id_pet]);
    $pet_fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA GOOGLE MAPS ---
    $google_maps_url = '#'; // URL Padrão
    $endereco_completo_array = [];

    // Exibe apenas localização aproximada para preservar o endereço do responsável.
    if (!empty($pet['doador_bairro'])) $endereco_completo_array[] = $pet['doador_bairro'];
    if (!empty($pet['doador_cidade'])) $endereco_completo_array[] = $pet['doador_cidade'];
    if (!empty($pet['doador_estado'])) $endereco_completo_array[] = $pet['doador_estado'];

    if (!empty($endereco_completo_array)) {
        // Formato aproximado: "Bairro, Cidade, UF"
        $query_string = implode(', ', $endereco_completo_array);
        // Gera a URL de busca do Google Maps
        $google_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query_string);
    }
    
    // Define uma foto padrão caso não ache nenhuma
    $foto_principal = $base_path . 'images/perfil/teste.jpg';
    if (!empty($pet_fotos)) {
        $foto_principal = $base_path . htmlspecialchars($pet_fotos[0]['caminho_foto']);
    }

    $caracteristicas = json_decode($pet['caracteristicas'] ?? '[]', true);

    // *** SQL ATUALIZADO: Busca a foto principal de outros pets ***
    $sql_outros = "SELECT p.id_pet, p.nome, pf.caminho_foto as foto 
                   FROM pet p
                   LEFT JOIN (
                       SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                       FROM pet_fotos
                       GROUP BY id_pet_fk
                   ) pf_min ON p.id_pet = pf_min.id_pet_fk
                   LEFT JOIN pet_fotos pf ON pf.id_foto = pf_min.min_id_foto
                   WHERE p.id_pet != :id_pet AND p.status_disponibilidade = 'disponivel' 
                   LIMIT 4"; 
    $stmt_outros = $conn->prepare($sql_outros);
    $stmt_outros->execute([':id_pet' => $id_pet]);
    $outros_pets = $stmt_outros->fetchAll(PDO::FETCH_ASSOC);
    
    
    $is_favorito = false;
    if ($id_usuario_logado) {
        $sql_fav = "SELECT id_favorito FROM favorito WHERE id_usuario = :id_usuario AND id_pet = :id_pet LIMIT 1";
        $stmt_fav = $conn->prepare($sql_fav);
        $stmt_fav->execute([':id_usuario' => $id_usuario_logado, ':id_pet' => $id_pet]);
        if ($stmt_fav->fetch()) {
            $is_favorito = true;
        }
    }

} catch (PDOException $e) {
    error_log('Erro ao carregar detalhes do pet: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar os dados do pet. Tente novamente.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pet['nome']); ?> - Adote Patas</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/pages/detalhe-pet/detalhe-pet.css"> 
</head>
<body class="pet-detalhe-body">

    <header>
      <nav class="navbar navbar-expand">
        <div class="container">
          <a class="navbar-brand" href="<?php echo $base_path; ?>./">
            <img src="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
          </a>
    
          <div class="d-flex align-items-center gap-4">
            <div class="d-none d-xl-block">
              <ul class="navbar-nav d-flex flex-row gap-4 mb-0">
                <li class="nav-item">
                  <a class="nav-link navlink" href="<?php echo $base_path; ?>sobre-nos">Sobre Nós</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link navlink" href="ajuda.php">Ajuda</a>
                </li>
              </ul>
            </div>
    
            <a href="<?php echo $base_path; ?>perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-3 text-decoration-none" title="Ver meu perfil">
              <div class="d-flex align-items-center flex-row-reverse gap-2">
                <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
                <span class="profile-name fs-5" style="color: var(--cor-vermelho);"><?php echo htmlspecialchars($primeiro_nome); ?></span>
              </div>
            </a>
    
            <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Abrir menu">
              <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
            </button>
          </div>
        </div>
      </nav>
    </header>

    <div id="toast-notification" 
         class="adp-toast p-0" 
         style="display: none;"
         role="alert" 
         aria-live="assertive" 
         aria-atomic="true">
        <div id="toast-icon" class="adp-toast-icon"></div>
        <div class="adp-toast-content">
            <p id="toast-message" class="adp-toast-message">Mensagem</p>
        </div>
        <div class="adp-toast-progress-bar"></div>
    </div>

    <main class="container my-4" style="padding: 0 30px;">
        
        <nav aria-label="breadcrumb" class="detalhe-breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $base_path; ?>pets">Adote</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($pet['nome']); ?></li>
            </ol>
        </nav>

        <div class="detalhe-main-content">
            
            <div class="detalhe-fotos">
<div class="detalhe-foto-principal shadow-sm clickable-image" style="border-radius: 20px; cursor: pointer;">
    <img id="foto-principal-img" 
         src="<?php echo htmlspecialchars($foto_principal, ENT_QUOTES, 'UTF-8'); ?>"
         alt="Foto principal de <?php echo htmlspecialchars($pet['nome']); ?>"
         onerror="this.src='<?php echo $base_path; ?>images/perfil/teste.jpg';">
</div>
                
                <?php if (count($pet_fotos) > 1): // Só mostra thumbnails se tiver mais de 1 foto ?>
                <div class="detalhe-thumbnails">
                    <?php foreach ($pet_fotos as $index => $foto): 
                        $caminho_foto_thumb = $base_path . htmlspecialchars($foto['caminho_foto']);
                    ?>
                        <img class="shadow-sm <?php echo ($index == 0) ? 'active' : ''; ?>" 
                             src="<?php echo htmlspecialchars($caminho_foto_thumb, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="thumbnail <?php echo $index + 1; ?>"
                             onclick='mudarFotoPrincipal(<?php echo json_encode($caminho_foto_thumb, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>, this)'
                             onerror="this.style.display='none';">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="detalhe-info-card shadow-sm">
               <div class="d-flex justify-content-between align-items-start">
    <h1>
        <?php echo htmlspecialchars($pet['nome']); ?>
        <?php if ($pet['sexo'] == 'femea'): ?>
            <i class="fa-solid fa-venus pet-gender-female" style="font-size: 2rem;" title="Fêmea"></i>
        <?php else: ?>
            <i class="fa-solid fa-mars pet-gender-male" style="font-size: 2rem;" title="Macho"></i>
        <?php endif; ?>
    </h1>
    
    <div class.="detalhe-action-icons">
        <i class="pet-like <?php echo $is_favorito ? 'fa-solid fa-heart favorited' : 'fa-regular fa-heart'; ?>" 
           data-pet-id="<?php echo $pet['id_pet']; ?>" 
           aria-label="Favoritar" 
           role="button">
        </i>
        
        <div class="dropdown d-inline-block">
            <i class="fa-solid fa-share-alt pet-share" aria-label="Compartilhar" role="button" data-bs-toggle="dropdown" aria-expanded="false"></i>
            <ul class="dropdown-menu dropdown-menu-end  p-0">
                <li><a class="dropdown-item" id="share-whatsapp" href="#" target="_blank"><i class="fab fa-whatsapp fa-fw me-2"></i> WhatsApp</a></li>
                <li><a class="dropdown-item" id="share-facebook" href="#" target="_blank"><i class="fab fa-facebook fa-fw me-2"></i> Facebook</a></li>
                <li><a class="dropdown-item" id="share-twitter" href="#" target="_blank"><i class="fa-brands fa-x-twitter"></i></i> X (Twitter)</a></li>
                <li><hr class="dropdown-divider" style="margin: 2px 0;"></li>
                <li><button class="dropdown-item" id="share-copy-link" type="button"><i class="fa-solid fa-copy fa-fw me-2"></i> Copiar Link</button></li>
            </ul>
        </div>
    </div>
</div>

                <div class="tutor-responsavel">
<h6 style="color: var(--cor-cinza-texto);">Tutor: <?php echo htmlspecialchars($pet['doador_nome']); ?></h6>
</div>

                <div class="info-tags-container">
                    <span><?php echo htmlspecialchars($pet['idade']); ?> Ano(s)</span>
                    <span class="<?php echo ($pet['status_vacinacao'] == 'sim') ? 'tag-vacinado' : ''; ?>">
                        <?php echo ($pet['status_vacinacao'] == 'sim') ? 'Vacinado' : 'Não Vacinado'; ?>
                    </span>
                    <span class="<?php echo ($pet['status_castracao'] == 'sim') ? 'tag-castrado' : ''; ?>">
                        <?php echo ($pet['status_castracao'] == 'sim') ? 'Castrado' : 'Não Castrado'; ?>
                    </span>
                    <span>Porte <?php echo htmlspecialchars($pet['porte']); ?></span>
                    <span><?php echo htmlspecialchars($pet['raca']); ?></span>
                </div>

                <div class="d-flex justify-content-center text-center">
                    <a href="<?php echo $base_path; ?>formulario/<?php echo $pet['id_pet']; ?>" class="adopt-btn">
                        <div class="heart-background" aria-hidden="true">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <span>Quero Adotar!</span>
                    </a>
                </div>

                <div class="location">
                    <i class="fa-solid fa-location-dot" style="font-size: 1.2rem; color: var(--cor-vermelho);"></i>
                    <?php 
                        $cidade = $pet['doador_cidade'] ?? 'Localização';
                        $estado = $pet['doador_estado'] ?? 'não informada';
                        echo htmlspecialchars($cidade) . ' - ' . htmlspecialchars($estado);
                    ?>
                    <div class="maps">
                        <a style="color: var(--cor-vermelho); text-decoration: underline;" target="_blank" href="<?php echo htmlspecialchars($google_maps_url); ?>">
                            Ver no Mapa
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="detalhe-secao shadow-sm">
            <h2>Sobre o <?php echo htmlspecialchars($pet['nome']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($pet['comportamento'] ?? 'Nenhuma descrição fornecida.')); ?></p>
        </div>

        <?php if (!empty($caracteristicas)): ?>
        <div class="detalhe-secao shadow-sm">
            <h2>Características</h2>
            <div class="caracteristicas-container">
                <?php foreach ($caracteristicas as $carac): ?>
                    <span class="char-tag-display">
                        <?php echo htmlspecialchars($carac); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        
        <?php
        // Verifica se existem alergias
        $alergias = json_decode($pet['alergias'] ?? '[]', true);
        if (!empty($alergias) && is_array($alergias)):
        ?>
        <div class="detalhe-secao shadow-sm">
            <h2>Alergias</h2>
            <div class="caracteristicas-container">
                <?php foreach ($alergias as $alergia): ?>
                    <span class="char-tag-display">
                        <?php echo htmlspecialchars($alergia); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($pet['medicacao'])): ?>
        <div class="detalhe-secao shadow-sm">
            <h2>Medicações</h2>
            <p><?php echo nl2br(htmlspecialchars($pet['medicacao'])); ?></p>
        </div>
        <?php endif; ?>


        <?php if (!empty($outros_pets)): ?>
        <div class="detalhe-secao">
            <h2>Outros Pets que Você Pode Gostar</h2>
            <div class="outros-pets-grid">
                <?php foreach ($outros_pets as $outro_pet): ?>
                    <a href="<?php echo $base_path; ?>pet-detalhe/<?php echo $outro_pet['id_pet']; ?>" class="outro-pet-card shadow-sm">
                        <img src="<?php echo $base_path; ?><?php echo htmlspecialchars($outro_pet['foto'] ?? 'images/perfil/teste.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($outro_pet['nome']); ?>"
                             onerror="this.src='<?php echo $base_path; ?>images/perfil/teste.jpg';">
                        <h3><?php echo htmlspecialchars($outro_pet['nome']); ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
    <!-- Modal para imagem ampliada -->
<div id="imageModal" class="image-modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modalImage">
</div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo $base_path; ?>assets/js/pages/index/offcanvas-fix.js"></script> 

    <script>
        // Modal de imagem ampliada
const fotoPrincipal = document.querySelector('.detalhe-foto-principal');
const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const closeModal = document.querySelector('.close-modal');

if (fotoPrincipal && modal && modalImg) {
    // Abrir modal ao clicar na imagem principal
    fotoPrincipal.addEventListener('click', function() {
        const imgSrc = document.getElementById('foto-principal-img').src;
        modalImg.src = imgSrc;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Previne scroll
    });

    // Fechar modal com o X
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    });

    // Fechar modal clicando fora da imagem
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Fechar com ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// Atualizar função mudarFotoPrincipal para funcionar com o modal
function mudarFotoPrincipal(novaSrc, elementoClicado) {
    const imgPrincipal = document.getElementById('foto-principal-img');
    if (imgPrincipal) {
        imgPrincipal.src = novaSrc;
    }
    
    // Atualiza a classe 'active'
    const thumbnails = document.querySelectorAll('.detalhe-thumbnails img');
    thumbnails.forEach(img => img.classList.remove('active'));
    
    if (elementoClicado) {
        elementoClicado.classList.add('active');
    }
}
    </script>

        <script>

             function mudarFotoPrincipal(novaSrc, elementoClicado) {
            const imgPrincipal = document.getElementById('foto-principal-img');
            if (imgPrincipal) {
                imgPrincipal.src = novaSrc;
            }
            
            // Atualiza a classe 'active'
            const thumbnails = document.querySelectorAll('.detalhe-thumbnails img');
            thumbnails.forEach(img => img.classList.remove('active'));
            
            if (elementoClicado) {
                elementoClicado.classList.add('active');
            }
        }
    document.addEventListener('DOMContentLoaded', function() {

        
        
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

        const heartIcon = document.querySelector('.pet-like');
        if (heartIcon) {
            heartIcon.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const petId = heartIcon.dataset.petId;
                toggleFavorite(petId, heartIcon);
            });
        }

                    // Adicione isso dentro do seu:
// document.addEventListener('DOMContentLoaded', function() { ... });

    // --- Lógica de Compartilhamento ---
    
    // 1. Pega a URL atual e o nome do pet
    const currentURL = window.location.href;
    const petName = <?php echo json_encode($pet['nome']); ?>; // Pega o nome do pet com segurança
    const shareText = "Ajude o(a) " + petName + " a encontrar um lar! Veja o perfil: ";
    
    // 2. Links de compartilhamento
    const whatsappLink = document.getElementById('share-whatsapp');
    if(whatsappLink) {
        whatsappLink.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareText + currentURL);
    }

    const facebookLink = document.getElementById('share-facebook');
    if(facebookLink) {
        facebookLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(currentURL);
    }
    
    const twitterLink = document.getElementById('share-twitter');
    if(twitterLink) {
        twitterLink.href = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(currentURL) + '&text=' + encodeURIComponent(shareText);
    }

    // 3. Botão de Copiar
    const copyLinkButton = document.getElementById('share-copy-link');
    if(copyLinkButton) {
        copyLinkButton.addEventListener('click', function() {
            navigator.clipboard.writeText(currentURL).then(function() {
                // REUTILIZANDO O SEU TOAST!
                showToast('Link copiado para a área de transferência!', 'success');
            }, function(err) {
                console.error('Erro ao copiar link: ', err);
                showToast('Não foi possível copiar o link.', 'danger');
            });
        });
    }

        async function toggleFavorite(petId, iconElement) {
            try {
                const response = await fetch('<?php echo $base_path; ?>favoritar-pet.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
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
                        setTimeout(() => { window.location.href = '<?php echo $base_path; ?>login'; }, 1500);
                    } else {
                        showToast(result.message || 'Erro ao favoritar.', 'danger');
                    }
                }
            } catch (error) {
                console.error('Erro no fetch:', error);
                showToast('Erro de conexão. Tente novamente.', 'danger');
            }
        }
    });
    </script>
    
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
  <div class="offcanvas-header border-bottom">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  
  <div class="offcanvas-body p-0">
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
          <a class="nav-link" href="<?php echo $base_path; ?>sobre-nos">
            <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
          </a>
          <a class="nav-link" href="#">
            <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
          </a>
          <hr class="my-2">
        </div>
        
        <a class="nav-link <?php echo ($pagina == 'perfil') ? 'active' : ''; ?>" 
           href="<?php echo $base_path; ?>perfil?page=perfil" 
           <?php echo ($pagina == 'perfil') ? 'aria-current="page"' : ''; ?>>
           <i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil
        </a>
        
        <a class="nav-link <?php echo ($pagina == 'meus-pets') ? 'active' : ''; ?>" 
           href="<?php echo $base_path; ?>perfil?page=meus-pets"
           <?php echo ($pagina == 'meus-pets') ? 'aria-current="page"' : ''; ?>>
           <i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets
        </a>

        <a class="nav-link <?php echo ($pagina == 'pets-curtidos') ? 'active' : ''; ?>" 
           href="<?php echo $base_path; ?>perfil?page=pets-curtidos"
           <?php echo ($pagina == 'pets-curtidos') ? 'aria-current="page"' : ''; ?>>
           <i class="fa-regular fa-heart fa-fw me-2"></i> Pets Curtidos
        </a>

        <a class="nav-link" href="<?php echo $base_path; ?>chat.php">
            <i class="fa-regular fa-comments fa-fw me-3"></i> Chats
        </a>

        <hr class="my-2">
        
        <a class="nav-link logout-link-sidebar" href="<?php echo $base_path; ?>sair.php">
          <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Sair
        </a>
      </nav>
    </aside>
  </div>
</div>
</body>
</html>
