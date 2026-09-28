<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';
include_once 'session.php';

// O $base_path precisa ser definido ANTES de ser usado no header()
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $base_path = '/TCC-AdotePatas/AdotePatas/';
} else {
    $base_path = '/'; 
}

// 1. Segurança: Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    header("Location: " . $base_path . "login?redirect=formulario&id=" . ($_GET['id'] ?? ''));
    exit;
}

// 2. Pega os dados básicos da sessão
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$primeiro_nome = '';
$usuario = null;
$pagina = "formulario-adocao";

// Carrega dados completos do usuário para o offcanvas
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
        
        if ($usuario && isset($usuario['nome'])) {
            $partes = explode(' ', $usuario['nome']);
            $primeiro_nome = $partes[0] ?? '';
        }
    }
} catch (PDOException $e) {
    // silencioso — manter UX
}

// 3. Pega o ID do Pet pela URL
$id_pet = $_GET['id'] ?? 0;
$id_pet = filter_var($id_pet, FILTER_SANITIZE_NUMBER_INT);

$pet = null;
$foto_principal = $base_path . 'images/perfil/teste.jpg';

if (empty($id_pet)) {
    header('Location: ' . $base_path . 'pets');
    exit;
}

// 4. Busca os dados do Pet para mostrar no formulário
try {
    $sql = "SELECT p.id_pet, p.nome, pf.caminho_foto 
            FROM pet p
            LEFT JOIN (
                SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                FROM pet_fotos
                GROUP BY id_pet_fk
            ) pf_min ON p.id_pet = pf_min.id_pet_fk
            LEFT JOIN pet_fotos pf ON pf.id_foto = pf_min.min_id_foto
            WHERE p.id_pet = :id_pet AND p.status_disponibilidade = 'disponivel'";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_pet' => $id_pet]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header('Location: ' . $base_path . 'pets');
        exit;
    }

    if (!empty($pet['caminho_foto'])) {
        $foto_principal = $base_path . htmlspecialchars($pet['caminho_foto']);
    }

} catch (PDOException $e) {
    error_log("Erro ao buscar pet para formulário: " . $e->getMessage());
    echo "Erro ao carregar dados do pet. Tente novamente.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adote <?php echo htmlspecialchars($pet['nome']); ?> - Adote Patas</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/pages/formulario-adocao/fomulario-adocao.css"> 
</head>
<body class="form-body">

    <header class="form-header shadow-sm">
      <nav class="navbar">
        <div class="container d-flex justify-content-between align-items-center">
          <a class="navbar-brand" href="<?php echo $base_path; ?>./">
            <img src="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
          </a>

          <!-- Área do usuário logado com botão do menu -->
          <div class="d-flex align-items-center gap-3">
            <!-- Links "Sobre Nós" e "Ajuda" (visíveis em telas grandes) -->
            <div class="d-none d-xl-block">
              <ul class="navbar-nav d-flex flex-row gap-4 mb-0">
                <li class="nav-item">
                  <a class="nav-link navlink" href="<?php echo $base_path; ?>sobre-nos">Sobre Nós</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link navlink" href="<?php echo $base_path; ?>ajuda.php">Ajuda</a>
                </li>
              </ul>
            </div>

            <!-- Nome e ícone do usuário -->
            <a href="<?php echo $base_path; ?>perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-2 text-decoration-none" title="Ver meu perfil">
              <div class="d-flex align-items-center flex-row-reverse gap-2">
                <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
                <span class="profile-name d-none d-sm-block"><?php echo htmlspecialchars($primeiro_nome); ?></span>
              </div>
            </a>

            <!-- Botão do menu (SEMPRE VISÍVEL) -->
            <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
              <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
            </button>
          </div>
        </div>
      </nav>
    </header>

    <!-- Offcanvas -->
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
            <!-- Links que aparecem apenas no offcanvas em telas pequenas -->
            <div class="d-xl-none">
              <a class="nav-link" href="<?php echo $base_path; ?>sobre-nos">
                <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
              </a>
              <a class="nav-link" href="<?php echo $base_path; ?>ajuda.php">
                <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
              </a>
              <hr class="my-2">
            </div>
            
            <a class="nav-link" href="<?php echo $base_path; ?>perfil?page=perfil">
              <i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil
            </a>
            
            <a class="nav-link" href="<?php echo $base_path; ?>perfil?page=meus-pets">
              <i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets
            </a>

            <a class="nav-link" href="<?php echo $base_path; ?>perfil?page=pets-curtidos">
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

    <!-- Modal de Termo -->
    <div class="modal fade" id="termoModal" tabindex="-1" aria-labelledby="termoModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content custom-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title w-100 text-center font-kawaii text-vermelho fs-2" id="termoModalLabel">Termo de Interesse</h5>
                </div>
                <div class="modal-body px-4 px-md-5">
                    <div class="welcome-icon text-center mb-3">
                    </div>
                    <p class="text-center lead mb-4">Olá! É um prazer ter você aqui ❤️</p>
                    <div class="termo-text-box">
                        <p>Ao preencher este formulário, você está dando um passo importante para transformar a vida de um cão ou gato. Queremos conhecer um pouquinho sobre você, seu lar e sua rotina.</p>
                        <p><strong>Importante:</strong> O AdotePatas é a ponte. A ONG/Protetor responsável fará a entrevista final</p>
                    </div>
                    
                    <div class="form-check-card mt-4">
                        <input class="form-check-input-custom" type="checkbox" id="termo-ciencia">
                        <label class="form-check-label-custom" for="termo-ciencia">
                            <div class="check-icon"><i class="bi bi-check-lg"></i></div>
                            <div class="check-text">
                                <strong>Declaro ter mais de 18 anos</strong> e autorizo o compartilhamento dos meus dados com os protetores parceiros. Entendo que este formulário <strong>não garante a reserva</strong> imediata do animal.
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="adopt-btn w-75" id="btn-confirmar-modal" disabled>
                        <div class="heart-background" aria-hidden="true">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <span>Confirmar e Continuar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="container my-5" id="form-principal" style="display: none;">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                
                <div class="pet-hero-card fade-in-up">
                    <div class="pet-image-wrapper">
                        <img src="<?php echo htmlspecialchars($foto_principal, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de <?php echo htmlspecialchars($pet['nome']); ?>" class="pet-hero-img" onerror="this.src='<?php echo $base_path; ?>images/perfil/teste.jpg';">
                    </div>
                    <div class="pet-hero-content">
                        <span class="badge-adote">Processo de Adoção</span>
                        <h1 class="font-kawaii text-vermelho mt-2">Você vai amar o(a) <?php echo htmlspecialchars($pet['nome']); ?>!</h1>
                        <p class="text-muted mb-0">Preencha os dados abaixo com carinho para que o protetor conheça seu perfil.</p>
                    </div>
                </div>

                <form action="<?php echo $base_path; ?>processa-adocao.php" method="POST" id="adoption-form" class="mt-4 fade-in-up" style="animation-delay: 0.2s;">
                    <input type="hidden" name="id_pet" value="<?php echo $id_pet; ?>">

                    <div class="form-card shadow-sm">
                        <h3 class="form-section-title font-kawaii"><i class="bi bi-house-heart me-2"></i>Sobre o Lar</h3>
                        
                        <div class="question-group">
                            <label class="question-label">Há crianças em sua casa?</label>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="tem_criancas" id="criancas_sim" value="sim" required>
                                    <label class="btn btn-outline-custom w-100" for="criancas_sim">Sim</label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="tem_criancas" id="criancas_nao" value="nao" required>
                                    <label class="btn btn-outline-custom w-100" for="criancas_nao">Não</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <label class="question-label">Todos em casa apoiam a adoção?</label>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="todos_apoiam" id="apoiam_sim" value="sim" required>
                                    <label class="btn btn-outline-custom w-100" for="apoiam_sim">Sim, todos!</label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="todos_apoiam" id="apoiam_nao" value="nao" required>
                                    <label class="btn btn-outline-custom w-100" for="apoiam_nao">Ainda não</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <label class="question-label">Onde você mora?</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="tipo_moradia" id="moradia_casa_g" value="Casa grande" required>
                                    <label class="card-radio-option" for="moradia_casa_g">
                                        <i class="bi bi-house-door-fill icon-option"></i>
                                        <span>Casa Grande</span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="tipo_moradia" id="moradia_casa_p" value="Casa pequena" required>
                                    <label class="card-radio-option" for="moradia_casa_p">
                                        <i class="bi bi-house-door icon-option"></i>
                                        <span>Casa Pequena</span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="tipo_moradia" id="moradia_ap_s" value="Apartamento seguro" required>
                                    <label class="card-radio-option" for="moradia_ap_s">
                                        <i class="bi bi-building-check icon-option"></i>
                                        <span>Apartamento com Telas</span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="tipo_moradia" id="moradia_ap_ns" value="Apartamento s/ proteção" required>
                                    <label class="card-radio-option" for="moradia_ap_ns">
                                        <i class="bi bi-building-exclamation icon-option"></i>
                                        <span>Apartamento s/ Telas</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card shadow-sm mt-4">
                        <h3 class="form-section-title font-kawaii"><i class="bi bi-person-hearts me-2"></i>Sobre a Posse</h3>

                        <div class="question-group">
                            <label class="question-label">O Pet será para você?</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="pet_sera_presente" id="presente_nao" value="nao" required>
                                    <label class="card-radio-option" for="presente_nao">
                                        <i class="bi bi-emoji-smile-fill icon-option"></i>
                                        <span>Sim, será meu companheiro</span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="pet_sera_presente" id="presente_sim" value="sim" required>
                                    <label class="card-radio-option" for="presente_sim">
                                        <i class="bi bi-gift-fill icon-option"></i>
                                        <span>Não, será um presente</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group bg-light-alert p-3 rounded" id="div-responsavel-presente" style="display: none;">
                            <label class="question-label text-vermelho">A pessoa presenteada está ciente e se responsabilizará?</label>
                            <div class="row g-3 mt-1">
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="presente_responsavel" id="responsavel_sim" value="sim">
                                    <label class="btn btn-outline-custom w-100" for="responsavel_sim">Sim</label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="presente_responsavel" id="responsavel_nao" value="nao">
                                    <label class="btn btn-outline-custom w-100" for="responsavel_nao">Não</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <label class="question-label">Histórico com Pets</label>
                            <div class="d-flex flex-column gap-2">
                                <input type="radio" class="btn-check" name="teve_pets" id="pets_tenho" value="Sim, eu tenho" required>
                                <label class="card-simple-option" for="pets_tenho">Sim, eu tenho atualmente</label>

                                <input type="radio" class="btn-check" name="teve_pets" id="pets_tive" value="Sim, já tive" required>
                                <label class="card-simple-option" for="pets_tive">Já tive, mas não tenho agora</label>

                                <input type="radio" class="btn-check" name="teve_pets" id="pets_nao_tenho" value="Não tenho" required>
                                <label class="card-simple-option" for="pets_nao_tenho">Nunca tive, será o primeiro</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-card shadow-sm mt-4">
                        <h3 class="form-section-title font-kawaii"><i class="bi bi-shield-check me-2"></i>Termos Finais</h3>

                        <div class="question-group">
                            <label class="question-label">Visita Domiciliar</label>
                            <p class="text-muted md">Autoriza uma visita do protetor para verificar a segurança do local, se necessário?</p>
                            <div class="row g-3">
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="autoriza_visita" id="visita_sim" value="sim" required>
                                    <label class="btn btn-outline-custom w-100" for="visita_sim">Autorizo</label>
                                </div>
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="autoriza_visita" id="visita_nao" value="nao" required>
                                    <label class="btn btn-outline-custom w-100" for="visita_nao">Não Autorizo</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <label class="question-label">Compromisso de Devolução</label>
                            <p class="text-muted md">Se não puder mais ficar com o pet, compromete-se a devolvê-lo ao protetor?</p>
                            <div class="row g-3">
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="ciente_devolucao" id="devolucao_sim" value="sim" required>
                                    <label class="btn btn-outline-custom w-100" for="devolucao_sim">Sim, prometo</label>
                                </div>
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="ciente_devolucao" id="devolucao_nao" value="nao" required>
                                    <label class="btn btn-outline-custom w-100" for="devolucao_nao">Não</label>
                                </div>
                            </div>
                        </div>

                        <div class="question-group">
                            <label class="question-label">Termo de Responsabilidade</label>
                            <p class="text-muted md">Compromete-se a assinar o termo legal de adoção no ato da entrega?</p>
                            <div class="row g-3">
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="ciente_termo_responsabilidade" id="termo_sim" value="sim" required>
                                    <label class="btn btn-outline-custom w-100" for="termo_sim">Com certeza</label>
                                </div>
                                <div class="col-6 col-md-4">
                                    <input type="radio" class="btn-check" name="ciente_termo_responsabilidade" id="termo_nao" value="nao" required>
                                    <label class="btn btn-outline-custom w-100" for="termo_nao">Não</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-5">
                           <button type="submit" class="adopt-btn w-75 btn-adocao-submit">
                                <div class="heart-background" aria-hidden="true">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                                <span>Enviar Solicitação</span>
                            </button>
                            <p class="mt-3 text-muted small">Ao enviar, o protetor receberá seus dados.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elementos
        const termoModalElement = document.getElementById('termoModal');
        const termoCheckbox = document.getElementById('termo-ciencia');
        const btnConfirmarModal = document.getElementById('btn-confirmar-modal');
        const formPrincipal = document.getElementById('form-principal');

        // Inicializa Modal
        var myModal = new bootstrap.Modal(termoModalElement, {
            keyboard: false,
            backdrop: 'static'
        });
        myModal.show();

        // Checkbox do Termo
        termoCheckbox.addEventListener('change', function() {
            btnConfirmarModal.disabled = !this.checked;
        });

        // Confirmar Modal
        btnConfirmarModal.addEventListener('click', function() {
            myModal.hide();
            formPrincipal.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Lógica condicional: Presente
        const radiosPresente = document.querySelectorAll('input[name="pet_sera_presente"]');
        const divResponsavel = document.getElementById('div-responsavel-presente');
        const radiosResponsavel = document.querySelectorAll('input[name="presente_responsavel"]');

        radiosPresente.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'sim') {
                    divResponsavel.style.display = 'block';
                    divResponsavel.classList.add('fade-in-up');
                    radiosResponsavel.forEach(r => r.required = true);
                } else {
                    divResponsavel.style.display = 'none';
                    radiosResponsavel.forEach(r => r.required = false);
                    radiosResponsavel.forEach(r => r.checked = false);
                }
            });
        });
    });
    </script>
</body>
</html>
