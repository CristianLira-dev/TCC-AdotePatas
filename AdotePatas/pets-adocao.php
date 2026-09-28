<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php'; // 1. Inclui a conexão
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $base_path = '/TCC-AdotePatas/AdotePatas/';
} else {
    $base_path = '/'; // Para a Hostinger (adotepatas.com)
}
$pagina = "pets-adocao"; // Definindo a página atual para a lógica 'active' do menu

// 2. Segurança: Garante que o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    // Se não estiver logado, redireciona para a página de login
    header("Location: login");
    exit;
}

// 3. Se chegou aqui, ESTÁ LOGADO. Buscar dados do usuário.
$usuario = null;
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$primeiro_nome = '';

// Carrega dados completos do usuário (para o offcanvas)
try {
    if ($user_tipo == 'usuario') {
        $sql = "SELECT nome, email, cpf FROM usuario WHERE id_usuario = :id LIMIT 1";
    } elseif ($user_tipo == 'ong') {
        $sql = "SELECT nome, email, cnpj FROM ong WHERE id_ong = :id LIMIT 1";
    } else {
        $sql = null; // Segurança
    }

    if (!empty($sql)) {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // silencioso — manter UX
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
}

// Pega o primeiro nome (Prioriza a sessão, usa o BD como fallback)
if (isset($_SESSION['nome'])) {
    $partes = explode(' ', $_SESSION['nome']);
    $primeiro_nome = $partes[0] ?? '';
} elseif ($usuario && isset($usuario['nome'])) {
     $partes = explode(' ', $usuario['nome']);
     $primeiro_nome = $partes[0] ?? '';
}

// 4. Lógica de favoritos (original de pets-adocao.php)
$favoritos_usuario = [];
try {
    // Busca todos os IDs de pets que o usuário já favoritou
    $sql_fav = "SELECT id_pet FROM favorito WHERE id_usuario = :id_usuario";
    $stmt_fav = $conn->prepare($sql_fav);
    $stmt_fav->execute([':id_usuario' => $user_id]);

    // Converte o resultado em um array simples de IDs [1, 5, 12]
    $favoritos_usuario = $stmt_fav->fetchAll(PDO::FETCH_COLUMN, 0);
    $favoritos_usuario = array_map('intval', $favoritos_usuario);

} catch (PDOException $e) {
    // Não para a página, apenas loga o erro
    error_log("Erro ao buscar favoritos: " . $e->getMessage());
}


// 5. Lógica para buscar os pets no banco de dados (original de pets-adocao.php)
$pets = [];
$erro = '';
$paginaAtual = max(1, filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1);
$itensPorPagina = 24;
$totalPaginas = 1;
try {
    $totalPets = (int) $conn->query(
        "SELECT COUNT(*) FROM pet WHERE status_disponibilidade = 'disponivel'"
    )->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalPets / $itensPorPagina));
    $paginaAtual = min($paginaAtual, $totalPaginas);
    $offset = ($paginaAtual - 1) * $itensPorPagina;

    // Buscamos apenas pets que estão 'disponiveis'
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
            LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro = "Erro ao buscar os pets. Tente novamente mais tarde.";
    // Para debug: error_log("Erro em pets-adocao.php: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adote Patas - Pets para Adoção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="
    crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <link rel="stylesheet" href="assets/css/pages/pets/pets.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
</head>
<body>

<header>
  <nav class="navbar navbar-expand">
    <div class="container">
      <a class="navbar-brand" href="./">
        <img src="./images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
      </a>

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

        <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Abrir menu">
          <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
        </button>
      </div>
    </div>
  </nav>
</header>
<main>
        <section class="pets-section">
            <div class="container">
                <div class="row mb-4">
                    <div class="col-12">
                        <h1 class="titulo-adocao">Pets para Adoção</h1>
                    </div>
                </div>

                <?php if (!empty($pets)): ?>
                <section class="search-filter-section mb-4">
                    <form class="search-filter-container d-flex justify-content-center" role="search">
                       <div class="search-bar-wrapper custom-search-width">
    <input type="search" class="form-control search-input"
           placeholder="Pesquise pelo nome ou adicione filtros"
           aria-label="Pesquisar pet pelo nome">
    <i class="fa-solid fa-magnifying-glass search-icon"></i>
</div>

                        <button type="button" class="btn filter-btn"
                                data-bs-toggle="collapse"
                                data-bs-target="#filterOptionsCollapse"
                                aria-expanded="false"
                                aria-controls="filterOptionsCollapse"
                                aria-label="Mostrar/Esconder filtros de pesquisa">
                            <span>Filtros</span>
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                    </form>
                </section>
                <?php endif; ?>

                <div class="collapse mb-4" id="filterOptionsCollapse">
                    <div class="filter-options-container card card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-3 filter-category mb-3">
                                <h5>Temperamento</h5>
                                <p class="filter-category-description">O comportamento principal do pet</p>
                                <div class="filter-category-options">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="docil" id="temp_docil"><label class="form-check-label" for="temp_docil">Dócil</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="brincalhao" id="temp_brincalhao"><label class="form-check-label" for="temp_brincalhao">Brincalhão</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="calmo" id="temp_calmo"><label class="form-check-label" for="temp_calmo">Calmo</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="carinhoso" id="temp_carinhoso"><label class="form-check-label" for="temp_carinhoso">Carinhoso</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="independente" id="temp_independente"><label class="form-check-label" for="temp_independente">Independente</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="timido" id="temp_timido"><label class="form-check-label" for="temp_timido">Tímido</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="sociavel" id="temp_sociavel"><label class="form-check-label" for="temp_sociavel">Sociável</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="protetor" id="temp_protetor"><label class="form-check-label" for="temp_protetor">Protetor</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="curioso" id="temp_curioso"><label class="form-check-label" for="temp_curioso">Curioso</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="alerta" id="temp_alerta"><label class="form-check-label" for="temp_alerta">Alerta</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="silencioso" id="temp_silencioso"><label class="form-check-label" for="temp_silencioso">Silencioso</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="vocal" id="temp_vocal"><label class="form-check-label" for="temp_vocal">Vocal</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="medroso" id="temp_medroso"><label class="form-check-label" for="temp_medroso">Medroso</label></div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="filter-category mb-3">
                                    <h5>Nível de Energia</h5>
                                    <p class="filter-category-description">Necessidade de atividade física</p>
                                    <div class="filter-category-options">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="baixa_energia" id="energia_baixa"><label class="form-check-label" for="energia_baixa">Baixa Energia</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="media_energia" id="energia_media"><label class="form-check-label" for="energia_media">Média Energia</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="alta_energia" id="energia_alta"><label class="form-check-label" for="energia_alta">Alta Energia</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="hiperativo" id="energia_hiperativo"><label class="form-check-label" for="energia_hiperativo">Hiperativo</label></div>
                                    </div>
                                </div>
                                <div class="filter-category">
                                    <h5>Sociabilidade</h5>
                                    <p class="filter-category-description">Interação com outros</p>
                                    <div class="filter-category-options">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="com_criancas" id="soc_criancas"><label class="form-check-label" for="soc_criancas">Com Crianças</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="com_caes" id="soc_caes"><label class="form-check-label" for="soc_caes">Com Cães</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="com_gatos" id="soc_gatos"><label class="form-check-label" for="soc_gatos">Com Gatos</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="com_estranhos" id="soc_estranhos"><label class="form-check-label" for="soc_estranhos">Com Estranhos</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="pet_unico" id="soc_unico"><label class="form-check-label" for="soc_unico">Pet Único</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="com_idosos" id="soc_idosos"><label class="form-check-label" for="soc_idosos">Com Idosos</label></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="filter-category mb-3">
                                    <h5>Cuidados Especiais</h5>
                                    <p class="filter-category-description">Necessidades que exigem atenção extra</p>
                                    <div class="filter-category-options">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="medicacao" id="cuidado_medicacao"><label class="form-check-label" for="cuidado_medicacao">Medicação</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="dieta_especial" id="cuidado_dieta"><label class="form-check-label" for="cuidado_dieta">Dieta Especial</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="alergia" id="cuidado_alergia"><label class="form-check-label" for="cuidado_alergia">Alergia</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="def_fisica" id="cuidado_def_fisica"><label class="form-check-label" for="cuidado_def_fisica">Deficiência Física</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="def_visual" id="cuidado_def_visual"><label class="form-check-label" for="cuidado_def_visual">Deficiência Visual</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="def_auditiva" id="cuidado_def_auditiva"><label class="form-check-label" for="cuidado_def_auditiva">Deficiência Auditiva</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="pos_operatorio" id="cuidado_pos_op"><label class="form-check-label" for="cuidado_pos_op">Pós-operatório</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="traumatico" id="cuidado_trauma"><label class="form-check-label" for="cuidado_trauma">Traumático</label></div>
                                    </div>
                                </div>
                                <div class="filter-category">
                                    <h5>Treinamento e Hábitos</h5>
                                    <p class="filter-category-description">Nível de educação e costumes</p>
                                    <div class="filter-category-options">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="adestrado" id="habito_adestrado"><label class="form-check-label" for="habito_adestrado">Adestrado</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="educado_higiene" id="habito_higiene"><label class="form-check-label" for="habito_higiene">Educado (higiene)</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="em_treinamento" id="habito_treinamento"><label class="form-check-label" for="habito_treinamento">Em Treinamento</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="bom_viajante" id="habito_viajante"><label class="form-check-label" for="habito_viajante">Bom Viajante</label></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 filter-category mb-3">
                                <h5>Ambiente Ideal</h5>
                                <p class="filter-category-description">Tipo de lar recomendado</p>
                                <div class="filter-category-options">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="apartamento" id="ambiente_apto"><label class="form-check-label" for="ambiente_apto">Para Apartamento</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="precisa_quintal" id="ambiente_quintal"><label class="form-check-label" for="ambiente_quintal">Precisa Quintal</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="para_casa" id="ambiente_casa"><label class="form-check-label" for="ambiente_casa">Para Casa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="primeira_adocao" id="ambiente_primeira"><label class="form-check-label" for="ambiente_primeira">1ª Adoção</label></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
        <button type="button" class="btn btn-secondary" id="limparFiltrosBtn">
            <i class="fa-solid fa-rotate-left me-2"></i>Limpar Filtros
        </button>
    </div>
                    </div>


                </div>

                <?php if (!empty($erro)): ?>
                    <div class="alert alert-danger text-center">
                        <?php echo htmlspecialchars($erro); ?>
                    </div>
                <?php elseif (empty($pets)): ?>

                    <section class="no-pet-section alert flex-column d-flex justify-content align-items-center text-center" style="margin-top: -5rem;">

                       <lottie-player src="animações/gato-deitado.json" background="transparent" speed="1" style="width: 400px; height: 400px;"
                            loop autoplay>
                        </lottie-player>

                        <h4 style="margin-top: -3rem; font-weight: 700;">Nenhum Amiguinho Encontrado</h4>
                        <p style=" color: var(--cor-cinza-texto);">Parece que todos os nossos peludinhos já encontraram um lar incrível!<br>
                        Mas não desanime, novas patinhas chegam em breve...</p>
                    </section>


                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="petsGrid">
                        <?php foreach ($pets as $pet): ?>
                            <?php
                                // Verifica se este pet está favoritado
                                $is_favorito = in_array($pet['id_pet'], $favoritos_usuario);
                                // Define um caminho padrão para a foto se não houver uma específica
                                $foto_path = !empty($pet['foto']) ? $pet['foto'] : 'images/placeholder-pet.png'; // Crie uma imagem placeholder
                                $alt_text = "Foto de " . htmlspecialchars($pet['nome']);
                            ?>
                            <div class="col">
                                <a href="<?php echo $base_path; ?>pet-detalhe/<?php echo $pet['id_pet']; ?>" class="pet-card-link">
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

                    <?php if ($totalPaginas > 1): ?>
                        <nav class="mt-5" aria-label="Paginação dos pets">
                            <ul class="pagination justify-content-center flex-wrap">
                                <?php for ($numeroPagina = 1; $numeroPagina <= $totalPaginas; $numeroPagina++): ?>
                                    <li class="page-item <?php echo $numeroPagina === $paginaAtual ? 'active' : ''; ?>">
                                        <a class="page-link" href="pets/?pagina=<?php echo $numeroPagina; ?>" <?php echo $numeroPagina === $paginaAtual ? 'aria-current="page"' : ''; ?>><?php echo $numeroPagina; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <div class="text-center mt-5">
                        <div class="spinner-border d-none mb-3" role="status" id="loadingSpinner">
                            <span class="visually-hidden">Carregando...</span>
                        </div>

                        <div class="btn-container" id="loadMoreBtnContainer" style="display: none;">
                            <button class="adopt-btn" id="loadMorePetsBtn">
                                <div class="heart-background" aria-hidden="true">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                                <span id="loadMoreText">Ver mais patinhas</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

<div id="toast-notification" class="adp-toast p-0" style="display: none;">
    <div id="toast-icon" class="adp-toast-icon" style="font-size: 1.6rem"></div>
    <div class="adp-toast-content">
        <p id="toast-message" class="adp-toast-message text-center">Pet Favoritado</p>
    </div>
    <div class="adp-toast-progress-bar"></div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Declaração de variáveis dentro do DOMContentLoaded ---
        const loadMoreBtnContainer = document.getElementById('loadMoreBtnContainer');
        const loadMoreBtn = document.getElementById('loadMorePetsBtn');
        const loadMoreText = document.getElementById('loadMoreText');
        const loadMoreSpinner = document.getElementById('loadingSpinner');
        const petsGrid = document.getElementById('petsGrid');
        const filterCheckboxes = document.querySelectorAll('.form-check-input');
        const searchInput = document.querySelector('.search-input');
        const limparFiltrosBtn = document.getElementById('limparFiltrosBtn');
        
        let filtroTimeout;
        let petsAreVisible = false;

        // --- Script do botão "Ver Mais/Menos" ---
        const hiddenPets = document.querySelectorAll('#petsGrid .pet-hidden');

        // Mostra o botão APENAS se houver pets ocultos para mostrar/esconder
        if (hiddenPets && hiddenPets.length > 0 && loadMoreBtnContainer) {
            loadMoreBtnContainer.style.display = 'inline-block';
        } else if (loadMoreBtnContainer) {
            loadMoreBtnContainer.style.display = 'none';
        }

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                if(loadMoreBtnContainer) loadMoreBtnContainer.style.display = 'none';
                loadMoreBtn.disabled = true;
                if(loadMoreSpinner) loadMoreSpinner.classList.remove('d-none');

                setTimeout(() => {
                    if(loadMoreSpinner) loadMoreSpinner.classList.add('d-none');
                    petsAreVisible = !petsAreVisible;
                    hiddenPets.forEach(pet => {
                        pet.classList.toggle('d-none', !petsAreVisible);
                    });
                    if(loadMoreText) {
                        loadMoreText.innerText = petsAreVisible ? "Ver Menos Patinhas" : "Ver Mais Patinhas";
                    }
                    if(loadMoreBtnContainer) loadMoreBtnContainer.style.display = 'inline-block';
                    loadMoreBtn.disabled = false;
                }, 500);
            });
        }

        // --- Script do Favoritar ---
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

        if (petsGrid) {
            petsGrid.addEventListener('click', function(event) {
                const heartIcon = event.target.closest('.pet-like');
                if (heartIcon) {
                    event.preventDefault();
                    event.stopPropagation();
                    const petId = heartIcon.dataset.petId;
                    toggleFavorite(petId, heartIcon);
                }
            });
        }

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

        // --- Script dos Filtros AJAX ---
        // Adiciona evento a todos os checkboxes
        filterCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', aplicarFiltros);
        });

        // Adiciona funcionalidade de pesquisa em tempo real
        if (searchInput) {
            searchInput.addEventListener('input', aplicarFiltrosComSearch);
        }

        // Botão Limpar Filtros
        if (limparFiltrosBtn) {
            limparFiltrosBtn.addEventListener('click', function() {
                // Desmarca todos os checkboxes
                document.querySelectorAll('.form-check-input:checked').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Limpa a pesquisa
                if (searchInput) {
                    searchInput.value = '';
                }
                
                // Aplica os filtros (vazios)
                aplicarFiltros();
            });
        }

        async function aplicarFiltros() {
            // Limpa timeout anterior para evitar múltiplas requisições
            clearTimeout(filtroTimeout);
            
            // Mostra loading
            if (petsGrid) {
                petsGrid.innerHTML = '<div class="col-12 text-center" style="margin: 5rem auto;"><div class="spinner-border" style="color: var(--cor-vermelho); margin: 0 auto;" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
            }
            
            // Esconde o botão "Ver mais" durante o filtro
            if (loadMoreBtnContainer) {
                loadMoreBtnContainer.style.display = 'none';
            }

            // Delay para agrupar múltiplos clicks rápidos
            filtroTimeout = setTimeout(async () => {
                // Coletar todos os filtros selecionados
                const filtrosSelecionados = [];
                document.querySelectorAll('.form-check-input:checked').forEach(checkbox => {
                    filtrosSelecionados.push(checkbox.value);
                });

                const termoPesquisa = searchInput ? searchInput.value.trim() : '';

                try {
                    const formData = new FormData();
                    formData.append('filtros', JSON.stringify(filtrosSelecionados));
                    formData.append('pesquisa', termoPesquisa);

                    const response = await fetch('filtrar-pets.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        const html = await response.text();
                        if (petsGrid) {
                            petsGrid.innerHTML = html;
                            
                            // Verifica se há pets após o filtro
                            const petsEncontrados = petsGrid.querySelectorAll('.col').length > 0;
                            
                            if (!petsEncontrados) {
                                petsGrid.innerHTML = `

                                <div class="container text-center">
  <div class="row"  style='margin-left: -2rem;'>
    <div class="col">
    <lottie-player src="animações/gato-deitado.json" background="transparent" speed="1" style="width: 400px; height: 400px; margin: 0 auto;" loop autoplay></lottie-player>

    </div>
    <div class="col">
   <h4 style="margin-top: -2rem; font-weight: 700; width: 500px;margin-left: -2rem;">Nenhum Pet Encontrado</h4>
    </div>
    <div class="col">
<p style="color: var(--cor-cinza-texto); width: 500px; margin-left: -2rem;">Não encontramos pets com os filtros selecionados.<br>Tente ajustar os filtros.</p>
    </div>
  </div>
</div>
                                `;
                            }
                            
                            // Mantém o botão "Ver mais" escondido durante filtros
                            if (loadMoreBtnContainer && (filtrosSelecionados.length > 0 || termoPesquisa.length > 0)) {
                                loadMoreBtnContainer.style.display = 'none';
                            } else if (loadMoreBtnContainer) {
                                // Mostra o botão apenas quando não há filtros ativos
                                const hiddenPetsAfterFilter = document.querySelectorAll('#petsGrid .pet-hidden');
                                if (hiddenPetsAfterFilter.length > 0) {
                                    loadMoreBtnContainer.style.display = 'inline-block';
                                }
                            }
                        }
                    } else {
                        throw new Error('Erro na resposta do servidor');
                    }
                } catch (error) {
                    console.error('Erro ao aplicar filtros:', error);
                    if (petsGrid) {
                        petsGrid.innerHTML = '<div class="col-12 text-center"><p>Erro ao carregar pets. Tente novamente.</p></div>';
                    }
                }
            }, 300);
        }

        function aplicarFiltrosComSearch() {
            clearTimeout(filtroTimeout);
            filtroTimeout = setTimeout(() => {
                aplicarFiltros();
            }, 500);
        }
    });
</script>
<script src="assets/js/pages/index/offcanvas-fix.js"></script>




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
          <a class="nav-link" href="sobre-nos"> <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
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
  </div>
</div>
</body>
</html>
