<?php
require_once __DIR__ . '/app/security.php';
appStartSession();

include_once 'conexao.php'; // 1. Inclui a conexão com o banco

// Verifica se há mensagens de sucesso/erro para exibir
$toast_message = '';
$toast_type = 'success';

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    
    // Limpa a mensagem da sessão após usar
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// 2. Segurança: Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    // Se não estiver logado, redireciona para a página de login
    header("Location: login");
    exit;
}
// 3. Pega os dados básicos da sessão
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$usuario = null;
$erro = '';

// 4. Busca os dados completos do usuário no banco (Isso só executa UMA VEZ)
try {
    if ($user_tipo == 'usuario') {
        $sql = "SELECT nome, email, cep, numero, complemento, cpf, banner_fixo FROM usuario WHERE id_usuario = :id LIMIT 1";
    } elseif ($user_tipo == 'ong') {
        $sql = "SELECT nome, email, cnpj, banner_fixo FROM ong WHERE id_ong = :id LIMIT 1";
    } elseif ($user_tipo == 'admin') {
        $sql = "SELECT nome, email FROM administrador WHERE id_admin = :id LIMIT 1";
    } else {
        $erro = "Tipo de usuário inválido.";
    }
    
    if (empty($erro)) {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_tipo == 'admin') {
            $usuario['banner_fixo'] = 'banner1.jpg'; 
        }
        
        if (!$usuario) {
            $erro = "Usuário não encontrado no banco de dados.";
        }
    }
} catch (PDOException $e) {
    $erro = "Ocorreu um erro ao buscar seus dados. Tente novamente.";
    error_log('Erro ao carregar perfil: ' . $e->getMessage());
}

/* ==========================================================================
LÓGICA DE NAVEGAÇÃO (SWITCH CASE) E BANNER
========================================================================== */

// 1. Determina a página atual. O padrão é 'perfil'.
// Usamos um parâmetro URL 'page' para controlar o switch
$pagina = $_GET['page'] ?? 'perfil';

// Verifica se há mensagens de sucesso/erro para exibir
if ($pagina == 'meus-pets') {
    if (isset($_SESSION['toast_message'])) {
        $toast_message = $_SESSION['toast_message'];
        $toast_type = $_SESSION['toast_type'] ?? 'success';
        
        // Limpa a mensagem da sessão após usar
        unset($_SESSION['toast_message']);
        unset($_SESSION['toast_type']);
    }
}

// 2. Prepara a lógica do banner, MAS só se a página for 'perfil'
$caminhoBanner = ''; // Inicializa a variável
if ($pagina == 'perfil') {
    // Usa o banner fixo do banco, se existir, senão usa o banner1.jpg
    $bannerFixo = $usuario['banner_fixo'] ?? 'banner1.jpg';
    $caminhoBanner = 'images/perfil/' . $bannerFixo;
}

// --- LÓGICA DO ADMIN (BUSCA TUDO) ---
$todos_usuarios = [];
$todas_ongs = [];
$todos_pets = [];
$solicitacoes_exclusao = [];

// Variáveis do Dashboard
$total_users_count = 0;
$total_ongs_count = 0;
$total_pets_count = 0;
$total_adocoes_count = 0;

if ($user_tipo == 'admin' && $pagina == 'painel-admin') {
    try {
        // --- 1. DASHBOARD COUNTS ---
        $total_users_count = $conn->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
        $total_ongs_count = $conn->query("SELECT COUNT(*) FROM ong")->fetchColumn();
        $total_pets_count = $conn->query("SELECT COUNT(*) FROM pet")->fetchColumn();
        $total_adocoes_count = $conn->query("SELECT COUNT(*) FROM solicitacao")->fetchColumn();

        // Busca Usuários
        $stmt = $conn->query("SELECT * FROM usuario ORDER BY id_usuario DESC");
        $todos_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Busca ONGs
        $stmt = $conn->query("SELECT * FROM ong ORDER BY id_ong DESC");
        $todas_ongs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Busca Pets (com nome do dono)
        $sql_pets_admin = "
            SELECT p.*, 
                   COALESCE(u.nome, o.nome) as dono_nome,
                   CASE WHEN p.id_usuario_fk IS NOT NULL THEN 'Usuário' ELSE 'ONG' END as tipo_dono
            FROM pet p
            LEFT JOIN usuario u ON p.id_usuario_fk = u.id_usuario
            LEFT JOIN ong o ON p.id_ong_fk = o.id_ong
            ORDER BY p.id_pet DESC
        ";
        $stmt = $conn->query($sql_pets_admin);
        $todos_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query(
            "SELECT id_request, account_id, account_type, requested_at"
            . " FROM account_deletion_request WHERE status = 'pending'"
            . " ORDER BY requested_at ASC"
        );
        $solicitacoes_exclusao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $erro = "Não foi possível carregar os dados do painel.";
        error_log('Erro ao carregar painel administrativo: ' . $e->getMessage());
    }
}

// ==========================================================
// Lógica para buscar os pets do usuário (APENAS se a página for 'meus-pets')
// ==========================================================
$pets = [];
$erro_pets = '';
if ($pagina == 'meus-pets') {
    try {
        $sql_pets = "SELECT 
                        p.id_pet, p.nome, p.sexo, p.status_disponibilidade, p.raca, p.idade,
                        pf.caminho_foto AS foto
                    FROM 
                        pet AS p
                    LEFT JOIN (
                        -- Subquery para achar a primeira foto (menor ID) de cada pet
                        SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                        FROM pet_fotos
                        GROUP BY id_pet_fk
                    ) pf_min ON p.id_pet = pf_min.id_pet_fk
                    LEFT JOIN 
                        pet_fotos AS pf ON pf.id_foto = pf_min.min_id_foto
                    WHERE ";
        
        if ($user_tipo == 'usuario') {
            $sql_pets .= "id_usuario_fk = :id_usuario"; 
        } elseif ($user_tipo == 'ong') {
            $sql_pets .= "id_ong_fk = :id_usuario";
        } else {
            throw new Exception("Tipo de usuário inválido para buscar pets.");
        }
        $stmt_pets = $conn->prepare($sql_pets);
        $stmt_pets->bindParam(':id_usuario', $user_id, PDO::PARAM_INT);
        $stmt_pets->execute();
        $pets = $stmt_pets->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erro_pets = "Não foi possível carregar seus pets.";
        error_log('Erro ao buscar pets do perfil: ' . $e->getMessage());
    }
}

// 4. --- LÓGICA 'Pets Curtidos' ---
$pets_curtidos = [];
$erro_pets_curtidos = '';
if ($pagina == 'pets-curtidos') {
    try {
        // SQL com JOIN para buscar os pets favoritados pelo usuário
        $sql_curtidos = "SELECT 
                             p.id_pet, p.nome, p.sexo,
                             pf.caminho_foto AS foto
                         FROM 
                             favorito AS f
                         JOIN 
                             pet AS p ON f.id_pet = p.id_pet
                         LEFT JOIN (
                            -- Subquery para achar a primeira foto
                            SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                            FROM pet_fotos
                            GROUP BY id_pet_fk
                         ) pf_min ON p.id_pet = pf_min.id_pet_fk
                         LEFT JOIN 
                            pet_fotos AS pf ON pf.id_foto = pf_min.min_id_foto
                         WHERE 
                             f.id_usuario = :id_usuario";
                         
        $stmt_curtidos = $conn->prepare($sql_curtidos);
        $stmt_curtidos->bindParam(':id_usuario', $user_id, PDO::PARAM_INT);
        $stmt_curtidos->execute();
        $pets_curtidos = $stmt_curtidos->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $erro_pets_curtidos = "Não foi possível carregar seus pets curtidos.";
        error_log("Erro ao buscar pets curtidos: " . $e->getMessage());
    }
}

// ==========================================================
// LÓGICA PARA PETS EM ANÁLISE (ADMIN)
// ==========================================================
$pets_analise = [];
$erro_analise = '';
if ($pagina == 'pet-analise') {
    try {
        $sql_analise = "
            SELECT p.*, 
                   COALESCE(u.nome, o.nome) as dono_nome,
                   CASE WHEN p.id_usuario_fk IS NOT NULL THEN 'Usuário' ELSE 'ONG' END as tipo_dono,
                   pf.caminho_foto AS foto
            FROM pet p
            LEFT JOIN usuario u ON p.id_usuario_fk = u.id_usuario
            LEFT JOIN ong o ON p.id_ong_fk = o.id_ong
            LEFT JOIN (
                SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                FROM pet_fotos
                GROUP BY id_pet_fk
            ) pf_min ON p.id_pet = pf_min.id_pet_fk
            LEFT JOIN pet_fotos AS pf ON pf.id_foto = pf_min.min_id_foto
            WHERE p.status_disponibilidade = 'Em Analise'
            ORDER BY p.id_pet DESC
        ";
        
        $stmt_analise = $conn->query($sql_analise);
        $pets_analise = $stmt_analise->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $erro_analise = "Não foi possível carregar os pets para análise.";
        error_log("Erro ao buscar pets em análise: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>
        <?php
        switch ($pagina) {
            case 'meus-pets':
                echo "Meus Pets";
                break;
            case 'pets-curtidos':
                echo "Pets Curtidos";
                break;
            case 'painel-admin':
                echo "Painel Admin";
                break;
            case 'pet-analise':
                echo "Pets em Análise";
                break;
            case 'perfil':
            default:
                echo "Meu Perfil";
                break;
        }
        ?> - Adote Patas
    </title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
      <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <link rel="stylesheet" href="assets/css/pages/perfil/perfil.css">
    <link rel="stylesheet" href="assets/css/global/toast.css">
</head>
<body class="profile-body">

    <a href="./" class="btn-voltar" title="Voltar para a página inicial">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Voltar</span>
    </a>

    <div id="toast-notification" class="toast" style="display: none;">
        <div id="toast-icon" class="toast-icon"></div>
        <div class="toast-content">
            <p id="toast-message" class="toast-message text-center">Mensagem...</p>
        </div>
        <div class="toast-progress-bar"></div>
    </div>

    <div class="container-fluid mt-5 pt-4">
        <div class="row full-height-row">

            <div class="col-lg-9">
                <?php
                // Inicia o switch case para carregar o conteúdo principal
                switch ($pagina) {

                    // ==========================================================
                    // CASO ADMIN: PAINEL GERAL
                    // ==========================================================
                    case 'painel-admin':
                        if ($user_tipo !== 'admin') { header("Location: login"); break; }
                ?>
                    <main style="animation: fadeIn 0.8s ease-out;">
                        <h1 class="mb-4 fw-bold" style="color: var(--cor-rosa-escuro);">
                            <i class="fa-solid fa-user-shield me-2"></i>Painel Administrativo
                        </h1>

                    <!-- DASHBOARD CARDS (NOVO) -->
                        <div class="row g-4 mb-4">
                            <!-- Card Usuários -->
                            <div class="col-md-3">
                                <div class="card dashboard-card card-users p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-1">Usuários (PF)</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $total_users_count; ?></h2>
                                        </div>
                                        <div class="dashboard-icon">
                                            <i class="fa-solid fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card ONGs -->
                            <div class="col-md-3">
                                <div class="card dashboard-card card-ongs p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-1">ONGs Parceiras</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $total_ongs_count; ?></h2>
                                        </div>
                                        <div class="dashboard-icon">
                                            <i class="fa-solid fa-building-ngo"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Pets -->
                            <div class="col-md-3">
                                <div class="card dashboard-card card-pets p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-1">Pets Cadastrados</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $total_pets_count; ?></h2>
                                        </div>
                                        <div class="dashboard-icon">
                                            <i class="fa-solid fa-paw"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Solicitações -->
                            <div class="col-md-3">
                                <div class="card dashboard-card card-solicitacoes p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-1">Adoções Iniciadas</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $total_adocoes_count; ?></h2>
                                        </div>
                                        <div class="dashboard-icon">
                                            <i class="fa-solid fa-heart"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- TABELA DE PETS -->
                        <div class="admin-table-card">
                            <h3 class="mb-3 text-secondary">Gerenciar Pets (<?php echo count($todos_pets); ?>)</h3>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Dono</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todos_pets as $p): ?>
                                        <tr>
                                            <td>#<?php echo $p['id_pet']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($p['nome']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($p['dono_nome']); ?> 
                                                <small class="text-muted">(<?php echo $p['tipo_dono']; ?>)</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($p['status_disponibilidade'] == 'disponivel') ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($p['status_disponibilidade']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="pet-detalhe/<?php echo $p['id_pet']; ?>" target="_blank" class="btn btn-sm btn-info text-white" title="Ver"><i class="fa-solid fa-eye"></i></a>
                                                <!-- Reusa a página de editar pet, passando o ID -->
                                                <a href="editar-pet.php?id=<?php echo $p['id_pet']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="fa-solid fa-pencil"></i></a>
                                                <button type="button" onclick="confirmarExclusao('excluir_pet', <?php echo (int) $p['id_pet']; ?>)" class="btn btn-sm btn-danger" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        
                        <!-- TABELA DE USUÁRIOS -->
                        <div class="admin-table-card">
                            <h3 class="mb-3 text-secondary">Gerenciar Usuários (<?php echo count($todos_usuarios); ?>)</h3>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>CPF</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todos_usuarios as $u): ?>
                                        <tr>
                                            <td>#<?php echo $u['id_usuario']; ?></td>
                                            <td><?php echo htmlspecialchars($u['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td><?php echo htmlspecialchars($u['cpf']); ?></td>
                                            <td>
                                                <button type="button" onclick="confirmarExclusao('excluir_usuario', <?php echo (int) $u['id_usuario']; ?>)" class="btn btn-sm btn-danger" title="Excluir Usuário"><i class="fa-solid fa-user-xmark"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TABELA DE ONGS -->
                        <div class="admin-table-card">
                            <h3 class="mb-3 text-secondary">Gerenciar ONGs (<?php echo count($todas_ongs); ?>)</h3>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>CNPJ</th>
                                            <th>Email</th>
                                            <th>Verificação</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todas_ongs as $o): ?>
                                        <tr>
                                            <td>#<?php echo $o['id_ong']; ?></td>
                                            <td><?php echo htmlspecialchars($o['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($o['cnpj']); ?></td>
                                            <td><?php echo htmlspecialchars($o['email']); ?></td>
                                            <td>
                                                <?php
                                                $verificationStatus = $o['verification_status'] ?? 'pending';
                                                $verificationLabels = ['verified' => 'Verificada', 'rejected' => 'Recusada', 'pending' => 'Pendente'];
                                                $verificationClasses = ['verified' => 'success', 'rejected' => 'danger', 'pending' => 'secondary'];
                                                ?>
                                                <span class="badge bg-<?php echo $verificationClasses[$verificationStatus] ?? 'secondary'; ?>">
                                                    <?php echo $verificationLabels[$verificationStatus] ?? 'Pendente'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" onclick="alterarVerificacaoOng('verificar_ong', <?php echo (int) $o['id_ong']; ?>)" class="btn btn-sm btn-success" title="Verificar ONG"><i class="fa-solid fa-circle-check"></i></button>
                                                <button type="button" onclick="alterarVerificacaoOng('rejeitar_ong', <?php echo (int) $o['id_ong']; ?>)" class="btn btn-sm btn-warning" title="Recusar verificação"><i class="fa-solid fa-ban"></i></button>
                                                <button type="button" onclick="confirmarExclusao('excluir_ong', <?php echo (int) $o['id_ong']; ?>)" class="btn btn-sm btn-danger" title="Excluir ONG"><i class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-table-card">
                            <h3 class="mb-3 text-secondary">Solicitações de exclusão (<?php echo count($solicitacoes_exclusao); ?>)</h3>
                            <?php if ($solicitacoes_exclusao === []): ?>
                                <p class="text-muted mb-0">Nenhuma solicitação pendente.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light"><tr><th>Protocolo</th><th>Conta</th><th>Solicitada em</th><th>Ações</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($solicitacoes_exclusao as $request): ?>
                                            <tr>
                                                <td>#<?php echo (int) $request['id_request']; ?></td>
                                                <td><?php echo $request['account_type'] === 'ong' ? 'ONG' : 'Usuário'; ?> #<?php echo (int) $request['account_id']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($request['requested_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="processarExclusaoConta('aprovar_exclusao_conta', <?php echo (int) $request['id_request']; ?>)">Excluir conta</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="processarExclusaoConta('rejeitar_exclusao_conta', <?php echo (int) $request['id_request']; ?>)">Rejeitar</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                    </main>
                <?php
                    break; // Fim do 'case painel-admin'

                    // ==========================================================
                    // CASO ADMIN: PETS EM ANÁLISE
                    // ==========================================================
                    case 'pet-analise':
                        if ($user_tipo !== 'admin') { 
                         header("Location: login"); 
                            break; 
                        }
                    ?>
                        <main class="profile-card" style="animation: fadeIn 0.8s ease-out;">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h1 class="fw-bold"  style="color: var(--cor-rosa-escuro) !important;">
                                    <i class="fa-solid fa-clipboard-list me-2"></i>Pets em Análise
                                </h1>
                               <span id="contador-pets" class="badge fs-6" style="background-color: var(--cor-rosa-escuro) !important; color: var(--cor-branca); !important">
    <?php echo count($pets_analise); ?> pets aguardando
</span>
                            </div>

                            <?php if (!empty($erro_analise)): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($erro_analise); ?>
                                </div>
                            <?php elseif (empty($pets_analise)): ?>
                                <div class="d-flex justify-content-center flex-column align-items-center py-5">
                                    
                            <lottie-player src="animações/cat-laptop.json" background="transparent" speed="1" style="width: 300px; height: 300px;"
                                loop autoplay>
                            </lottie-player>

                                    <h3 style="color: var(--cor-vermelho);">Nenhum pet aguardando análise!</h3>
                                    <p class="mb-0">Todos os pets foram analisados e aprovados.</p>
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4" id="petsAnaliseGrid">
                                    <?php foreach ($pets_analise as $pet): ?>
                                    <div class="col" id="pet-card-<?php echo $pet['id_pet']; ?>">
                                        <div class="pet-card">
                                            <div class="pet-card-img">
                                                <img src="<?php echo htmlspecialchars($pet['foto'] ?? 'images/global/placeholder-pet.png'); ?>" 
                                                     alt="Foto de <?php echo htmlspecialchars($pet['nome']); ?>"
                                                     onerror="this.src='images/perfil/teste.jpg';">
                                            </div>
                                            
                                            <div class="pet-card-body flex-wrap p-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h2 class="pet-name me-2 mb-0"><?php echo htmlspecialchars($pet['nome']); ?></h2>
                                                    <?php if (!empty($pet['sexo'])): ?>
                                                        <?php if ($pet['sexo'] == 'femea'): ?>
                                                            <i class="fa-solid fa-venus pet-gender-female" style="color: var(--cor-femea-simbolo)" aria-label="Fêmea" title="Fêmea"></i>
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-mars pet-gender-male" style="color: var(--cor-macho-simbolo)" aria-label="Macho" title="Macho"></i>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="pet-info mb-2">
                                                    <small class="text-muted">
                                                        <strong>Dono:</strong> <?php echo htmlspecialchars($pet['dono_nome']); ?> (<?php echo $pet['tipo_dono']; ?>)
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <div class="pet-card-actions d-flex justify-content-between align-items-center p-3 border-top">
                                                <span class="badge bg-warning text-dark status-badge">
                                                    <i class="fa-solid fa-clock me-1"></i>Em Análise
                                                </span>
                                                <div class="action-buttons">
                                                    <?php if (!empty($pet['carteira_vacinacao'])): ?>
                                                    <a href="<?php echo htmlspecialchars($pet['carteira_vacinacao']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-info text-white" 
                                                       title="Ver Carteirinha de Vacinação">
                                                       <i class="fas fa-file-medical"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="editar-pet.php?id=<?php echo $pet['id_pet']; ?>" class="btn btn-sm btn-outline-primary me-2" title="Editar Pet"> <i class="fa-solid fa-pencil"></i> </a>
                                                    <button class="btn btn-sm btn-success btn-aprovar-pet" 
                                                            data-pet-id="<?php echo $pet['id_pet']; ?>"
                                                            title="Aprovar Pet">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </main>

            <script>
    function refresh() {
        window.location.reload();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const petsGrid = document.getElementById('petsAnaliseGrid');

        if (petsGrid) {
            petsGrid.addEventListener('click', function(event) {
                const aprovarBtn = event.target.closest('.btn-aprovar-pet');
                if (aprovarBtn) {
                    event.preventDefault();
                    const petId = aprovarBtn.dataset.petId;
                    aprovarPet(petId, aprovarBtn);
                }
            });
        }

        async function aprovarPet(petId, buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>';

            try {
                const response = await fetch('aprovar-pet.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id_pet=${petId}`
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showToast('Pet analisado com sucesso!', 'success');

                    // Remove visualmente
                    const petCard = document.getElementById(`pet-card-${petId}`);
                    if (petCard) petCard.classList.add("fade-out");

                    setTimeout(() => {
                        if (petCard) petCard.remove();

                        // --- LÓGICA DE CONTAGEM E REFRESH ---
                        
                        // 1. Tenta pelo ID do contador (Mais preciso)
                        const contadorEl = document.getElementById('contador-pets');
                        
                        // 2. Backup: Verifica se a Grid ficou vazia
                        const gridEl = document.getElementById('petsAnaliseGrid');
                        const isGridEmpty = gridEl && gridEl.children.length === 0;

                        let count = 0;

                        if (contadorEl) {
                            // Pega apenas os números do texto
                            let currentText = contadorEl.textContent; 
                            let currentNumber = parseInt(currentText.replace(/\D/g, '')); // Remove tudo que não é número

                            if (!isNaN(currentNumber)) {
                                count = Math.max(0, currentNumber - 1);
                                contadorEl.textContent = `${count} pets aguardando`;
                            }
                        }

                        // 3. DECISÃO FINAL DE REFRESH
                        // Atualiza se o contador chegou a 0 OU se a grid está visualmente vazia
                        if (count === 0 || isGridEmpty) {
                            console.log("Todos os pets analisados. Atualizando...");
                            refresh();
                        }

                    }, 1000);

                } else {
                    showToast(result.message || 'Erro ao aprovar.', 'danger');
                    resetButton(buttonElement);
                }

            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro de conexão.', 'danger');
                resetButton(buttonElement);
            }
        }

        function resetButton(btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>';
        }
    });
</script>
                    <?php
                        break; // Fim do 'case pet-analise'
                    
                    // ==========================================================
                    // CASO 1: PÁGINA 'MEU PERFIL' (Padrão)
                    // ==========================================================
                    case 'perfil':
                    default:
                ?>
                        <main class="profile-card" style=" animation: fadeIn 0.8s ease-out;">

                            <div class="banner">
    <img src="<?php echo htmlspecialchars($caminhoBanner); ?>" alt="Banner do Usuário">
    <!-- Botão para trocar banner - AGORA NO CANTO INFERIOR DIREITO -->
    <div class="banner-actions">
        <button type="button" class="btn btn-sm btn-light btn-change-banner" data-bs-toggle="modal" data-bs-target="#bannerModal">
            <i class="fa-solid fa-image me-1"></i> Trocar Banner
        </button>
    </div>
</div>

                            <h1>Meu Perfil</h1>

                            <?php if (!empty($erro)): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($erro); ?>
                                </div>

                            <?php elseif ($usuario): ?>
                                <form id="profileForm" novalidate>
                                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                    <input type="hidden" name="user_tipo" value="<?php echo $user_tipo; ?>">

                                    <div class="mb-3">
                                        <label for="inputNome" class="form-label"><strong>Nome:</strong></label>
                                        <input type="text" class="form-control" id="inputNome" name="nome"
                                               value="<?php echo htmlspecialchars($usuario['nome']); ?>" disabled data-profile-field>
                                        <div id="feedbackNome" class="feedback-message"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="inputEmail" class="form-label"><strong>E-mail:</strong></label>
                                        <input type="email" class="form-control" id="inputEmail" name="email"
                                               value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled data-profile-field>
                                        <div id="feedbackEmail" class="feedback-message"></div>
                                    </div>

                                    <?php if ($user_tipo == 'usuario'): ?>
                                        <div class="mb-3">
                                            <label for="inputDocumento" class="form-label"><strong>CPF:</strong></label>
                                            <input type="text" class="form-control" id="inputDocumento" name="documento"
                                                   value="<?php echo htmlspecialchars($usuario['cpf']); ?>" disabled data-profile-field>
                                            <div id="feedbackDocumento" class="feedback-message"></div>
                                        </div>
                                    <?php elseif ($user_tipo == 'ong'): ?>
                                        <div class="mb-3">
                                            <label for="inputDocumento" class="form-label"><strong>CNPJ:</strong></label>
                                            <input type="text" class="form-control" id="inputDocumento" name="documento"
                                                   value="<?php echo htmlspecialchars($usuario['cnpj']); ?>" disabled data-profile-field>
                                            <div id="feedbackDocumento" class="feedback-message"></div>
                                        </div>
                                    <?php endif; ?>

                                <?php if (in_array($user_tipo, ['usuario', 'ong'])) : ?>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                        <label for="inputCep" class="form-label"><strong>CEP:</strong></label>
                                            <input type="text" class="form-control" id="inputCep" name="cep"
                                                   placeholder="CEP"
                                                   value="<?php echo htmlspecialchars($usuario['cep'] ?? ''); ?>" disabled data-profile-field>
                                            <div id="feedbackCep" class="feedback-message"></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="inputNumero" class="form-label"><strong>Número:</strong></label>
                                            <input type="text" class="form-control" id="inputNumero" name="numero"
                                                   placeholder="Número"
                                                   value="<?php echo htmlspecialchars($usuario['numero'] ?? ''); ?>" disabled data-profile-field>
                                            <div id="feedbackNumero" class="feedback-message"></div>
                                        </div>
                                        <div class="col-md-5">
                                            <label for="inputComplemento" class="form-label"><strong>Complemento:</strong></label>
                                            <input type="text" class="form-control" id="inputComplemento" name="complemento"
                                                   placeholder="Complemento"
                                                   value="<?php echo htmlspecialchars($usuario['complemento'] ?? ''); ?>" disabled data-profile-field>
                                        </div>
                                    </div>
                                                                        
                                    <input type="hidden" id="inputLogradouro" name="logradouro" data-profile-field 
                                           value="<?php echo htmlspecialchars($usuario['logradouro'] ?? ''); ?>">
                                    <input type="hidden" id="inputBairro" name="bairro" data-profile-field
                                           value="<?php echo htmlspecialchars($usuario['bairro'] ?? ''); ?>">
                                    <input type="hidden" id="inputCidade" name="cidade" data-profile-field
                                           value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>">
                                    <input type="hidden" id="inputEstado" name="estado" data-profile-field
                                           value="<?php echo htmlspecialchars($usuario['estado'] ?? ''); ?>">

                                    <?php endif; ?>

                                    <hr class="my-4">

                                    <button type="button" id="btnEditar" class="btn btn-danger">
                                        <i class="fa-solid fa-pencil me-1"></i> Editar Perfil
                                    </button>

                                    <button type="submit" id="btnSalvar" class="btn btn-success d-none">
                                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                        </main>
                <?php
                    break; // Fim do 'case perfil'

                    // ==========================================================
                    // CASO 2: PÁGINA 'MEUS PETS'
                    // ==========================================================
                    case 'meus-pets':
                ?>
                        <main class="profile-card pets-grid-container"  style=" animation: fadeIn 0.8s ease-out;">
    
    <div class="d-flex justify-content-between align-items-center m-0 header-pets-section" style="border-bottom: 2px solid var(--cor-rosa-pastel); padding-bottom: 10px;">
        <h1 class="mb-0 border border-0 section-title-pets">Meus Pets</h1>
        <a href="cadastrar-pet.php" class="btn btn-danger btn-add-pet-header">
            <i class="fa-solid fa-plus me-1"></i> Cadastrar Pet
        </a>
    </div>

    <?php if (!empty($erro_pets)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($erro_pets); ?>
        </div>

    <?php elseif (empty($pets)): ?>
        <div class="no-pets-section d-flex flex-column align-items-center justify-content-center text-center p-4 p-md-5" style="margin-top: -50px;">
            <div class="no-pets-illustration mb-4">
                 <lottie-player src="animações/pets.json" background="transparent" speed="1" style="width: 250px; height: 250px;"
    loop autoplay>
</lottie-player>
            </div>
            <h2 class="section-title mb-3" style="color: var(--cor-cinza-texto)">Ainda não tem um pet registrado?</h2>
            <p class="section-description mb-4">
                Que tal adicionar seu Pet e começar a aproveitar todos os benefícios? É rápido e fácil!
            </p>
            <a href="cadastrar-pet.php" class="btn btn-success btn-lg custom-btn-add-pet">
                <i class="fas fa-plus-circle me-2"></i> Cadastrar Novo Pet
            </a>
            <p class="mt-3 text-muted">
                <em>Encontre a felicidade em cada patinha cadastrada!</em>
            </p>
        </div>

    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4" id="petsGrid">
            <?php foreach ($pets as $pet): 
                
                // Lógica para determinar a cor do badge baseada no status
$badgeClass = match ($pet['status_disponibilidade']) {
    'Disponivel'   => 'bg-success',        // Verde padrão do Bootstrap (ou crie --cor-disponivel)
    'Em Analise'   => 'badge-analise',     // Vermelho/Laranja de alerta
    'Adotado'      => 'badge-adotado',     // O novo verde personalizado
    'Indisponivel' => 'badge-indisponivel',// O novo cinza
    default        => 'bg-secondary',      // Fallback (cinza padrão)
};

                ?>
            <div class="col">
                <a style="text-decoration: none; color: var(--cor-cinza-texto)" href="pet-detalhe/<?php echo ($pet['id_pet']) ?>">
                    <div class="pet-card">
                        <div class="pet-card-img">
                            <img src="<?php echo htmlspecialchars($pet['foto'] ?? 'images/global/placeholder-pet.png'); ?>" 
                            alt="Foto de <?php echo htmlspecialchars($pet['nome']); ?>"
                            onerror="this.src='images/perfil/teste.jpg';"> </div>
                            
                            <div class="pet-card-body flex-wrap p-2">
                                <div class="d-flex align-items-center mb-2">
                                    <h2 class="pet-name me-2 mb-0"><?php echo htmlspecialchars($pet['nome']); ?></h2>
                                    <?php if (!empty($pet['sexo'])): ?>
                                        <?php if ($pet['sexo'] == 'femea'): ?>
                                            <i class="fa-solid fa-venus pet-gender-female"style="color: var(--cor-femea-simbolo)" aria-label="Fêmea" title="Fêmea"></i>
                                            <?php else: // 'macho' ?>
                                                <i class="fa-solid fa-mars pet-gender-male" style="color: var(--cor-macho-simbolo)" aria-label="Macho" title="Macho"></i>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="pet-card-actions d-flex justify-content-between align-items-center p-3 border-top">
                                      <span class="badge <?php echo $badgeClass; ?> status-badge">
    <?php echo $pet['status_disponibilidade']; ?>
</span>
                                            <div class="action-buttons">
                                                <a href="editar-pet.php?id=<?php echo $pet['id_pet']; ?>" class="btn btn-sm btn-outline-primary me-2" title="Editar Pet">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </a>
                                                <button type="button" data-pet-id="<?php echo (int) $pet['id_pet']; ?>" class="btn btn-sm btn-outline-danger btn-excluir-pet" title="Excluir Pet">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

</main>
                <?php
                    break; // Fim do 'case meus-pets'

                    // ==========================================================
                    // CASO 3: PÁGINA 'PETS CURTIDOS'
                    // ==========================================================
                    case 'pets-curtidos':
                ?>
                       <main class="profile-card pets-grid-container"  style=" animation: fadeIn 0.8s ease-out;">
                            <h1 class="mb-4">Pets Curtidos</h1>

                            <?php if (!empty($erro_pets_curtidos)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($erro_pets_curtidos); ?></div>
                            
                            <?php elseif (empty($pets_curtidos)): ?>

                                <div class="alert text-center flex-column d-flex justify-content align-items-center text-center" style="margin-top: -1.5rem; animation: fadeIn 0.8s ease-out;">
                                         
                                <lottie-player src="animações/dog-beijos.json" background="transparent" speed="1" style="width: 200px; height: 200px;"
                                loop autoplay>
                            </lottie-player>
                                    <h5 class="mb-1">Você ainda não curtiu nenhum amiguinho.</h5>
                                    <p>Que tal curtir seu novo amigo?</p>
                                    <a href="pets" class="btn btn-danger btn-add-pet-header" style="box-shadow: rgba(50, 50, 93, 0.25) 0px 13px 27px -5px, rgba(0, 0, 0, 0.3) 0px 8px 16px -8px;">Ver pets para adoção</a>
                                </div>
                            
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4" id="petsGrid">
                                
                                    <?php foreach ($pets_curtidos as $pet): ?>
                                    <div class="col">
                                        <div class="pet-card">
                                            <a href="pet-detalhe.php?id=<?php echo $pet['id_pet']; ?>" class="pet-card-link-img">
                                                <div class="pet-card-img">
                                                    <img src="<?php echo htmlspecialchars($pet['foto'] ?? 'images/global/placeholder-pet.png'); ?>" 
                                                         alt="Foto de <?php echo htmlspecialchars($pet['nome']); ?>"
                                                         onerror="this.src='images/perfil/teste.jpg';">
                                                </div>
                                            </a>
                                            <div class="pet-card-body">
                                                <h2 class="pet-name" style="color: var(--cor-cinza-texto);">
                                                    <a href="pet-detalhe.php?id=<?php echo $pet['id_pet']; ?>" class="pet-card-link-nome">
                                                        <?php echo htmlspecialchars($pet['nome']); ?>
                                                    </a>
                                                </h2>
                                                
                                                <?php if (!empty($pet['sexo'])): ?>
                                                    <?php if ($pet['sexo'] == 'femea'): ?>
                                                <i class="fa-solid fa-venus pet-gender-female"style="color: var(--cor-femea-simbolo)" aria-label="Fêmea" title="Fêmea"></i>

                                                    <?php else: ?>
                                                <i class="fa-solid fa-mars pet-gender-male" style="color: var(--cor-macho-simbolo)" aria-label="Macho" title="Macho"></i>

                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <i class="pet-like fa-solid fa-heart favorited" 
                                                   data-pet-id="<?php echo $pet['id_pet']; ?>" 
                                                   aria-label="Desfavoritar" 
                                                   role="button">
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>

                                </div>
                            <?php endif; ?>
                        </main>
                <?php
                    break; // Fim do 'case pets-curtidos'
                
                } // Fim do switch
                ?>
            </div>

                 <div class="col-lg-3 sidebar-wrapper-col">
                <div class="sidebar-sticky-wrapper">
                    <aside class="profile-sidebar p-3">
                        <div class="sidebar-header text-center mb-4">
                            <i class="fa-regular fa-circle-user sidebar-profile-icon"></i>
                            <h5 class="mt-2 mb-0">
                                <?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário'); ?>
                            </h5>
                            <small class="text-muted fs-6">
                                <?php echo htmlspecialchars(ucfirst($user_tipo)); ?>
                            </small>
                        </div>
                        
                        <nav class="nav nav-pills flex-column profile-nav">
                            
                            <a class="nav-link <?php echo ($pagina == 'perfil') ? 'active' : ''; ?>" 
                               href="perfil?page=perfil" 
                               <?php echo ($pagina == 'perfil') ? 'aria-current="page"' : ''; ?>>
                                <i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil
                            </a>
                            
                           <?php if ($user_tipo == 'admin'): ?>
                                <a class="nav-link <?php echo ($pagina == 'painel-admin') ? 'active' : ''; ?>" href="perfil?page=painel-admin" style="color: #dc3545;">
                                    <i class="fa-solid fa-gauge-high fa-fw me-2"></i> Painel Admin
                                </a>

                                <a class="nav-link <?php echo ($pagina == 'pet-analise') ? 'active' : ''; ?>" href="perfil?page=pet-analise">
                                    <i class="fa-solid fa-clipboard-list fa-fw me-2"></i> Pets a Analisar
                                    <?php if ($pagina == 'pet-analise' && isset($pets_analise) && count($pets_analise) > 0): ?>
                                    <?php endif; ?>
                                </a>

                            <?php else: ?>
                                <!-- ITENS DE USUÁRIO NORMAL -->
                                <a class="nav-link <?php echo ($pagina == 'meus-pets') ? 'active' : ''; ?>" href="perfil?page=meus-pets">
                                    <i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets
                                </a>
                                <a class="nav-link <?php echo ($pagina == 'pets-curtidos') ? 'active' : ''; ?>" href="perfil?page=pets-curtidos">
                                    <i class="fa-regular fa-heart fa-fw me-2"></i> Pets Curtidos
                                </a>
                                <a class="nav-link" href="chat.php">
                                    <i class="fa-regular fa-comments fa-fw me-2"></i> Chats
                                </a>
                            <?php endif; ?>
                            <a class="nav-link" href="notificacoes/">
                                <i class="fa-regular fa-bell fa-fw me-2"></i> Notificações
                            </a>
                            <?php if ($user_tipo !== 'admin'): ?>
                                <a class="nav-link" href="privacidade-conta/">
                                    <i class="fa-solid fa-shield-halved fa-fw me-2"></i> Privacidade
                                </a>
                            <?php endif; ?>
                            <hr class="my-2">
                            
                            <a class="nav-link logout-link-sidebar" href="sair.php">
                                <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Sair
                            </a>
                        </nav>
                    </aside>
                </div>
            </div>


            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-center mb-3">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <i class="fa-solid fa-trash"></i>
                    <h4 class="modal-title mt-3" id="confirmDeleteModalLabel">Confirmar Exclusão</h4>
                    Você tem certeza que deseja excluir este pet?
                    <br>
                    <strong style="color: var(--cor-vermelho);">Esta ação não pode ser desfeita.</strong>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Excluir</button>
                    
                </div>
            </div>
        </div>
    </div>

            
        </div>
    </div>

<!-- Modal de Seleção de Banner -->
<div class="modal fade" id="bannerModal" tabindex="-1" aria-labelledby="bannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" id="modalBanner">
                <button type="button" class="btn-close" onclick="refresh()" data-bs-dismiss="modal" aria-label="Close"></button>
                <h3 class="modal-title" id="bannerModalLabel">Escolha seu Banner de Perfil</h3>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php
                    $banners = ['banner1.jpg', 'banner2.jpg', 'banner3.jpg', 'banner4.jpg', 'banner5.jpg'];
                    // Busca o banner atual do banco de dados
                    $bannerAtual = $usuario['banner_fixo'] ?? 'banner1.jpg';
                    
                    foreach ($banners as $banner) {
                        $isActive = ($bannerAtual == $banner) ? 'active' : '';
                        echo "
                        <div class='col-6 col-md-4 mb-3'>
                            <div class='banner-option $isActive' data-banner='$banner'>
                                <img src='images/perfil/$banner' alt='Banner $banner' class='img-fluid rounded'>
                                " . ($isActive ? "<div class='badge mt-2' style='  background-color: var(--cor-verde-pastel) !important;'><i class='fa-solid fa-check me-1'></i>Em uso</div>" : "") . "
                            </div>
                        </div>";
                    }
                    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary salvar" id="saveBanner">Salvar Banner</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMAÇÃO GENÉRICO -->
            <div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirmação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            Tem certeza que deseja realizar esta exclusão? <br>
                            <strong class="text-danger">Isso não poderá ser desfeito.</strong>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" id="genericConfirmBtn" class="btn btn-danger">Sim, Excluir</button>
                        </div>
                    </div>
                </div>
            </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
    
    <script src="assets/js/pages/perfil/editar.js" type="module"></script>

    <script>

        function confirmarExclusao(acao, id) {
            const modalEl = document.getElementById('genericConfirmModal');
            const modal = new bootstrap.Modal(modalEl);
            const confirmButton = document.getElementById('genericConfirmBtn');
            confirmButton.dataset.acao = acao;
            confirmButton.dataset.id = String(id);
            modal.show();
        }

        async function alterarVerificacaoOng(acao, id) {
            try {
                const response = await fetch('admin-acoes.php', {
                    method: 'POST',
                    body: new URLSearchParams({ acao, id: String(id) })
                });
                if (!response.ok) throw new Error('Não foi possível atualizar a verificação.');
                window.location.reload();
            } catch (error) {
                showToast(error.message, 'danger');
            }
        }

        async function processarExclusaoConta(acao, id) {
            if (acao === 'aprovar_exclusao_conta'
                && !window.confirm('Esta ação excluirá permanentemente a conta e os dados relacionados. Continuar?')) return;
            try {
                const response = await fetch('admin-acoes.php', {
                    method: 'POST',
                    body: new URLSearchParams({ acao, id: String(id) })
                });
                if (!response.ok) throw new Error('Não foi possível processar a solicitação.');
                window.location.reload();
            } catch (error) {
                showToast(error.message, 'danger');
            }
        }

        document.getElementById('genericConfirmBtn')?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const body = new URLSearchParams({ acao: this.dataset.acao, id: this.dataset.id });
                const response = await fetch('admin-acoes.php', { method: 'POST', body });
                if (!response.ok) throw new Error('Não foi possível excluir o registro.');
                window.location.reload();
            } catch (error) {
                this.disabled = false;
                showToast(error.message, 'danger');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Só executa se estivermos na página 'meus-pets'
            <?php if ($pagina == 'meus-pets'): ?>
            
            const deleteModalElement = document.getElementById('confirmDeleteModal');
            
            // Verifica se o modal existe na página
            if (deleteModalElement) {
                const deleteModal = new bootstrap.Modal(deleteModalElement);
                const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
                const petsGrid = document.getElementById('petsGrid');

                // Usa "event delegation" para ouvir cliques nos botões de excluir
                if (petsGrid) {
                    petsGrid.addEventListener('click', function(event) {
                        // Encontra o botão de excluir mais próximo que foi clicado
                        const deleteButton = event.target.closest('.btn-excluir-pet');

                        if (deleteButton) {
                            // Previne a ação padrão do link (ir para a página)
                            event.preventDefault(); 
                            
                            const petId = deleteButton.dataset.petId;

                            if (confirmDeleteBtn) {
                                confirmDeleteBtn.dataset.petId = petId;
                            }
                            
                            // Abre o modal
                            deleteModal.show();
                        }
                    });
                }
            }
            
            <?php endif; ?>
        });

        document.getElementById('confirmDeleteBtn')?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const body = new URLSearchParams({ id: this.dataset.petId });
                const response = await fetch('excluir-pet.php', { method: 'POST', body });
                if (!response.ok) throw new Error('Não foi possível excluir o pet.');
                window.location.reload();
            } catch (error) {
                this.disabled = false;
                showToast(error.message, 'danger');
            }
        });
    </script>

  <script>
// Toast para TODAS as páginas (não apenas meus-pets)
<?php if (!empty($toast_message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const toastMessage = <?php echo json_encode($toast_message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const toastType = <?php echo json_encode($toast_type, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    showToast(toastMessage, toastType);
});
<?php endif; ?>

// Função Toast global corrigida
function showToast(message, type = 'success') {
    console.log('Chamando showToast:', message, type);
    
    const toast = document.getElementById('toast-notification');
    const toastIcon = document.getElementById('toast-icon');
    const toastMessage = document.getElementById('toast-message');
    
    if (!toast) {
        console.error('Elemento toast não encontrado');
        return;
    }
    if (!toastMessage) {
        console.error('Elemento toast-message não encontrado');
        return;
    }
    
    // Limpa classes anteriores
    toast.className = 'toast';
    if (toastIcon) toastIcon.className = 'toast-icon';
    
    // Configura mensagem e tipo
    toastMessage.textContent = message;
    toast.classList.add(type);
    
    // Configura ícone
    if (toastIcon) {
        toastIcon.className = 'toast-icon';
        if (type === 'success') {
            toastIcon.classList.add('fas', 'fa-check');
        } else if (type === 'danger') {
            toastIcon.classList.add('fas', 'fa-times');
        } else if (type === 'warning') {
            toastIcon.classList.add('fas', 'fa-exclamation-triangle');
        }
    }
    
    // Mostra o toast
    toast.style.display = 'flex';
    
    // Configura a barra de progresso
    const progressBar = toast.querySelector('.toast-progress-bar');
    if (progressBar) {
        progressBar.style.animation = 'none';
        void progressBar.offsetWidth; // Trigger reflow
        progressBar.style.animation = 'progress 3s linear forwards';
    }
    
    // Esconde após 3 segundos
    setTimeout(() => { 
        toast.style.display = 'none'; 
    }, 3100);
}

// Debug: verifica se a função está disponível globalmente
window.showToast = showToast;
console.log('Função showToast carregada');
</script>

     <script>
    document.addEventListener('DOMContentLoaded', function() {
        const petsGrid = document.getElementById('petsGrid');
        
        // Função Toast (precisa ser acessível por este script também)
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-notification');
            const toastIcon = document.getElementById('toast-icon');
            const toastMessage = document.getElementById('toast-message');
            if (!toast || !toastIcon || !toastMessage) return;
            toastMessage.textContent = message;
            toast.classList.remove('success', 'danger', 'warning');
            toastIcon.className = 'toast-icon';
            toast.classList.add(type);
            if (type === 'success') toastIcon.classList.add('fas', 'fa-check');
            else if (type === 'danger') toastIcon.classList.add('fas', 'fa-times');
            else if (type === 'warning') toastIcon.classList.add('fas', 'fa-exclamation-triangle');
            toast.style.display = 'block';
            const progressBar = toast.querySelector('.toast-progress-bar');
            progressBar.style.animation = 'none';
            void progressBar.offsetWidth;
            progressBar.style.animation = 'progress 3s linear forwards';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);
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
                       // showToast(result.message, 'danger');
                        
                        // NOVO: Remove o card da tela após descurtir
                        // (Apenas se estivermos na página 'pets-curtidos')
                        <?php if ($pagina == 'pets-curtidos'): ?>
                        setTimeout(() => {
                            iconElement.closest('.col').remove();
                            // Verifica se a grid ficou vazia
                            if (petsGrid.querySelectorAll('.col').length === 0) {
                                document.querySelector('.pets-grid-container').innerHTML = `

                        <h1 class="mb-4">Pets Curtidos</h1>

                        <div class="alert text-center flex-column d-flex justify-content align-items-center text-center" style="margin-top: -1.5rem; animation: fadeIn 0.8s ease-out;">
                                <lottie-player src="animações/dog-beijos.json" background="transparent" speed="1" style="width: 200px; height: 200px;"
                                loop autoplay>
                            </lottie-player>
                                    <h5 class="mb-1">Você ainda não curtiu nenhum amiguinho.</h5>
                                    <p>Que tal curtir seu novo amigo?</p>
                                    <a href="pets" class="btn btn-danger btn-add-pet-header" style="box-shadow: rgba(50, 50, 93, 0.25) 0px 13px 27px -5px, rgba(0, 0, 0, 0.3) 0px 8px 16px -8px;">Ver pets para adoção</a>
                                </div>

                                `;
                            }
                        }, 1000); // Espera 1s antes de remover
                        <?php endif; ?>
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
    });
    </script>

    <script>
        function refresh() {
             window.location.reload();
        }
    </script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bannerModal = document.getElementById('bannerModal');
    const saveBannerBtn = document.getElementById('saveBanner');
    let selectedBanner = '';

    // Função Toast global
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-notification');
        const toastIcon = document.getElementById('toast-icon');
        const toastMessage = document.getElementById('toast-message');
        if (!toast || !toastIcon || !toastMessage) return;
        toastMessage.textContent = message;
        toast.classList.remove('success', 'danger', 'warning');
        toastIcon.className = 'toast-icon';
        toast.classList.add(type);
        if (type === 'success') toastIcon.classList.add('fas', 'fa-check');
        else if (type === 'danger') toastIcon.classList.add('fas', 'fa-times');
        else if (type === 'warning') toastIcon.classList.add('fas', 'fa-exclamation-triangle');
        toast.style.display = 'block';
        const progressBar = toast.querySelector('.toast-progress-bar');
        progressBar.style.animation = 'none';
        void progressBar.offsetWidth;
        progressBar.style.animation = 'progress 3s linear forwards';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // Quando o modal é aberto, configura os eventos
    bannerModal.addEventListener('show.bs.modal', function() {
        const activeBanner = document.querySelector('.banner-option.active');
        if (activeBanner) {
            selectedBanner = activeBanner.dataset.banner;
        }

        // Adiciona evento de clique nas opções de banner
        document.querySelectorAll('.banner-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove a classe active de todas as opções
                document.querySelectorAll('.banner-option').forEach(opt => {
                    opt.classList.remove('active');
                    // Remove o badge "Em uso" ou "Selecionado"
                    const badge = opt.querySelector('.badge');
                    if (badge) badge.remove();
                });

                // Adiciona a classe active na opção clicada
                this.classList.add('active');
                
                // Adiciona badge "Selecionado"
                const selectedBadge = document.createElement('div');
                selectedBadge.className = 'badge mt-2';
                selectedBadge.style = '  background-color: var(--cor-laranja-pastel) !important;';
                selectedBadge.innerHTML = '<i class="fa-solid fa-check me-1"></i>Selecionado';
                this.appendChild(selectedBadge);

                selectedBanner = this.dataset.banner;
            });
        });
    });

    // Salvar banner selecionado
    saveBannerBtn.addEventListener('click', function() {
        if (!selectedBanner) {
            showToast('Por favor, selecione um banner.', 'warning');
            return;
        }

        // Desabilita o botão durante a requisição
        saveBannerBtn.disabled = true;
        saveBannerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';

        // Envia requisição para salvar o banner
        fetch('atualizar-banner.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `banner=${selectedBanner}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na rede: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
               // showToast('Banner atualizado com sucesso! A página será recarregada.', 'success');
                
                // Fecha o modal
                const modal = bootstrap.Modal.getInstance(bannerModal);
                modal.hide();
                
                // Recarrega a página após 1.5 segundos para mostrar o novo banner
                setTimeout(() => {
                    window.location.reload();
                }, 400);
                
            } else {
                showToast(data.message || 'Erro ao atualizar banner.', 'danger');
                // Reabilita o botão em caso de erro
                saveBannerBtn.disabled = false;
                saveBannerBtn.innerHTML = 'Salvar Banner';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de conexão. Tente novamente.', 'danger');
            // Reabilita o botão em caso de erro
            saveBannerBtn.disabled = false;
            saveBannerBtn.innerHTML = 'Salvar Banner';
        });
    });

    // Fecha o modal quando clica no X ou Cancelar
    bannerModal.addEventListener('hidden.bs.modal', function () {
        // Reseta o botão quando o modal é fechado
        saveBannerBtn.disabled = false;
        saveBannerBtn.innerHTML = 'Salvar Banner';
    });
});
</script>


</body>
</html>
