<?php
// Inclui a conexão e a sessão
include_once 'conexao.php';
include_once 'session.php';

// Verifica se há mensagens toast para exibir (para erros)
if (isset($_SESSION['toast_message'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('" . addslashes($_SESSION['toast_message']) . "', '" . ($_SESSION['toast_type'] ?? 'danger') . "');
        });
    </script>";
    // Limpa a mensagem da sessão após usar
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Protege a página: Somente usuários logados podem acessar
requerer_login();

// Pega IDs da sessão
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

// Pega o ID do pet da URL
$id_pet_para_editar = $_GET['id'] ?? null;
$pet = null;
$erro = '';
$pet_fotos = [];

if (empty($id_pet_para_editar)) {
    // Se não tiver ID, volta para 'meus-pets' com erro
    $_SESSION['toast_message'] = "ID do pet não fornecido.";
    $_SESSION['toast_type'] = 'danger';
    header('Location: perfil?page=meus-pets');
    exit;
}

// --- Busca os dados do Pet e VERIFICA A PERMISSÃO ---
try {
    $sql = "SELECT * FROM pet WHERE id_pet = :id_pet";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_pet' => $id_pet_para_editar]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        throw new Exception("Pet não encontrado.");
    }

    // VERIFICAÇÃO DE PROPRIEDADE
    $tem_permissao = false;
    if ($user_tipo == 'usuario' && $pet['id_usuario_fk'] == $user_id) {
        $tem_permissao = true;
    } elseif ($user_tipo == 'ong' && $pet['id_ong_fk'] == $user_id) {
        $tem_permissao = true;
    } elseif ($user_tipo == 'admin') {
        $tem_permissao = true;
    }

    if (!$tem_permissao) {
        throw new Exception("Você não tem permissão para editar este pet.");
    }

    $sql_fotos = "SELECT id_foto, caminho_foto FROM pet_fotos WHERE id_pet_fk = :id_pet ORDER BY id_foto ASC";
    $stmt_fotos = $conn->prepare($sql_fotos);
    $stmt_fotos->execute([':id_pet' => $id_pet_para_editar]);
    $pet_fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

    $pet_caracteristicas = json_decode($pet['caracteristicas'] ?? '[]', true);
    
    // Carregar alergias e medicação do banco
    $alergias = json_decode($pet['alergias'] ?? '[]', true);
    $medicacao = $pet['medicacao'] ?? '';

}  catch (Exception $e) {
    if ($e instanceof PDOException) {
        error_log('Erro ao carregar pet para edição: ' . $e->getMessage());
        $_SESSION['toast_message'] = 'Não foi possível carregar o pet. Tente novamente.';
    } else {
        $_SESSION['toast_message'] = $e->getMessage();
    }
    $_SESSION['toast_type'] = 'danger';
    if ($user_tipo == 'admin') {
        header('Location: perfil?page=painel-admin');
    } else {
        header('Location: perfil?page=meus-pets');
    }
    exit;
}

// Processar idade
$idade_display = $pet['idade'];
preg_match('/(\d+)\s*(ano|mes)/', $idade_display, $matches);
$idade_valor_edit = $matches[1] ?? '';
$idade_unidade_edit = isset($matches[2]) ? ($matches[2] == 'ano' ? 'anos' : 'meses') : 'anos';

// Lógica para controle de status baseado no status atual
$status_atual = $pet['status_disponibilidade'];
$status_options = [];

if ($status_atual == 'Em Analise') {
    $status_options = ['Em Analise' => 'Em Análise'];
} elseif ($status_atual == 'Disponivel') {
    $status_options = [
        'Disponivel' => 'Disponível',
        'Adotado' => 'Adotado', 
        'Indisponivel' => 'Indisponível'
    ];
} else {
    $status_options = [
        'Disponivel' => 'Disponível',
        'Adotado' => 'Adotado',
        'Indisponivel' => 'Indisponível'
    ];
}

// Se for admin, permite todas as opções
if ($user_tipo == 'admin') {
    $status_options = [
        'Em Analise' => 'Em Análise',
        'Disponivel' => 'Disponível',
        'Adotado' => 'Adotado',
        'Indisponivel' => 'Indisponível'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pet - Adote Patas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="assets/css/pages/autenticacao/autenticacao.css">
    <link rel="stylesheet" href="assets/css/pages/cadastro-pet/caracteristica.css">
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --cor-vermelho: #B46459;
            --cor-vermelho-claro: #d68a80;
            --cor-rosa-claro: #f8f0ef;
            --cor-rosa-escuro: #e8c4c0;
            --cor-branca: #ffffff;
            --cor-texto: #333333;
        }

        /* Estilos para os campos alinhados */
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .form-row-3 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* --- CORREÇÃO DA IDADE --- */
.age-input-group {
    display: grid;
    grid-template-columns: 1fr 120px;
    gap: 8px;
    align-items: start; /* Alterado de stretch para start para evitar distorção */
}

/* Trava a altura do input numérico */
.age-input-group input[type="number"] {
    height: 54px !important; /* Altura fixa padrão */
    max-height: 54px !important;
    max-width: 100px !important;
    box-sizing: border-box;
    border: 2px solid transparent;
}

/* Trava a altura do select customizado */
.age-unit-custom .custom-select-trigger {
    height: 54px !important;
    min-height: 54px  ;
    max-height: 54px ;
    display: flex;
    align-items: center;
    padding: 0 1.15rem; /* Padding lateral apenas */
    box-sizing: border-box;
    /* Borda igual ao input padrão */
    border: 2px solid transparent;
    margin: 0;
}

/* Garante que o hover/focus não mude o tamanho da borda */
.age-unit-custom .custom-select-trigger:focus,
.age-unit-custom .custom-select-trigger:hover {
    border-color: rgba(255, 255, 255, 0.6);
    outline: none;
    margin: 0; /* Previne margens extras */
}

@media (max-width: 768px) {
    .age-input-group {
        grid-template-columns: 1fr;
    }
}

        /* Estilo para mensagens de erro */
        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
        }

        .input-error {
            border-color: #dc2626 !important;
            background-color: #fef2f2 !important;
        }

        /* Estilo para o campo de arquivos com drag & drop */
        .file-drop-area {
            border: 2px dashed var(--cor-vermelho-claro);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            background: var(--cor-rosa-claro);
            cursor: pointer;
            position: relative;
        }

        .file-drop-area:hover, .file-drop-area.dragover {
            background: #fff0f0;
            border-color: var(--cor-vermelho);
            transform: translateY(-2px);
        }

        .file-drop-area i {
            font-size: 3rem;
            color: var(--cor-vermelho-claro);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .file-drop-area.dragover i {
            color: var(--cor-vermelho);
            transform: scale(1.1);
        }

        .file-info {
            font-weight: 600;
            color: var(--cor-texto);
            margin-bottom: 0.5rem;
        }

        .file-hint {
            color: #666;
            font-size: 0.9rem;
        }

        /* Campos dinâmicos para alergias e medicação */
        .dynamic-field {
            margin-top: 1rem;
            padding: 1.5rem;
            background: var(--cor-rosa-claro);
            border-radius: 12px;
        }

        .dynamic-field h4 {
            color: var(--cor-vermelho);
            margin-bottom: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alergia-input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            align-items: center;
        }

        .alergia-input {
            flex: 1;
        }

        .btn-add-alergia {
            background: var(--cor-vermelho);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-add-alergia:hover {
            background: var(--cor-vermelho-claro);
            transform: translateY(-1px);
        }

        .btn-remove-alergia {
            background: var(--cor-vermelho);
            color: white;
            border: none;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .btn-remove-alergia:hover {
            background: #b91c1c;
            transform: scale(1.1);
        }

        .medicacao-input {
            width: 100%;
        }

        /* Oculta os spinners nos inputs number */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        /* Estilos para selects customizados */
        .select-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .custom-select-wrapper {
            position: relative;
            width: 100%;
            font-family: 'Poppins', sans-serif; 
        }

        .custom-select-trigger {
            width: 100%;
            padding: 1.15rem;
            background-color: rgba(180, 100, 89, 0.55);
            border: 1px solid transparent;
            border-radius: 12px;
            color: var(--cor-branca);
            font-size: 1rem;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.2s ease, background-color 0.2s ease;
            -webkit-appearance: none; 
            -moz-appearance: none; 
            appearance: none; 
        }

        .custom-select-trigger:focus,
        .custom-select-trigger:hover {
            border-color:  rgba(255, 255, 255, 0.6);
            outline: none; 
        }
        
        .custom-select-value.placeholder {
            color: var(--cor-branca);
            background-color: transparent;
            opacity: 0.8;
        }

        .custom-select-arrow {
            width: 10px;
            height: 10px;
            border-right: 2px solid var(--cor-vermelho);
            border-bottom: 2px solid var(--cor-vermelho);
            transform: rotate(45deg);
            transition: transform 0.3s ease;
            pointer-events: none; 
        }

        .custom-select-trigger[aria-expanded="true"] .custom-select-arrow {
            transform: rotate(225deg);
            margin-top: 5px; 
        }

        .custom-select-options {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 10;
            background-color: var(--cor-rosa-claro);
            border-radius: 12px;
            border: 0.5px solid var(--cor-rosa-escuro); 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 8px;
            list-style: none;
            margin: 0;
            overflow-y: auto;
            max-height: 200px;
            display: none;
            -webkit-overflow-scrolling: touch;
        }

        .custom-option {
            padding: 10px 12px;
            color: var(--cor-vermelho); 
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .custom-option:hover,
        .custom-option:focus {
            background-color: var(--cor-rosa-escuro);
            opacity: 0.4;
            color: var(--cor-branca);
            outline: none;
        }

        .custom-option.selected {
            background-color: var(--cor-rosa-escuro); 
            color: var(--cor-branca);
            font-weight: 700;
        }

        /* Estilo customizado para o select de idade */
        .age-unit-custom {
            width: 100%;
        }

        .age-unit-custom .custom-select-trigger {
            height: 100%;
            min-height: 54px;
        }

        .tags-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .char-tag-input {
            background-color: #ffffff;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--cor-vermelho);
            border: 1px solid #eee;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        #openModalBtn {
            min-height: 60px;
            display: flex;
            align-items: flex-start;
            padding: 1.15rem;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tags-placeholder {
            color: var(--cor-branca) !important;
        }

        #openModalBtn:has(.char-tag-input) {
            color: inherit;
        }

        /* Estilos para preview de fotos */
        #fotos-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .foto-preview {
            position: relative;
            width: 100%;
            padding-top: 100%;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 2px solid #eee;
        }

        .foto-preview img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            color: var(--cor-vermelho);
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: all 0.2s ease;
        }

        .remove-preview:hover {
            background: var(--cor-vermelho);
            color: white;
            transform: scale(1.1);
        }

        /* Estilo para visualização de arquivo de vacinação */
        .vacina-view {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--cor-rosa-claro);
            border-radius: 8px;
            color: var(--cor-vermelho);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid var(--cor-rosa-escuro);
        }

        .vacina-view:hover {
            background: var(--cor-rosa-escuro);
            color: var(--cor-vermelho);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(180, 100, 89, 0.1);
        }

        /* Galeria de fotos atuais */
        .fotos-atuais-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .foto-atual-item {
            position: relative;
            width: 100%;
            padding-top: 100%;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .foto-atual-item img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .delete-foto-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 28px;
            height: 28px;
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--cor-vermelho);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            line-height: 1;
        }
        .delete-foto-btn:hover {
            background-color: var(--cor-vermelho);
            color: white;
            transform: scale(1.1);
        }
        .foto-atual-item input[type="checkbox"]:checked + img {
            opacity: 0.4;
            filter: grayscale(1);
        }
        .foto-atual-item input[type="checkbox"]:checked ~ .delete-foto-btn {
            background-color: var(--cor-vermelho);
            color: white;
            transform: rotate(45deg);
        }
        .delete-foto-btn::before {
            content: '\00D7';
        }

        /* Modal styles */
        .custom-icon-modal .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: visible;
            margin-top: 30px;
            margin-left: 5%;
            padding: 0;
        }

        .custom-icon-modal .modal-header {
            position: relative;
        }

        .custom-icon-modal .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
            color: var(--cor-branca);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            margin-top: -50px;
            margin-bottom: 10px;
            background-color: var(--cor-vermelho);
        }

        .custom-icon-modal .modal-header.warning-theme .icon-box {
            background: linear-gradient(135deg, var(--cor-vermelho-aviso), var(--cor-vermelho));
        }

        .custom-icon-modal .modal-title {
            color: var(--cor-cinza-texto);
            font-weight: 700;
        }

        .text-warning-dark {
            color:  var(--cor-vermelho);
        }

        #confirmSaveChanges{
            background: linear-gradient(135deg, var(--cor-vermelho-aviso), var(--cor-vermelho)) !important;
            border: none !important;
            outline: none !important;
        }

        .custom-icon-modal .btn {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .custom-icon-modal .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Estilos para a seção de vacinação */
#upload-vacina-area .file-drop-area {
    border: 2px dashed var(--cor-vermelho);
    background: var(--cor-rosa-claro);
}

#upload-vacina-area .file-drop-area:hover {
    border-color: var(--cor-vermelho-claro);
    background: #fff0f0;
}

/* Preview da carteirinha */
.vacina-preview {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
}

.vacina-preview.pdf {
    width: 150px;
    height: 200px;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed #d1d5db;
}

.vacina-preview.pdf i {
    font-size: 3rem;
    color: #dc2626;
    margin-bottom: 0.5rem;
}

.btn-remove-preview {
    background: var(--cor-vermelho);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 0.5rem;
}

.btn-remove-preview:hover {
    background: #b91c1c;
}

/* Ajuste para campo de cor quando vacinado */
.cor-full-width {
    grid-column: 1 / -1;
}
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

<div class="modal fade custom-icon-modal" id="analysisModal" tabindex="-1" aria-labelledby="analysisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 d-flex flex-column align-items-center pb-0 warning-theme">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="icon-box">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>

            <div class="modal-body fs-4 text-center">
                <h1 class="mb-2">
                    Você realizou alterações no cadastro do seu pet.
                </h1>
                <p class="mb-0 fs-6 text-muted">
                    Para garantir a segurança da adoção, seu pet irá para o status de <strong class="text-warning-dark">Em Análise</strong> após salvar.
                </p>
            </div>

            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning text-white px-4 rounded-pill fw-bold" id="confirmSaveChanges">
                    Confirmar e Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<div id="toast-notification" class="toast p-0" style="display: none;">
    <div id="toast-icon" class="toast-icon"></div>
    <div class="toast-content">
        <p id="toast-message" class="toast-message">Mensagem de exemplo.</p>
    </div>
    <div class="toast-progress-bar"></div>
</div>

<a href="perfil.php?page=meus-pets" class="btn-voltar" title="Voltar para Meus Pets">
    <i class="fa-solid fa-arrow-left"></i>
    <span>Voltar</span>
</a>

<img src="images/cadastro-login/pata.png" alt="Desenho de Pata" class="pata-fundo">

<div class="w-full max-w-2xl mx-auto">
    
    <div class="w-full flex items-center justify-between mb-6 relative">
        <div>
            <a href="./" title="Voltar para a página inicial">
                <img src="images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" width="70" height="70">
            </a>
        </div>
        <div class="absolute inset-x-0 text-center">
            <h1 class="text-xl md:text-4xl font-bold text-[#666662]">Editar Pet</h1>
            <div class="w-24 h-1 bg-[#666662] mx-auto mt-1 rounded-full"></div>
        </div>
        <div class="h-16 w-16 invisible"></div>
    </div>

    <div class="container-card w-full p-6 sm:p-10 rounded-3xl shadow-xl">
        <form action="atualizar-pet.php" method="post" enctype="multipart/form-data" id="form-edit-pet" class="space-y-6">
            
            <input type="hidden" name="id_pet" value="<?php echo $pet['id_pet']; ?>">

            <div class="grid gap-6">
                <div>
                    <label for="nome">Nome do Pet</label>
                    <input type="text" name="nome" id="nome" placeholder="Nome do Pet" required class="input-style w-full"
                           value="<?php echo htmlspecialchars($pet['nome']); ?>">
                </div>
            </div>

            <!-- NOVO LAYOUT: Idade, Espécie e Sexo na mesma linha -->
            <div class="form-row-3">
                <!-- Idade Aproximada -->
                <div>
                    <label>Idade Aproximada</label>
                    <div class="age-input-group">
                        <input 
                            type="number" 
                            name="idade_valor" 
                            placeholder="Ex: 2" 
                            required 
                            min="0" 
                            class="input-style age-value" 
                            value="<?php echo $idade_valor_edit; ?>"
                        >
                        <select name="idade_unidade" id="idade_unidade-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                            <option value="anos" <?= $idade_unidade_edit == 'anos' ? 'selected' : '' ?>>Anos</option>
                            <option value="meses" <?= $idade_unidade_edit == 'meses' ? 'selected' : '' ?>>Meses</option>
                        </select>

                        <div class="custom-select-wrapper age-unit-custom" data-target-select="idade_unidade-real">
                            <button type="button" class="custom-select-trigger input-style w-full" 
                                    aria-haspopup="listbox" 
                                    aria-expanded="false">
                                <span class="custom-select-value <?php echo empty($idade_unidade_edit) ? 'placeholder' : ''; ?>">
                                    <?php 
                                        if ($idade_unidade_edit == 'anos') echo 'Anos';
                                        elseif ($idade_unidade_edit == 'meses') echo 'Meses';
                                        else echo 'Unidade';
                                    ?>
                                </span>
                                <span class="custom-select-arrow"></span>
                            </button>
                            <ul class="custom-select-options" role="listbox">
                                <li class="custom-option" data-value="anos" role="option" tabindex="0">Anos</li>
                                <li class="custom-option" data-value="meses" role="option" tabindex="0">Meses</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Espécie -->
                <div>
                    <label id="select-label-especie">Espécie</label>
                    <select name="especie" id="especie-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="cachorro" <?php echo ($pet['especie'] == 'cachorro') ? 'selected' : ''; ?>>Cachorro</option>
                        <option value="gato" <?php echo ($pet['especie'] == 'gato') ? 'selected' : ''; ?>>Gato</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="especie-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-especie">
                            <span class="custom-select-value <?php echo empty($pet['especie']) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($pet['especie'] == 'cachorro') echo 'Cachorro';
                                    elseif ($pet['especie'] == 'gato') echo 'Gato';
                                    else echo 'Espécie';
                                ?>
                            </span>
                            <span class="custom-select-arrow"></span>
                        </button>
                        <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-especie">
                            <li class="custom-option" data-value="cachorro" role="option" tabindex="0">Cachorro</li>
                            <li class="custom-option" data-value="gato" role="option" tabindex="0">Gato</li>
                        </ul>
                    </div>
                </div>

                <!-- Sexo -->
                <div>
                    <label id="select-label-sexo">Sexo</label>
                    <select name="sexo" id="sexo-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="macho" <?php echo ($pet['sexo'] == 'macho') ? 'selected' : ''; ?>>Macho</option>
                        <option value="femea" <?php echo ($pet['sexo'] == 'femea') ? 'selected' : ''; ?>>Fêmea</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="sexo-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-sexo">
                            <span class="custom-select-value <?php echo empty($pet['sexo']) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($pet['sexo'] == 'macho') echo 'Macho';
                                    elseif ($pet['sexo'] == 'femea') echo 'Fêmea';
                                    else echo 'Gênero';
                                ?>
                            </span>
                            <span class="custom-select-arrow"></span>
                        </button>
                        <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-sexo">
                            <li class="custom-option" data-value="macho" role="option" tabindex="0">Macho</li>
                            <li class="custom-option" data-value="femea" role="option" tabindex="0">Fêmea</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Resto dos campos mantidos -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label id="select-label-porte">Porte</label>
                    <select name="porte" id="porte-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="pequeno" <?php echo ($pet['porte'] == 'pequeno') ? 'selected' : ''; ?>>Pequeno</option>
                        <option value="medio" <?php echo ($pet['porte'] == 'medio') ? 'selected' : ''; ?>>Médio</option>
                        <option value="grande" <?php echo ($pet['porte'] == 'grande') ? 'selected' : ''; ?>>Grande</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="porte-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-porte">
                            <span class="custom-select-value <?php echo empty($pet['porte']) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($pet['porte'] == 'pequeno') echo 'Pequeno';
                                    elseif ($pet['porte'] == 'medio') echo 'Médio';
                                    elseif ($pet['porte'] == 'grande') echo 'Grande';
                                    else echo 'Porte';
                                ?>
                            </span>
                            <span class="custom-select-arrow"></span>
                        </button>
                        <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-porte">
                            <li class="custom-option" data-value="pequeno" role="option" tabindex="0">Pequeno</li>
                            <li class="custom-option" data-value="medio" role="option" tabindex="0">Médio</li>
                            <li class="custom-option" data-value="grande" role="option" tabindex="0">Grande</li>
                        </ul>
                    </div>
                </div>
                <div>
                    <label for="raca">Raça (Ex: SRD)</label>
                    <input type="text" name="raca" id="raca" placeholder="Raça (Ex: SRD)" class="input-style w-full"
                           value="<?php echo htmlspecialchars($pet['raca']); ?>">
                </div>
            </div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="cor-vacinacao-container">
    <div>
        <label for="cor">Cor</label>
        <input type="text" name="cor" id="cor" placeholder="Cor (Ex: Caramelo)" class="input-style w-full"
               value="<?php echo htmlspecialchars($pet['cor']); ?>">
    </div>
    
    <!-- Campo Vacinado? -->
    <div id="vacinacao-field">
        <label id="select-label-vacinacao">Vacinado?</label>
        <?php if ($pet['status_vacinacao'] == 'sim'): ?>
            <!-- Se já for vacinado, mostra apenas "Sim" e desabilita -->
            <select name="status_vacinacao" id="status_vacinacao-real" required class="select-hidden" aria-hidden="true" tabindex="-1" disabled>
                <option value="sim" selected>Sim</option>
            </select>

            <div class="custom-select-wrapper" data-target-select="status_vacinacao-real">
                <button type="button" class="custom-select-trigger input-style w-full" disabled style="background-color: rgba(180, 100, 89, 0.35);">
                    <span class="custom-select-value">Sim</span>
                    <span class="custom-select-arrow" style="opacity: 0.5;"></span>
                </button>
                <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-vacinacao" style="display: none;">
                    <li class="custom-option selected" data-value="sim" role="option" tabindex="0">Sim</li>
                </ul>
            </div>
            
            <!-- Input hidden para garantir que o valor seja enviado -->
            <input type="hidden" name="status_vacinacao" value="sim">
        <?php else: ?>
            <!-- Se não for vacinado, permite escolher -->
            <select name="status_vacinacao" id="status_vacinacao-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                <option value="sim" <?php echo ($pet['status_vacinacao'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                <option value="nao" <?php echo ($pet['status_vacinacao'] == 'nao') ? 'selected' : ''; ?>>Não</option>
            </select>

            <div class="custom-select-wrapper" data-target-select="status_vacinacao-real">
                <button type="button" class="custom-select-trigger input-style w-full" 
                        aria-haspopup="listbox" 
                        aria-expanded="false" 
                        aria-labelledby="select-label-vacinacao">
                    <span class="custom-select-value <?php echo empty($pet['status_vacinacao']) ? 'placeholder' : ''; ?>">
                        <?php 
                            if ($pet['status_vacinacao'] == 'sim') echo 'Sim';
                            elseif ($pet['status_vacinacao'] == 'nao') echo 'Não';
                            else echo 'Vacinado?';
                        ?>
                    </span>
                    <span class="custom-select-arrow"></span>
                </button>
                <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-vacinacao">
                    <li class="custom-option" data-value="sim" role="option" tabindex="0">Sim</li>
                    <li class="custom-option" data-value="nao" role="option" tabindex="0">Não</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6"> 
                <div>
                    <label id="select-label-castracao">Castrado?</label>
                    <select name="status_castracao" id="status_castracao-real" required class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="sim" <?php echo ($pet['status_castracao'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="nao" <?php echo ($pet['status_castracao'] == 'nao') ? 'selected' : ''; ?>>Não</option>
                    </select>
                    
                    <div class="custom-select-wrapper" data-target-select="status_castracao-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-castracao">
                            <span class="custom-select-value <?php echo empty($pet['status_castracao']) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($pet['status_castracao'] == 'sim') echo 'Sim';
                                    elseif ($pet['status_castracao'] == 'nao') echo 'Não';
                                    else echo 'Castrado?';
                                ?>
                            </span>
                            <span class="custom-select-arrow"></span>
                        </button>
                        <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-castracao">
                            <li class="custom-option" data-value="sim" role="option" tabindex="0">Sim</li>
                            <li class="custom-option" data-value="nao" role="option" tabindex="0">Não</li>
                        </ul>
                    </div>
                </div>

                                <div>
                    <label id="select-label-disponibilidade">Status</label>
                    <select name="status_disponibilidade" id="status_disponibilidade-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($pet['status_disponibilidade'] == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="status_disponibilidade-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-disponibilidade">
                            <span class="custom-select-value">
                                <?php 
                                $status_display = $pet['status_disponibilidade'];
                                if ($status_display == 'Disponivel') {
                                    echo 'Disponível';
                                } else if ($status_display == 'Indisponivel') {
                                    echo 'Indisponível';
                                } else if ($status_display == 'Adotado') {
                                    echo 'Adotado';
                                } else if ($status_display == 'Em Analise') {
                                    echo 'Em Análise';
                                } else {
                                    echo htmlspecialchars(ucfirst($status_display));
                                }
                                ?>
                            </span>
                            <span class="custom-select-arrow"></span>
                        </button>
                        <ul class="custom-select-options" role="listbox" aria-labelledby="select-label-disponibilidade">
                            <?php foreach ($status_options as $value => $label): ?>
                                <li class="custom-option" data-value="<?php echo $value; ?>" role="option" tabindex="0">
                                    <?php echo $label; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>

            <hr class="border-gray-200 my-4">

            
<!-- SEÇÃO DE VACINAÇÃO MODIFICADA -->
<div class="grid gap-4 mt-2 grid-cols-1 md:grid-cols-1" id="vacinacao-section">
    <h3 class="text-lg font-bold text-gray-700">Documentação de Vacinação</h3>
    
    <!-- Área que aparece apenas quando o pet É VACINADO -->
    <div id="vacinado-content" style="display: <?php echo ($pet['status_vacinacao'] == 'sim') ? 'block' : 'none'; ?>;">
        <?php if (!empty($pet['carteira_vacinacao'])): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-3 mb-3">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-green-800">Carteirinha de vacinação enviada</p>
                        <div class="flex flex-wrap gap-3 mt-2">
                            <a href="<?php echo $pet['carteira_vacinacao']; ?>" target="_blank" class="vacina-view">
                                <i class="fas fa-eye"></i> Visualizar carteirinha atual
                            </a>
                            <button type="button" id="replace-vacina-btn" class="vacina-view bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-sync-alt"></i> Alterar carteirinha
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-book-medical fs-4"></i>
                    <p class="text-sm font-semibold text-yellow-800">Nenhuma carteirinha enviada. Adicione a carteirinha de vacinação.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Campo de upload da carteirinha (sempre visível quando vacinado) -->
        <div class="mt-4" id="upload-vacina-area" style="display: <?php echo empty($pet['carteira_vacinacao']) ? 'block' : 'none'; ?>;">
            <label class="font-semibold text-gray-700 mb-1 block">Carteirinha de Vacinação <span class="text-red-500">*</span></label>
            <div id="drop-area-vacina" class="file-drop-area">
                <i class="fas fa-file-medical"></i>
                <div class="file-info" id="file-name-vacina">Arraste e solte o arquivo aqui ou clique para selecionar</div>
                <div class="file-hint">Formatos aceitos: JPG, PNG, PDF, WEBP</div>
                <input type="file" name="carteira_vacinacao" id="file_vacina" class="hidden" accept="image/*,.pdf" <?php echo ($pet['status_vacinacao'] == 'sim' && empty($pet['carteira_vacinacao'])) ? 'required' : ''; ?>>
            </div>
            <p class="text-sm text-red-500 mt-1">Arquivo obrigatório para pets vacinados.</p>
        </div>
    </div>

    <!-- Mensagem quando NÃO É VACINADO -->
    <div id="nao-vacinado-content" style="display: <?php echo ($pet['status_vacinacao'] == 'nao') ? 'block' : 'none'; ?>;">
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-center">
            <i class="fas fa-info-circle text-blue-500 text-xl mb-2"></i>
            <p class="text-sm text-blue-800">O pet não é vacinado. As opções de carteirinha ficarão disponíveis quando marcar como vacinado.</p>
        </div>
    </div>
</div>

            <div>
                <label class="font-semibold text-gray-700">Gerenciar Fotos</label>
                <?php if (empty($pet_fotos)): ?>
                    <p class="text-sm text-gray-500">Nenhuma foto cadastrada. Adicione fotos abaixo.</p>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Marque as fotos que deseja <span class="font-bold text-red-600">excluir</span>:</p>
                    <div class="fotos-atuais-grid">
                        <?php foreach ($pet_fotos as $foto): ?>
                            <div class="foto-atual-item">
                                <input type="checkbox" name="fotos_para_excluir[]" 
                                        id="foto_<?php echo $foto['id_foto']; ?>" 
                                        value="<?php echo $foto['id_foto']; ?>"
                                        onchange="validarLimiteFotos()">
                                <img src="<?php echo htmlspecialchars($foto['caminho_foto']); ?>" alt="Foto atual do pet"
                                            onerror="this.src='images/perfil/teste.jpg';">
                                <label class="delete-foto-btn" 
                                        for="foto_<?php echo $foto['id_foto']; ?>" 
                                        title="Marcar para excluir"></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Área de Drag and Drop para novas fotos -->
                <div id="drop-area" class="file-drop-area mt-4">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div class="file-info" id="file-name-span">Arraste e solte fotos aqui ou clique para selecionar</div>
                    <div class="file-hint">Máximo: 5 fotos | Formatos: PNG, JPG, JPEG, WEBP</div>
                    <input type="file" id="fotos_novas_input" class="hidden" multiple accept="image/png, image/jpeg, image/jpg, image/webp">
                </div>
                
                <input type="file" name="fotos_novas[]" id="fotos_novas_final" class="hidden" multiple>

                <div id="fotos-preview-container" class="mt-4"></div>
                <small id="limite-fotos-helper" class="text-sm text-gray-600 mt-1"></small>
            </div>

       <!-- CAMPOS DINÂMICOS PARA CARACTERÍSTICAS ESPECIAIS -->
<div id="dynamic-fields-container">
    <!-- Alergias (aparece quando selecionar característica "Alergia") -->
    <div id="alergia-field" class="dynamic-field" style="display: <?php echo (in_array('Alergia', $pet_caracteristicas)) ? 'block' : 'none'; ?>;">
        <h4><i class="fas fa-allergies"></i> Alergias do Pet</h4>
        <div id="alergias-container">
            <!-- Inputs de alergia serão adicionados aqui dinamicamente -->
        </div>
        <button type="button" id="add-alergia-btn" class="btn-add-alergia">
            <i class="fas fa-plus"></i> Adicionar Alergia
        </button>
    </div>

    <!-- Medicação (aparece quando selecionar característica "Medicação") -->
    <div id="medicacao-field" class="dynamic-field" style="display: <?php echo (in_array('Medicação', $pet_caracteristicas)) ? 'block' : 'none'; ?>;">
        <h4><i class="fas fa-pills"></i> Medicação do Pet</h4>
        <input type="text" name="medicacao" id="medicacao_input" placeholder="Nome da medicação que o pet toma" 
               class="input-style medicacao-input" value="<?php echo htmlspecialchars($medicacao); ?>">
    </div>
</div>

            <hr class="border-gray-200 my-4">
                
            <div>
                <label for="openModalBtn" class="sr-only">Características</label>
                <button type="button" id="openModalBtn" class="input-style w-full text-left">
                    <span id="tagsPlaceholder" class="tags-placeholder">Selecionar Características...</span>
                    <span class="tags-preview" id="tagsPreview"></span>
                </button>
            </div>

            <div id="hidden-tags-container"></div>

            <div>
                <label class="sr-only" for="comportamento">Comportamento (Ex: Dócil, adora crianças...)</label>
                <input type="text" name="comportamento" id="comportamento" placeholder="Conte um pouco sobre o pet..." class="input-style w-full" value="<?php echo htmlspecialchars($pet['comportamento']); ?>">
            </div>

            <div class="flex justify-center w-55 mx-auto">
                <button type="submit" class="adopt-btn" id="submit-btn"> 
                    <div class="heart-background" aria-hidden="true">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <span>Salvar Alterações</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Características -->
<div id="charModal" class="char-modal">
    <div class="char-modal-content">
        <div class="char-modal-header">
            <div>
                <h2>Selecionar Características</h2>
                <p>Escolha as características para o seu pet.</p>
            </div>
            <button type="button" class="char-modal-close" id="closeModalBtn">&times;</button>
        </div>

        <div class="char-modal-body">
            <h3>Temperamento</h3>
            <div class="char-tags-container" data-category="unlimited">
                <span class="char-tag" data-color="laranja" data-value="Dócil"><i class="fa-solid fa-face-smile"></i> Dócil <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Brincalhão"><i class="fa-solid fa-puzzle-piece"></i> Brincalhão <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Calmo"><i class="fa-solid fa-leaf"></i> Calmo <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Carinhoso"><i class="fa-solid fa-heart"></i> Carinhoso <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Tímido"><i class="fa-solid fa-user-secret"></i> Tímido <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Sociável"><i class="fa-solid fa-users"></i> Sociável <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Protetor"><i class="fa-solid fa-shield-halved"></i> Protetor <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Curioso"><i class="fa-solid fa-magnifying-glass"></i> Curioso <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Medroso"><i class="fa-solid fa-ghost"></i> Medroso <i class="fas fa-check"></i></span>
            </div>

            <h3>Nível de Energia (Max 1)</h3>
            <div class="char-tags-container" data-category="single-energy">
                <span class="char-tag" data-color="verde" data-value="Baixa Energia"><i class="fa-solid fa-battery-quarter"></i> Baixa Energia <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Média Energia"><i class="fa-solid fa-battery-half"></i> Média Energia <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Alta Energia"><i class="fa-solid fa-battery-full"></i> Alta Energia <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Hiperativo"><i class="fa-solid fa-bolt"></i> Hiperativo <i class="fas fa-check"></i></span>
            </div>

            <h3>Sociabilidade</h3>
            <div class="char-tags-container" data-category="unlimited">
                <span class="char-tag" data-color="rosa" data-value="Com Crianças"><i class="fa-solid fa-child"></i> Com Crianças <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Com Cães"><i class="fa-solid fa-dog"></i> Com Cães <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Com Gatos"><i class="fa-solid fa-cat"></i> Com Gatos <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Com Estranhos"><i class="fa-solid fa-user-group"></i> Com Estranhos <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Pet Único"><i class="fa-solid fa-user"></i> Pet Único <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Com Idosos"><i class="fa-solid fa-person-cane"></i> Com Idosos <i class="fas fa-check"></i></span>
            </div>

            <h3>Cuidados Especiais</h3>
            <div class="char-tags-container" data-category="unlimited">
                <span class="char-tag" data-color="roxo" data-value="Medicação"><i class="fa-solid fa-pills"></i> Medicação <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Dieta Especial"><i class="fa-solid fa-bowl-food"></i> Dieta Especial <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Alergia"><i class="fa-solid fa-allergies"></i> Alergia <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Deficiência Física"><i class="fa-solid fa-wheelchair"></i> Def. Física <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Deficiência Visual"><i class="fa-solid fa-eye-slash"></i> Def. Visual <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Deficiência Auditiva"><i class="fa-solid fa-ear-listen-slash"></i> Def. Auditiva <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="Pós-operatório"><i class="fa-solid fa-kit-medical"></i> Pós-operatório <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Traumático"><i class="fa-solid fa-heart-crack"></i> Traumático <i class="fas fa-check"></i></span>
            </div>

            <h3>Treinamento e Hábitos (Max 1)</h3>
            <div class="char-tags-container" data-category="single-training">
                <span class="char-tag" data-color="verde" data-value="Adestrado"><i class="fa-solid fa-graduation-cap"></i> Adestrado <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Em Treinamento"><i class="fa-solid fa-person-chalkboard"></i> Em Treinamento <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="roxo" data-value="Não Adestrado"><i class="fa-solid fa-xmark"></i> Não Adestrado <i class="fas fa-check"></i></span>
            </div>

            <h3>Ambiente Ideal</h3>
            <div class="char-tags-container" data-category="unlimited">
                <span class="char-tag" data-color="roxo" data-value="Apartamento"><i class="fa-solid fa-building"></i> Apartamento <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="verde" data-value="Precisa de Quintal"><i class="fa-solid fa-tree"></i> Precisa de Quintal <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="laranja" data-value="Casa"><i class="fa-solid fa-house"></i> Casa <i class="fas fa-check"></i></span>
                <span class="char-tag" data-color="rosa" data-value="1ª Adoção"><i class="fa-solid fa-star"></i> 1ª Adoção <i class="fas fa-check"></i></span>
            </div>
        </div>
        
        <div class="char-modal-footer">
            <button type="button" class="btn btn-cancelar" id="cancelModalBtn">Cancelar</button>
            <button type="button" class="btn btn-salvar" id="saveModalBtn">Salvar Seleção (0/5)</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
<script src="assets/js/pages/autenticacao/autenticacao.js" type="module"></script>

<script>
// Variáveis globais para controle de fotos
const totalFotosAtuais = <?php echo count($pet_fotos); ?>;
const MAX_FOTOS_GLOBAL = 5;

// Verificar se houve mudanças no formulário
function checkFormChanges() {
    const form = document.getElementById('form-edit-pet');
    const submitBtn = document.getElementById('submit-btn');
    
    if (!form || !submitBtn) return;
    
    // Flag para controlar se deve mostrar alerta
    window.formHasChanges = false;
    let formSubmitted = false;

    // Monitorar mudanças em todos os campos
    form.addEventListener('change', function() {
        if (!formSubmitted) {
            window.formHasChanges = true;
        }
    });
    
    // Monitorar input em campos de texto
    const textInputs = form.querySelectorAll('input[type="text"], input[type="number"], textarea');
    textInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (!formSubmitted) {
                window.formHasChanges = true;
            }
        });
    });
    
    // Interceptar o envio do formulário
    form.addEventListener('submit', function(e) {
        // Se for admin, envia diretamente sem modal
        const isAdmin = <?php echo $user_tipo === 'admin' ? 'true' : 'false'; ?>;
        
        if (isAdmin) {
            return; // Admin pode enviar diretamente
        }
        
        // Se não há mudanças, envia diretamente
        if (!window.formHasChanges) {
            return;
        }
        
        // Se há mudanças e não é admin, mostra o modal
        e.preventDefault();
        
        const analysisModal = new bootstrap.Modal(document.getElementById('analysisModal'));
        analysisModal.show();
    });
    
    // Configurar o botão de confirmação do modal
    document.getElementById('confirmSaveChanges').addEventListener('click', function() {
        formSubmitted = true;
        document.getElementById('form-edit-pet').submit();
    });
}

// Mostrar campos dinâmicos baseado nas características existentes
function showDynamicFieldsBasedOnCharacteristics() {
    const hasAlergia = <?php echo (in_array('Alergia', $pet_caracteristicas)) ? 'true' : 'false'; ?>;
    const hasMedicacao = <?php echo (in_array('Medicação', $pet_caracteristicas)) ? 'true' : 'false'; ?>;
    
    if (hasAlergia) {
        document.getElementById('alergia-field').style.display = 'block';
    }
    
    if (hasMedicacao) {
        document.getElementById('medicacao-field').style.display = 'block';
    }
}

// Chamar a função quando a página carregar
document.addEventListener('DOMContentLoaded', showDynamicFieldsBasedOnCharacteristics);

// Inicializar a verificação de mudanças
document.addEventListener('DOMContentLoaded', checkFormChanges);

// VALIDAÇÃO DA IDADE (DINÂMICA)
document.addEventListener('DOMContentLoaded', function() {
    const idadeValorInput = document.querySelector('input[name="idade_valor"]');
    const idadeUnidadeSelect = document.getElementById('idade_unidade-real');
    
    if (!idadeValorInput || !idadeUnidadeSelect) return;

    function checkAgeLimit() {
        // Se for MESES, aplica o limite de 11
        if (idadeUnidadeSelect.value === 'meses') {
            idadeValorInput.setAttribute('max', '11');
            
            // Se o usuário já tinha digitado algo maior que 11, corrige e avisa
            if (idadeValorInput.value && parseInt(idadeValorInput.value) > 11) {
                idadeValorInput.value = 11;
                if (typeof showToast === 'function') {
                    showToast('A idade em meses não pode ser maior que 11.', 'warning');
                }
            }
        } else {
            // Se for ANOS, remove o limite máximo (libera 15, 20, etc)
            idadeValorInput.removeAttribute('max');
        }
    }

    // Ouve quando digita o número
    idadeValorInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, ''); // Garante apenas números
        checkAgeLimit();
    });
    
    // Ouve quando muda o select (Anos <-> Meses)
    idadeUnidadeSelect.addEventListener('change', checkAgeLimit);
    
    // Roda uma vez ao carregar a página para garantir o estado certo
    checkAgeLimit();
});

// DRAG & DROP UNIFICADO PARA CARTEIRINHA DE VACINAÇÃO
document.addEventListener('DOMContentLoaded', function() {
    const dropAreaVacina = document.getElementById('drop-area-vacina');
    const fileInputVacina = document.getElementById('file_vacina');
    const fileNameVacina = document.getElementById('file-name-vacina');
    // const previewVacina = document.getElementById('preview-vacina'); // Se não usar preview de texto, pode comentar

    if (!dropAreaVacina || !fileInputVacina) return;

    // Prevenir comportamentos padrão
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Efeitos visuais
    ['dragenter', 'dragover'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropAreaVacina.classList.add('dragover');
    }

    function unhighlight() {
        dropAreaVacina.classList.remove('dragover');
    }

    // Manipular arquivos dropados
    dropAreaVacina.addEventListener('drop', handleDropVacina, false);

    function handleDropVacina(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFilesVacina(files);
    }

    // Manipular seleção via clique (AQUI ESTAVA O PROBLEMA: SÓ PRECISA DE UM DESSES)
    dropAreaVacina.addEventListener('click', () => {
        fileInputVacina.click();
    });

    fileInputVacina.addEventListener('change', function() {
        handleFilesVacina(this.files);
    });

    function handleFilesVacina(files) {
        if (files.length) {
            const file = files[0];
            
            // 1. Validação do tipo de arquivo
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                if (typeof showToast === 'function') {
                    showToast('Formato inválido. Use JPG, PNG, WEBP ou PDF.', 'danger');
                }
                return;
            }
            
            // 2. Validação de Tamanho (5MB) - Trouxemos do segundo bloco
            if (file.size > 5 * 1024 * 1024) {
                if (typeof showToast === 'function') {
                    showToast('Arquivo muito grande. Tamanho máximo: 5MB.', 'danger');
                }
                return;
            }
            
            // Atualiza a interface
            if(fileNameVacina) {
                fileNameVacina.textContent = file.name;
                fileNameVacina.style.color = 'green';
            }
            
            // Atualiza o input file
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInputVacina.files = dataTransfer.files;

            // Avisa o sistema que houve mudança no formulário
            if (typeof window.formHasChanges !== 'undefined') {
                window.formHasChanges = true;
            }
        }
    }
});

// CONTROLE DA VACINAÇÃO
document.addEventListener('DOMContentLoaded', function() {
    const statusVacinacaoReal = document.getElementById('status_vacinacao-real');
    const vacinadoContent = document.getElementById('vacinado-content');
    const naoVacinadoContent = document.getElementById('nao-vacinado-content');
    const fileInputVacina = document.getElementById('file_vacina');
    const replaceVacinaBtn = document.getElementById('replace-vacina-btn');
    const uploadVacinaArea = document.getElementById('upload-vacina-area');

    // Função para toggle da seção de vacinação
    function toggleVacinacaoSection() {
        const isVacinado = statusVacinacaoReal ? statusVacinacaoReal.value === 'sim' : <?php echo $pet['status_vacinacao'] == 'sim' ? 'true' : 'false'; ?>;

        // Mostra/oculta conteúdos
        if (vacinadoContent) vacinadoContent.style.display = isVacinado ? 'block' : 'none';
        if (naoVacinadoContent) naoVacinadoContent.style.display = isVacinado ? 'none' : 'block';

        // Se for vacinado e não tem carteirinha, torna o campo obrigatório
        if (fileInputVacina) {
            if (isVacinado && !<?php echo !empty($pet['carteira_vacinacao']) ? 'true' : 'false'; ?>) {
                fileInputVacina.required = true;
            } else {
                fileInputVacina.required = false;
            }
        }
    }

    // Botão para substituir carteirinha
    if (replaceVacinaBtn && uploadVacinaArea) {
        replaceVacinaBtn.addEventListener('click', function() {
            uploadVacinaArea.style.display = 'block';
            this.style.display = 'none';
            if (fileInputVacina) {
                fileInputVacina.required = true;
            }
        });
    }

    // Inicializar estado
    toggleVacinacaoSection();

    // Observar mudanças no select de vacinação (apenas se não estiver desabilitado)
    if (statusVacinacaoReal && !statusVacinacaoReal.disabled) {
        statusVacinacaoReal.addEventListener('change', function() {
            toggleVacinacaoSection();
            
            // Se mudou para "Sim", torna o campo obrigatório
            if (fileInputVacina && this.value === 'sim') {
                fileInputVacina.required = true;
            }
            
            // Disparar evento de mudança no formulário
            if (typeof window.formHasChanges !== 'undefined') {
                window.formHasChanges = true;
            }
        });
    }

    // Validação do formulário para carteirinha obrigatória
    const form = document.getElementById('form-edit-pet');
    if (form) {
        form.addEventListener('submit', function(e) {
            const isVacinado = statusVacinacaoReal ? statusVacinacaoReal.value === 'sim' : <?php echo $pet['status_vacinacao'] == 'sim' ? 'true' : 'false'; ?>;
            const hasCarteirinha = <?php echo !empty($pet['carteira_vacinacao']) ? 'true' : 'false'; ?>;
            const fileSelected = fileInputVacina && fileInputVacina.files.length > 0;
            
            // Se é vacinado, não tem carteirinha atual e não selecionou novo arquivo
            if (isVacinado && !hasCarteirinha && !fileSelected) {
                e.preventDefault();
                if (typeof showToast === 'function') {
                    showToast('Por favor, envie a carteirinha de vacinação, pois o pet é vacinado.', 'danger');
                }
                // Rola até a seção de vacinação
                document.getElementById('vacinacao-section').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    }
});

// CAMPOS DINÂMICOS PARA ALERGIAS E MEDICAÇÃO
document.addEventListener('DOMContentLoaded', function() {
    const alergiaField = document.getElementById('alergia-field');
    const medicacaoField = document.getElementById('medicacao-field');
    const alergiasContainer = document.getElementById('alergias-container');
    const addAlergiaBtn = document.getElementById('add-alergia-btn');
    
    // Preencher alergias existentes do PHP
    const alergiasExistentes = <?php echo json_encode($alergias); ?>;
    if (alergiasExistentes.length > 0 && alergiasExistentes[0] !== '') {
        alergiasExistentes.forEach((alergia, index) => {
            if (alergia.trim() !== '') {
                addAlergiaInput(alergia);
            }
        });
    } else {
        // Adiciona um input vazio por padrão
        addAlergiaInput('');
    }
    
    // Função para adicionar input de alergia
    function addAlergiaInput(value = '') {
        const inputGroup = document.createElement('div');
        inputGroup.className = 'alergia-input-group';
        
        const inputId = `alergia_${Date.now()}_${alergiasContainer.children.length}`;
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'alergias[]';
        input.value = value;
        input.placeholder = 'Nome da alergia';
        input.className = 'input-style alergia-input';
        input.id = inputId;
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn-remove-alergia';
        removeButton.disabled = alergiasContainer.children.length === 0;
        const icon = document.createElement('i');
        icon.className = 'fas fa-times';
        removeButton.appendChild(icon);
        inputGroup.append(input, removeButton);
        
        alergiasContainer.appendChild(inputGroup);
        
        // Atualiza estado dos botões de remover
        updateRemoveButtons();
        
        // Foca no novo input
        document.getElementById(inputId).focus();
    }
    
    // Atualizar estado dos botões de remover
    function updateRemoveButtons() {
        const removeButtons = alergiasContainer.querySelectorAll('.btn-remove-alergia');
        removeButtons.forEach((btn, index) => {
            btn.disabled = (index === 0 && removeButtons.length === 1);
        });
    }
    
    // Event listener para adicionar alergia
    addAlergiaBtn.addEventListener('click', () => {
        addAlergiaInput();
    });
    
    // Event listener para remover alergia (delegação de evento)
    alergiasContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-alergia')) {
            const inputGroup = e.target.closest('.alergia-input-group');
            if (inputGroup && alergiasContainer.children.length > 1) {
                inputGroup.remove();
                updateRemoveButtons();
            }
        }
    });
    
    // Mostrar/ocultar campos baseado nas características selecionadas
    function toggleDynamicFields(selectedTags) {
        const hasAlergia = selectedTags.some(tag => tag.value === 'Alergia');
        const hasMedicacao = selectedTags.some(tag => tag.value === 'Medicação');
        
        // Alergia
        if (hasAlergia) {
            alergiaField.style.display = 'block';
        } else {
            alergiaField.style.display = 'none';
            // Limpa os inputs de alergia, mas mantém um vazio
            alergiasContainer.innerHTML = '';
            addAlergiaInput('');
        }
        
        // Medicação
        if (hasMedicacao) {
            medicacaoField.style.display = 'block';
        } else {
            medicacaoField.style.display = 'none';
            document.getElementById('medicacao_input').value = '';
        }
    }
    
    // Expor a função globalmente para o modal de características
    window.toggleDynamicFields = toggleDynamicFields;
});

// Implementação do Drag and Drop para Fotos
document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('fotos_novas_input');
    const finalInput = document.getElementById('fotos_novas_final');
    const fileNameSpan = document.getElementById('file-name-span');
    const previewContainer = document.getElementById('fotos-preview-container');

    // Prevenir comportamentos padrão
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Efeitos visuais
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropArea.classList.add('dragover');
    }

    function unhighlight() {
        dropArea.classList.remove('dragover');
    }

    // Manipular arquivos dropados
    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    // Manipular seleção via clique
    dropArea.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    // Função principal para processar arquivos
    async function handleFiles(files) {
        if (!files.length) return;

        // Validação preliminar
        const totalPreliminar = files.length;

        if (totalPreliminar > MAX_FOTOS_GLOBAL) {
            if (typeof showToast === 'function') {
                showToast(`Limite de ${MAX_FOTOS_GLOBAL} fotos excedido!`, 'danger');
            }
            return;
        }

        fileNameSpan.textContent = 'Processando...';

        try {
            const conversionPromises = Array.from(files).map(file => {
                // Mostra preview imediato
                const reader = new FileReader();
                reader.onload = (e) => {
                    addImagePreview(e.target.result, file.name);
                };
                reader.readAsDataURL(file);

                // Converte para WebP
                return convertToWebP(file);
            });

            const convertedFiles = await Promise.all(conversionPromises);
            updateFinalInput(convertedFiles, finalInput);
            
            fileNameSpan.textContent = `${convertedFiles.length} foto(s) adicionada(s)`;
            validarLimiteFotos();

        } catch (error) {
            console.error("Erro ao processar imagens:", error);
            fileNameSpan.textContent = 'Erro no processamento. Tente novamente.';
            if (typeof showToast === 'function') {
                showToast('Erro ao processar imagens.', 'danger');
            }
        }
    }

    // Adicionar preview da imagem
    function addImagePreview(src, filename) {
        const previewDiv = document.createElement('div');
        previewDiv.className = 'foto-preview';
        
        const img = document.createElement('img');
        img.src = src;
        img.alt = `Preview: ${filename}`;
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-preview';
        removeBtn.innerHTML = '×';
        removeBtn.title = 'Remover foto';
        
        removeBtn.addEventListener('click', function() {
            previewDiv.remove();
            // Recria a lista de arquivos no input final
            updateFinalInputFromPreviews();
            validarLimiteFotos();
        });
        
        previewDiv.appendChild(img);
        previewDiv.appendChild(removeBtn);
        previewContainer.appendChild(previewDiv);
    }

    // Atualizar input final baseado nos previews
    function updateFinalInputFromPreviews() {
        // Limpa o input final
        const dataTransfer = new DataTransfer();
        finalInput.files = dataTransfer.files;
        
        // Re-adiciona os arquivos que ainda estão no preview
        fileNameSpan.textContent = 'Arraste e solte fotos aqui ou clique para selecionar';
        validarLimiteFotos();
    }
});

// Função para converter para WebP
async function convertToWebP(file) {
    return new Promise((resolve, reject) => {
        if (file.type === 'image/webp') {
            resolve(file);
            return;
        }
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                canvas.toBlob(function(blob) {
                    const webpFileName = file.name.split('.').slice(0, -1).join('.') + '.webp';
                    const webpFile = new File([blob], webpFileName, { type: 'image/webp' });
                    resolve(webpFile);
                }, 'image/webp', 0.8);
            };
            img.onerror = reject;
            img.src = event.target.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

// Função para atualizar input final
function updateFinalInput(files, finalInput) {
    const dataTransfer = new DataTransfer();
    files.forEach(file => {
        dataTransfer.items.add(file);
    });
    finalInput.files = dataTransfer.files;
}

// Função de validação de limite de fotos
function validarLimiteFotos() {
    const fotosMarcadasParaExcluir = document.querySelectorAll('input[name="fotos_para_excluir[]"]:checked').length;
    const fotosNovas = document.getElementById('fotos_novas_final').files.length;
    
    const fotosAtuaisRestantes = totalFotosAtuais - fotosMarcadasParaExcluir;
    const totalFinal = fotosAtuaisRestantes + fotosNovas;

    const limiteHelper = document.getElementById('limite-fotos-helper');
    const submitBtn = document.getElementById('submit-btn');

    if (totalFinal > MAX_FOTOS_GLOBAL) {
        limiteHelper.textContent = `Erro: Limite de ${MAX_FOTOS_GLOBAL} fotos excedido! (Total: ${totalFinal})`;
        limiteHelper.style.color = 'var(--cor-vermelho)';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        return false;
    } else if (totalFinal === 0) {
        limiteHelper.textContent = `Erro: O pet deve ter pelo menos 1 foto.`;
        limiteHelper.style.color = 'var(--cor-vermelho)';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        return false;
    } else {
        const espacoRestante = MAX_FOTOS_GLOBAL - fotosAtuaisRestantes;
        limiteHelper.textContent = `Você pode adicionar mais ${espacoRestante} foto(s). (Total será ${totalFinal}/${MAX_FOTOS_GLOBAL})`;
        limiteHelper.style.color = '#555';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        return true;
    }
}

// Inicializar a validação de fotos
document.addEventListener('DOMContentLoaded', validarLimiteFotos);

// Adiciona listeners para os checkboxes existentes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="fotos_para_excluir[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', validarLimiteFotos);
    });
});

// Custom Select Functionality
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.custom-select-wrapper').forEach(setupCustomSelect);
});

function setupCustomSelect(wrapper) {
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const optionsList = wrapper.querySelector('.custom-select-options');
    const options = wrapper.querySelectorAll('.custom-option');
    const valueSpan = trigger.querySelector('.custom-select-value');
    
    // Pega o <select> real escondido usando o data-target-select
    const targetSelectId = wrapper.dataset.targetSelect;
    const realSelect = document.getElementById(targetSelectId);

    // Encontra a opção selecionada no select real e a marca no customizado
    if (realSelect) {
        const initialSelectedValue = realSelect.value;
        options.forEach(option => {
            if (option.dataset.value === initialSelectedValue) {
                option.classList.add('selected');
                valueSpan.textContent = option.textContent;
            }
        });
    }

    // 1. Abrir/Fechar o menu
    trigger.addEventListener('click', () => {
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        // Fecha outros selects abertos
        document.querySelectorAll('.custom-select-trigger[aria-expanded="true"]').forEach(otherTrigger => {
            if (otherTrigger !== trigger) {
                otherTrigger.setAttribute('aria-expanded', 'false');
                otherTrigger.nextElementSibling.style.display = 'none';
            }
        });

        trigger.setAttribute('aria-expanded', !isExpanded);
        optionsList.style.display = isExpanded ? 'none' : 'block';
    });

    // 2. Selecionar uma opção
    options.forEach(option => {
        option.addEventListener('click', () => {
            // Remove a classe 'selected' de todos
            options.forEach(o => o.classList.remove('selected'));
            
            // Adiciona 'selected' na clicada
            option.classList.add('selected');
            
            // Atualiza o valor visual no "trigger"
            valueSpan.textContent = option.textContent;
            valueSpan.classList.remove('placeholder');
            
            // ATUALIZA O VALOR NO <select> ESCONDIDO
            if (realSelect) {
                realSelect.value = option.dataset.value;
                // Dispara um evento 'change' no select real
                const event = new Event('change');
                realSelect.dispatchEvent(event);
            }
            
            // Fecha o menu
            trigger.setAttribute('aria-expanded', 'false');
            optionsList.style.display = 'none';
        });
    });

    // 3. Fechar ao clicar fora
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            trigger.setAttribute('aria-expanded', 'false');
            optionsList.style.display = 'none';
        }
    });
}
</script>

<script type="module">
    // Pega os elementos do DOM
    const modal = document.getElementById('charModal');
    const openBtn = document.getElementById('openModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelModalBtn');
    const saveBtn = document.getElementById('saveModalBtn');
    const allTagsInModal = document.querySelectorAll('.char-tag');
    const hiddenTagsContainer = document.getElementById('hidden-tags-container');
    const tagsPreview = document.getElementById('tagsPreview');
    const tagsPlaceholder = document.getElementById('tagsPlaceholder');
    
    // Pega características existentes do PHP
    const existingCharacteristics = <?php echo json_encode($pet_caracteristicas); ?>;
    
    // Array que guarda as seleções
    let selectedTags = [];

    // --- Funções do Modal ---
    function openModal() {
        if (modal) modal.style.display = 'flex';
        // Recarrega visualmente o estado atual caso tenha mudado
        syncVisualState();
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }
    
    function updateSelectionText() {
        const count = selectedTags.length;
        if(saveBtn) saveBtn.textContent = `Salvar Seleção (${count})`;
    }

    // --- Sincronização Visual ---
    function syncVisualState() {
        allTagsInModal.forEach(tag => {
            if (selectedTags.some(t => t.value === tag.dataset.value)) {
                tag.classList.add('active');
            } else {
                tag.classList.remove('active');
            }
        });
        updateSelectionText();
    }
    
    // --- Lógica Principal: Salvar e Aplicar ---
    function saveAndApplyTags() {
        // 1. Limpa os inputs escondidos e o preview
        hiddenTagsContainer.innerHTML = '';
        tagsPreview.innerHTML = '';
        
        let hasSelection = selectedTags.length > 0;

        selectedTags.forEach(tag => {
            // 2a. Cria input hidden para envio no POST
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'caracteristicas[]';
            input.value = tag.value;
            hiddenTagsContainer.appendChild(input);
            
            // 2b. Cria a tag visual na página principal
            const tagElement = document.createElement('span');
            tagElement.className = 'char-tag-input';
            tagElement.innerHTML = tag.iconHTML + ' ' + tag.value;
            tagsPreview.appendChild(tagElement);
        });
        
        // 3. Controla o placeholder
        if (tagsPlaceholder) {
            tagsPlaceholder.style.display = hasSelection ? 'none' : 'block';
        }
        
        // 4. Dispara campos dinâmicos (Alergia/Medicação)
        if (typeof toggleDynamicFields === 'function') {
            toggleDynamicFields(selectedTags);
        }

        // 5. Avisa que o formulário mudou (para o modal de confirmação)
        if (typeof window.formHasChanges !== 'undefined') {
            window.formHasChanges = true;
        }
    }
    
    // --- Carregamento Inicial (Prefill) ---
    function prefillCharacteristics() {
        selectedTags = [];
        existingCharacteristics.forEach(value => {
            // Procura a tag no HTML do modal para pegar o ícone correto
            const matchingTag = document.querySelector(`.char-tag[data-value="${value}"]`);
            if (matchingTag) {
                const iconHTML = matchingTag.querySelector('i:first-child').outerHTML;
                selectedTags.push({ value: value, iconHTML: iconHTML });
            }
        });
        
        // Aplica o estado inicial
        syncVisualState();
        saveAndApplyTags();
        
        // Reseta o flag de mudanças pois acabamos de carregar a página
        if (typeof window.formHasChanges !== 'undefined') {
            window.formHasChanges = false;
        }
    }

    // --- Event Listeners ---
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', (event) => (event.target === modal) && closeModal());
    }

    // CLIQUE NAS TAGS (A Lógica Inteligente)
    allTagsInModal.forEach(tag => {
        tag.addEventListener('click', () => {
            const container = tag.closest('.char-tags-container');
            const categoryType = container.dataset.category;
            const value = tag.dataset.value;
            const iconHTML = tag.querySelector('i:first-child').outerHTML;
            const isActive = tag.classList.contains('active');

            if (isActive) {
                // Se já está ativo, remove
                tag.classList.remove('active');
                selectedTags = selectedTags.filter(t => t.value !== value);
            } else {
                // Se for categoria "Single" (Única escolha), limpa os outros do mesmo grupo
                if (categoryType === 'single-energy' || categoryType === 'single-training') {
                    const siblings = container.querySelectorAll('.char-tag.active');
                    siblings.forEach(sibling => {
                        sibling.classList.remove('active');
                        selectedTags = selectedTags.filter(t => t.value !== sibling.dataset.value);
                    });
                }
                
                // Adiciona o novo
                tag.classList.add('active');
                selectedTags.push({ value: value, iconHTML: iconHTML });
            }
            updateSelectionText();
        });
    });
    
    // Botão Salvar do Modal
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            saveAndApplyTags();
            closeModal();
        });
    }
    
    // Inicializa tudo
    document.addEventListener('DOMContentLoaded', prefillCharacteristics);
</script>
</body>
</html>
