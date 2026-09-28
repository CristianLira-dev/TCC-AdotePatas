<?php
// Inclui a conexão e a sessão
include_once 'conexao.php';
include_once 'session.php';

// Protege a página
requerer_login();

// --- LÊ MENSAGENS DA SESSÃO ---
$mensagem_status = $_SESSION['mensagem_status'] ?? '';
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? '';

unset($_SESSION['mensagem_status']);
unset($_SESSION['tipo_mensagem']);

// --- INICIALIZA VARIÁVEIS ---
$nome = $_SESSION['form_data']['nome'] ?? '';
$especie = $_SESSION['form_data']['especie'] ?? '';
$idade_valor = $_SESSION['form_data']['idade_valor'] ?? '';
$idade_unidade = $_SESSION['form_data']['idade_unidade'] ?? 'anos';
$porte = $_SESSION['form_data']['porte'] ?? '';
$sexo = $_SESSION['form_data']['sexo'] ?? ''; 
$raca = $_SESSION['form_data']['raca'] ?? '';
$cor = $_SESSION['form_data']['cor'] ?? '';
$status_vacinacao = $_SESSION['form_data']['status_vacinacao'] ?? '';
$status_castracao = $_SESSION['form_data']['status_castracao'] ?? '';
$comportamento = $_SESSION['form_data']['comportamento'] ?? '';
$caracteristicas = $_SESSION['form_data']['caracteristicas'] ?? [];
$alergias = $_SESSION['form_data']['alergias'] ?? [''];
$medicacao = $_SESSION['form_data']['medicacao'] ?? '';

unset($_SESSION['form_data']);

// --- PROCESSAMENTO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta de dados
    $nome = trim($_POST['nome'] ?? '');
    $especie = trim($_POST['especie'] ?? '');
    
    // Tratamento da Idade Composta
    $idade_valor = trim($_POST['idade_valor'] ?? '');
    $idade_unidade = trim($_POST['idade_unidade'] ?? 'anos');
    $idade_final = "$idade_valor $idade_unidade";

    $porte = trim($_POST['porte'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $raca = trim($_POST['raca'] ?? 'Não definida');
    $cor = trim($_POST['cor'] ?? '');
    $status_vacinacao = trim($_POST['status_vacinacao'] ?? '');
    $status_castracao = trim($_POST['status_castracao'] ?? '');
    $comportamento = trim($_POST['comportamento'] ?? '');
    $caracteristicas = $_POST['caracteristicas'] ?? [];
    $alergias = $_POST['alergias'] ?? [];
    $medicacao = trim($_POST['medicacao'] ?? '');
    
    // Novas validações
    $amamentacao_completa = isset($_POST['amamentacao_completa']);
    
    $_SESSION['form_data'] = $_POST;
    $id_usuario_logado = $_SESSION['user_id'];
    $tipo_usuario_logado = $_SESSION['user_tipo'];

    $erros = [];
    $fotos_salvas_paths = [];
    $caminho_vacina = null;

    // 2. Validações
    if (empty($nome)) $erros['nome'] = "O campo 'Nome' é obrigatório.";
    if (empty($idade_valor)) $erros['idade'] = "Informe o valor da idade.";
    
    // Validação da idade - apenas números
    if (!empty($idade_valor) && !is_numeric($idade_valor)) {
        $erros['idade'] = "A idade deve conter apenas números.";
    }
    
    // Validação de idade máxima em meses
    if ($idade_unidade == 'meses' && $idade_valor > 11) {
        $erros['idade'] = "A idade em meses não pode ser maior que 11. Para idades maiores, selecione 'Anos'.";
    }
    
    // Validação de Amamentação (Regra de Negócio)
    if (!$amamentacao_completa) {
        $erros['amamentacao'] = "O pet não pode ser cadastrado se não tiver concluído a amamentação.";
    }

$vaccinationUpload = $_FILES['carteira_vacinacao'] ?? null;
if ($status_vacinacao === 'sim' && (!$vaccinationUpload || ($vaccinationUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
    $erros['carteira_vacinacao'] = 'Envie a carteirinha de vacinação em JPG, PNG, WEBP ou PDF.';
} elseif ($vaccinationUpload && ($vaccinationUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    try {
        $caminho_vacina = appStoreUploadedFile(
            $vaccinationUpload,
            'uploads/documentos',
            'vacina_',
            appVaccinationDocumentMimeMap(),
            8 * 1024 * 1024
        );
    } catch (RuntimeException $error) {
        $erros['carteira_vacinacao'] = $error->getMessage();
    }
}
    // Validação das Fotos do Pet
    if (isset($_FILES['fotos_novas']) && !empty(array_filter($_FILES['fotos_novas']['name']))) {
        $total_files = count($_FILES['fotos_novas']['name']);
        if ($total_files > 5) $erros['fotos'] = "Máximo 5 fotos.";
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['fotos_novas']['error'][$i] == UPLOAD_ERR_OK) {
                $photo = [
                    'name' => $_FILES['fotos_novas']['name'][$i],
                    'type' => $_FILES['fotos_novas']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['fotos_novas']['tmp_name'][$i],
                    'error' => $_FILES['fotos_novas']['error'][$i],
                    'size' => $_FILES['fotos_novas']['size'][$i],
                ];
                try {
                    $fotos_salvas_paths[] = appStoreUploadedFile(
                        $photo,
                        'uploads/pets',
                        'pet_',
                        appImageMimeMap(),
                        5 * 1024 * 1024
                    );
                } catch (RuntimeException $error) {
                    $erros['fotos'] = $error->getMessage();
                }
            }
        }
    } else {
        $erros['fotos'] = "Pelo menos uma foto do pet é obrigatória.";
    }

    // 3. Inserção
    if (!empty($erros)) {
        if ($caminho_vacina) {
            @unlink(__DIR__ . '/' . $caminho_vacina);
        }
        foreach ($fotos_salvas_paths as $path) {
            @unlink(__DIR__ . '/' . $path);
        }
        $_SESSION['erros_form'] = $erros;
        $_SESSION['mensagem_status'] = "Por favor, corrija os erros abaixo.";
        $_SESSION['tipo_mensagem'] = 'danger';
        header("Location: cadastrar-pet.php");
        exit;
    } else {
        try {
            $conn->beginTransaction();
            
            $id_usuario_fk = ($tipo_usuario_logado == 'usuario') ? $id_usuario_logado : null;
            $id_ong_fk = ($tipo_usuario_logado == 'ong') ? $id_usuario_logado : null;
            
            // Processamento das características
            $caracteristicas_json = json_encode($caracteristicas, JSON_UNESCAPED_UNICODE);
            
            // Processamento de alergias e medicação
            $alergias_limpas = array_filter($alergias, function($alergia) {
                return !empty(trim($alergia));
            });
            $alergias_json = !empty($alergias_limpas) ? json_encode($alergias_limpas, JSON_UNESCAPED_UNICODE) : null;
            $medicacao_limpa = !empty(trim($medicacao)) ? trim($medicacao) : null;

$sql = "INSERT INTO pet (nome, especie, sexo, idade, porte, raca, cor, status_vacinacao, status_castracao, comportamento, id_usuario_fk, id_ong_fk, status_disponibilidade, caracteristicas, carteira_vacinacao, alergias, medicacao) 
        VALUES (:nome, :especie, :sexo, :idade, :porte, :raca, :cor, :status_vacinacao, :status_castracao, :comportamento, :id_usuario_fk, :id_ong_fk, 'Em Analise', :caracteristicas, :carteira_vacinacao, :alergias, :medicacao)";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ':nome' => $nome,
    ':especie' => $especie,
    ':sexo' => $sexo,
    ':idade' => $idade_final,
    ':porte' => $porte,
    ':raca' => $raca,
    ':cor' => $cor,
    ':status_vacinacao' => $status_vacinacao,
    ':status_castracao' => $status_castracao,
    ':comportamento' => $comportamento,
    ':id_usuario_fk' => $id_usuario_fk,
    ':id_ong_fk' => $id_ong_fk,
    ':caracteristicas' => $caracteristicas_json,
    ':carteira_vacinacao' => $caminho_vacina, 
    ':alergias' => $alergias_json,
    ':medicacao' => $medicacao_limpa
]);
            
            $id_pet = $conn->lastInsertId();

            // Salva fotos
            $stmt_foto = $conn->prepare("INSERT INTO pet_fotos (id_pet_fk, caminho_foto) VALUES (?, ?)");
            foreach ($fotos_salvas_paths as $path) {
                $stmt_foto->execute([$id_pet, $path]);
            }

            $conn->commit();
            appAudit($conn, 'create', 'pet', (int) $id_pet);
            unset($_SESSION['form_data']);
            unset($_SESSION['erros_form']);
            $_SESSION['toast_message'] = "Pet cadastrado! Aguardando análise da carteirinha de vacinação.";
            $_SESSION['toast_type'] = 'success';
            header("Location: perfil?page=meus-pets");
            exit;

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            // Limpeza de arquivos em caso de erro
            if ($caminho_vacina) @unlink(__DIR__ . '/' . $caminho_vacina);
            foreach ($fotos_salvas_paths as $p) @unlink(__DIR__ . '/' . $p);
            error_log('Erro ao cadastrar pet: ' . $e->getMessage());
            $_SESSION['mensagem_status'] = 'Não foi possível cadastrar o pet. Tente novamente.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header("Location: cadastrar-pet.php");
            exit;
        }
    }
}

// Carrega erros da sessão para exibição
$erros_form = $_SESSION['erros_form'] ?? [];
unset($_SESSION['erros_form']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Pet - Adote Patas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="assets/css/pages/cadastro-pet/caracteristica.css">
    <link rel="stylesheet" href="assets/css/pages/autenticacao/autenticacao.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
/* =========================================
   VARIÁVEIS E GERAL
   ========================================= */
:root {
    --cor-vermelho: #B46459;
    --cor-vermelho-claro: #d68a80;
    --cor-rosa-claro: #f8f0ef;
    --cor-rosa-escuro: #e8c4c0;
    --cor-branca: #ffffff;
    --cor-texto: #333333;
}

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

/* =========================================
   INPUTS E ERROS
   ========================================= */
.input-error {
    border-color: #dc2626 !important;
    background-color: rgba(254, 242, 242, 0.9) !important; 
}

.error-message {
    color: #dc2626; 
    font-weight: 500;
    font-size: 0.875rem;
    margin-top: 0.4rem;
    display: none;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.input-error:focus {
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
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

input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] {
    -moz-appearance: textfield;
}

/* =========================================
   DRAG AND DROP & UPLOADS
   ========================================= */
.file-drop-area {
    border: 2px dashed var(--cor-vermelho-claro);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9); 
    cursor: pointer;
    position: relative;
}

.file-drop-area:hover, .file-drop-area.dragover {
    background: #fff;
    border-color: var(--cor-vermelho);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.file-drop-area i {
    font-size: 3rem;
    color: var(--cor-vermelho);
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.file-drop-area.dragover i {
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

#vacinacao-section .file-drop-area {
    border: 2px dashed var(--cor-vermelho);
    background: rgba(255, 245, 245, 0.95);
}

#vacinacao-section .file-drop-area:hover {
    border-color: #a04035;
    background: #fff;
}

/* Previews de Fotos */
#fotos-preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.foto-preview {
    position: relative;
    width: 100%;
    padding-top: 100%;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    border: 2px solid #fff;
}

.foto-preview img {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
}

.remove-preview {
    position: absolute;
    top: 5px; right: 5px;
    width: 24px; height: 24px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    color: var(--cor-vermelho);
    font-weight: bold;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease;
}

.remove-preview:hover {
    background: var(--cor-vermelho);
    color: white;
}

/* =========================================
   CHECKBOXES E CAMPOS DINÂMICOS
   ========================================= */
.amamentacao-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 16px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    cursor: pointer;
}

.amamentacao-checkbox:hover {
    border-color: var(--cor-vermelho-claro);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(180, 100, 89, 0.2);
}

.amamentacao-checkbox.input-error {
    border-color: #dc2626 !important;
    background: rgba(255, 240, 240, 0.95);
}

.amamentacao-checkbox input[type="checkbox"] {
    width: 24px;
    height: 24px;
    margin-top: 0.25rem;
    accent-color: var(--cor-vermelho);
    flex-shrink: 0;
}

.amamentacao-checkbox label {
    font-weight: 600;
    color: var(--cor-texto);
    line-height: 1.5;
    cursor: pointer;
}

.amamentacao-checkbox .required {
    color: var(--cor-vermelho);
    font-weight: 700;
}

/* Campos Dinâmicos */
.dynamic-field {
    margin-top: 1rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.dynamic-field h4 {
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
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

.alergia-input { flex: 1; }

.btn-add-alergia, .btn-remove-alergia {
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex; align-items: center; justify-content: center;
    color: white;
}

.btn-add-alergia {
    background: var(--cor-vermelho);
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    gap: 0.5rem;
}
.btn-add-alergia:hover { background: #a04035; }

.btn-remove-alergia {
    background: rgba(180, 100, 89, 0.8);
    width: 42px; height: 54px; 
    border-radius: 12px;
}
.btn-remove-alergia:hover { background: var(--cor-vermelho); }


/* =========================================
   SELECTS CUSTOMIZADOS
   ========================================= */
.select-hidden {
    position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0;
    overflow: hidden; clip: rect(0, 0, 0, 0); border: 0;
}

.custom-select-wrapper {
    position: relative;
    width: 100%;
    font-family: 'Poppins', sans-serif;
}

.custom-select-trigger {
    width: 100%;
    padding: 0 1.15rem; /* Padding ajustado para altura fixa */
    height: 54px; /* Altura fixa igual aos inputs */
    background-color: rgba(180, 100, 89, 0.58); 
    border: 2px solid transparent;
    border-radius: 0.75rem; 
    color: white;
    font-size: 1rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
    transition: border-color 0.3s ease-in-out;
    box-sizing: border-box;
}

.custom-select-trigger:focus, 
.custom-select-trigger:hover {
    border-color: rgba(255, 255, 255, 0.6);
    outline: none;
}

.custom-select-value.placeholder {
    color: rgba(255, 255, 255, 0.8);
}

.custom-select-arrow {
    width: 10px; height: 10px;
    border-right: 2px solid white;
    border-bottom: 2px solid white;
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
    top: calc(100% + 5px);
    left: 0; right: 0; z-index: 50;
    background-color: #fff; 
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    padding: 8px;
    list-style: none;
    margin: 0;
    overflow-y: auto;
    max-height: 250px;
    display: none;
}

.custom-option {
    padding: 10px 15px;
    color: var(--cor-texto);
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.custom-option:hover, 
.custom-option.selected {
    background-color: var(--cor-rosa-claro);
    color: var(--cor-vermelho);
    font-weight: 700;
}

/* Botão de Características */
#openModalBtn {
    min-height: 60px;
    display: flex;
    align-items: center; 
    padding: 1.15rem;
    flex-wrap: wrap;
    gap: 8px;
}

.tags-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.char-tag-input {
    background-color: rgba(255, 255, 255, 0.9);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--cor-vermelho);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tags-placeholder {
    color: rgba(255, 255, 255, 0.8) !important;
}
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

<div id="toast-notification" class="toast p-0" style="display: none;">
    <div id="toast-icon" class="toast-icon"></div>
    <div class="toast-content">
        <p id="toast-message" class="toast-message">Mensagem de exemplo.</p>
    </div>
    <div class="toast-progress-bar"></div>
</div>

<a href="perfil?page=meus-pets" class="btn-voltar" title="Voltar para a página inicial">
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
            <h1 class="text-xl md:text-4xl font-bold text-[#666662]">Cadastrar Pet</h1>
            <div class="w-24 h-1 bg-[#666662] mx-auto mt-1 rounded-full"></div>
        </div>
        <div class="h-16 w-16 invisible"></div>
    </div>

    <div class="container-card w-full p-6 sm:p-10 rounded-3xl shadow-xl">
        
        <?php if (!empty($mensagem_status)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> mb-4">
                <?php echo htmlspecialchars($mensagem_status); ?>
            </div>
        <?php endif; ?>

       <form action="cadastrar-pet.php" method="post" enctype="multipart/form-data" id="form-cadastro-pet" class="space-y-6" novalidate>
            
            <div class="grid gap-6">
                <div>
                    <label for="nome">Nome do Pet</label>
                    <input type="text" name="nome" id="nome" placeholder="Nome do Pet" required 
                           class="input-style w-full <?php echo isset($erros_form['nome']) ? 'input-error' : ''; ?>"
                           value="<?php echo htmlspecialchars($nome); ?>">
                    <?php if (isset($erros_form['nome'])): ?>
                        <span class="error-message"><?php echo htmlspecialchars($erros_form['nome']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NOVO LAYOUT: Idade, Espécie e Sexo na mesma linha -->
            <div class="form-row-3">
                <!-- Idade Aproximada -->
                <div>
                    <label>Idade Aproximada</label>
                    <div class="age-input-group">
                        
                        <input type="number" name="idade_valor" id="idade_valor" placeholder="Ex: 2" required min="0"
                            class="input-style age-value <?php echo isset($erros_form['idade']) ? 'input-error' : ''; ?>" 
                            value="<?php echo htmlspecialchars($idade_valor); ?>">
                            
                        <select name="idade_unidade" id="idade_unidade-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                            <option value="anos" <?= $idade_unidade == 'anos' ? 'selected' : '' ?>>Anos</option>
                            <option value="meses" <?= $idade_unidade == 'meses' ? 'selected' : '' ?>>Meses</option>
                        </select>

                        <div class="custom-select-wrapper age-unit-custom" data-target-select="idade_unidade-real">
                            <button type="button" class="custom-select-trigger input-style w-full" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-select-value <?php echo empty($idade_unidade) ? 'placeholder' : ''; ?>">
                                    <?php echo ($idade_unidade == 'meses') ? 'Meses' : 'Anos'; ?>
                                </span>
                                <span class="custom-select-arrow"></span>
                            </button>
                            <ul class="custom-select-options" role="listbox">
                                <li class="custom-option" data-value="anos" role="option" tabindex="0">Anos</li>
                                <li class="custom-option" data-value="meses" role="option" tabindex="0">Meses</li>
                            </ul>
                        </div>
                    </div>
                    <?php if (isset($erros_form['idade'])): ?>
                        <span class="error-message" style="display:block"><?php echo htmlspecialchars($erros_form['idade']); ?></span>
                    <?php endif; ?>
                    <div class="error-message" id="error-idade">Informe uma idade válida.</div>
                </div>
                <!-- Espécie -->
                <div>
                    <label id="select-label-especie">Espécie</label>
                    <select name="especie" id="especie-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="cachorro" <?php echo ($especie == 'cachorro') ? 'selected' : ''; ?>>Cachorro</option>
                        <option value="gato" <?php echo ($especie == 'gato') ? 'selected' : ''; ?>>Gato</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="especie-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-especie">
                            <span class="custom-select-value <?php echo empty($especie) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($especie == 'cachorro') echo 'Cachorro';
                                    elseif ($especie == 'gato') echo 'Gato';
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
                        <option value="macho" <?php echo ($sexo == 'macho') ? 'selected' : ''; ?>>Macho</option>
                        <option value="femea" <?php echo ($sexo == 'femea') ? 'selected' : ''; ?>>Fêmea</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="sexo-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-sexo">
                            <span class="custom-select-value <?php echo empty($sexo) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($sexo == 'macho') echo 'Macho';
                                    elseif ($sexo == 'femea') echo 'Fêmea';
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
                    <select name="porte" id="porte-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="pequeno" <?php echo ($porte == 'pequeno') ? 'selected' : ''; ?>>Pequeno</option>
                        <option value="medio" <?php echo ($porte == 'medio') ? 'selected' : ''; ?>>Médio</option>
                        <option value="grande" <?php echo ($porte == 'grande') ? 'selected' : ''; ?>>Grande</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="porte-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-porte">
                            <span class="custom-select-value <?php echo empty($porte) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($porte == 'pequeno') echo 'Pequeno';
                                    elseif ($porte == 'medio') echo 'Médio';
                                    elseif ($porte == 'grande') echo 'Grande';
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
                           value="<?php echo htmlspecialchars($raca); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="cor">Cor</label>
                    <input type="text" name="cor" id="cor" placeholder="Cor (Ex: Caramelo)" class="input-style w-full"
                           value="<?php echo htmlspecialchars($cor); ?>">
                </div>
                <div>
                    <label id="select-label-vacinacao">Vacinado?</label>
                    <select name="status_vacinacao" id="status_vacinacao-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="sim" <?php echo ($status_vacinacao == 'sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="nao" <?php echo ($status_vacinacao == 'nao') ? 'selected' : ''; ?>>Não</option>
                    </select>

                    <div class="custom-select-wrapper" data-target-select="status_vacinacao-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-vacinacao">
                            <span class="custom-select-value <?php echo empty($status_vacinacao) ? 'placeholder' : ''; ?>">
                                 <?php 
                                    if ($status_vacinacao == 'sim') echo 'Sim';
                                    elseif ($status_vacinacao == 'nao') echo 'Não';
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
                </div>
            </div>
            
            <div class="grid gap-6"> 
                <div>
                    <label id="select-label-castracao">Castrado?</label>
                    <select name="status_castracao" id="status_castracao-real" class="select-hidden" aria-hidden="true" tabindex="-1">
                        <option value="sim" <?php echo ($status_castracao == 'sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="nao" <?php echo ($status_castracao == 'nao') ? 'selected' : ''; ?>>Não</option>
                    </select>
                    
                    <div class="custom-select-wrapper" data-target-select="status_castracao-real">
                        <button type="button" class="custom-select-trigger input-style w-full" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-labelledby="select-label-castracao">
                            <span class="custom-select-value <?php echo empty($status_castracao) ? 'placeholder' : ''; ?>">
                                <?php 
                                    if ($status_castracao == 'sim') echo 'Sim';
                                    elseif ($status_castracao == 'nao') echo 'Não';
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
            </div>

            <hr class="border-gray-200 my-4">
            
<!-- REQUISITOS DE SAÚDE - MELHORADO -->
<div class="grid gap-4 mt-2 grid-cols-1 md:grid-cols-1">
    <h3 class="text-lg font-bold text-gray-700">Requisitos de Saúde</h3>
    
    <!-- 1. Amamentação Completa - ESTILIZADO -->
    <div class="amamentacao-checkbox <?php echo isset($erros_form['amamentacao']) ? 'input-error' : ''; ?>">
        <input type="checkbox" name="amamentacao_completa" id="check_amamentacao" required
               <?php echo isset($_POST['amamentacao_completa']) ? 'checked' : ''; ?>>
        <label for="check_amamentacao">
            Declaro que o pet já completou o período de amamentação (desmame) e já come ração sólida/pastosa. 
            <span class="required">* Obrigatório</span>
        </label>
    </div>
    <?php if (isset($erros_form['amamentacao'])): ?>
        <span class="error-message"><?php echo htmlspecialchars($erros_form['amamentacao']); ?></span>
    <?php endif; ?>

    <!-- 2. Carteirinha de Vacinação - COMPORTAMENTO DINÂMICO -->
    <div id="vacinacao-section" class="mt-4">
        <h4 class="font-semibold text-gray-700 mb-3">Documentação de Vacinação</h4>
        
        <!-- Área que aparece apenas quando o pet É VACINADO -->
        <div id="vacinado-content" style="display: <?php echo ($status_vacinacao == 'sim') ? 'block' : 'none'; ?>;">
            <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(98, 142, 109, 0.8);">
                <div class="flex items-center gap-3" style="color: var(--cor-branca); !important">
                    <i class="fa-solid fa-syringe fs-4"></i>
                    <div>
                        <p class="text-sm font-semibold">Pet vacinado</p>
                        <p class="text-sm text-informacao">É obrigatório enviar a carteirinha de vacinação para comprovação.</p>
                    </div>
                </div>
            </div>

            <!-- Campo de upload da carteirinha (obrigatório quando vacinado) -->
            <div class="mt-4">
                <label class="font-semibold text-gray-700 mb-1 block" id="label-vacina-text">Carteirinha de Vacinação <span class="text-red-600">*</span></label>
                <div id="drop-area-vacina" class="file-drop-area <?php echo isset($erros_form['carteira_vacinacao']) ? 'input-error' : ''; ?>">
                    <i class="fas fa-file-medical"></i>
                    <div class="file-info" id="file-name-vacina">Arraste ou clique para selecionar</div>
                    <div class="file-hint">Formatos: JPG, PNG, PDF, WEBP</div>
                    <input type="file" name="carteira_vacinacao" id="file_vacina" class="hidden" accept="image/*,.pdf">
                </div>
                <div id="preview-vacina-container" class="hidden mt-2 p-2 bg-white rounded border">
                    <div id="preview-vacina"></div>
                </div>
                 <div class="error-message" id="error-vacina">A carteirinha é obrigatória.</div>
                 <?php if (isset($erros_form['carteira_vacinacao'])): ?>
                    <span class="error-message" style="display:block"><?php echo htmlspecialchars($erros_form['carteira_vacinacao']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mensagem quando NÃO É VACINADO -->
        <div id="nao-vacinado-content" style="display: <?php echo ($status_vacinacao == 'nao') ? 'block' : 'none'; ?>;">
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg text-center">
                                    <i class="fa-solid fa-syringe fs-4"></i>
                <p class="text-sm text-gray-700">O pet <strong style="color: var(--cor-vermelho);">NÃO</strong> é vacinado. As opções de carteirinha ficarão disponíveis quando marcar como vacinado.</p>
            </div>
        </div>
    </div>
</div>
    
            <div>
                <label class="font-semibold text-gray-700">Adicionar Fotos</label>
                
                <!-- Área de Drag and Drop para Fotos -->
                <div id="drop-area" class="file-drop-area <?php echo isset($erros_form['fotos']) ? 'input-error' : ''; ?>">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div class="file-info" id="file-name-span">Arraste e solte fotos aqui ou clique para selecionar</div>
                    <div class="file-hint">Máximo: 5 fotos | Formatos: PNG, JPG, JPEG, WEBP</div>
                    <input type="file" id="fotos_novas_input" class="hidden" multiple accept="image/png, image/jpeg, image/jpg, image/webp">
                </div>
                
                <input type="file" name="fotos_novas[]" id="fotos_novas_final" class="hidden" multiple>

                <div id="fotos-preview-container" class="mt-4"></div>
                <small id="limite-fotos-helper" class="text-sm text-gray-600 mt-1"></small>
                <?php if (isset($erros_form['fotos'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($erros_form['fotos']); ?></span>
                <?php endif; ?>
            </div>

            <!-- CAMPOS DINÂMICOS PARA CARACTERÍSTICAS ESPECIAIS -->
            <div id="dynamic-fields-container">
                <!-- Alergias (aparece quando selecionar característica "Alergia") -->
                <div id="alergia-field" class="dynamic-field" style="display: none;">
                    <h4><i class="fas fa-allergies"></i> Alergias do Pet</h4>
                    <div id="alergias-container">
                        <!-- Inputs de alergia serão adicionados aqui dinamicamente -->
                    </div>
                    <button type="button" id="add-alergia-btn" class="btn-add-alergia">
                        <i class="fas fa-plus"></i> Adicionar Alergia
                    </button>
                </div>

                <!-- Medicação (aparece quando selecionar característica "Medicação") -->
                <div id="medicacao-field" class="dynamic-field" style="display: none;">
                    <h4><i class="fas fa-pills"></i> Medicação do Pet</h4>
                    <input type="text" name="medicacao" id="medicacao_input" placeholder="Nome da medicação que o pet toma" 
                           class="input-style medicacao-input" value="<?php echo htmlspecialchars($medicacao); ?>">
                </div>
            </div>

            <hr class="border-gray-200 my-4">
                
            <div>
                <label for="openModalBtn" class="sr-only">Características</label>
                <button type="button" id="openModalBtn" class="input-style w-full text-left">
                    <span id="tagsPlaceholder" class="tags-placeholder" style="<?php echo !empty($caracteristicas) ? 'display: none;' : 'display: block;'; ?>">Selecionar Características...</span>
                    <span class="tags-preview" id="tagsPreview"></span>
                </button>
            </div>

            <div id="hidden-tags-container"></div>

            <div>
                <label class="sr-only" for="comportamento">Comportamento (Ex: Dócil, adora crianças...)</label>
                <input type="text" name="comportamento" id="comportamento" placeholder="Conte um pouco sobre o pet..." class="input-style w-full" value="<?php echo htmlspecialchars($comportamento); ?>">
            </div>

            <div class="flex justify-center w-55 mx-auto">
                <button type="submit" class="adopt-btn" id="submit-btn"> 
                    <div class="heart-background" aria-hidden="true">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <span>Cadastrar Pet</span>
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
                <p>Escolha as características do seu pet.</p>
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
            <button type="button" class="btn btn-salvar" id="saveModalBtn">Salvar Seleção</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>

<script src="assets/js/pages/autenticacao/autenticacao.js" type="module"></script>

<script>
// Constantes Globais
const totalFotosAtuais = 0; // Cadastro começa com 0
const existingCharacteristics = <?php echo json_encode($caracteristicas); ?>;
const alergiasExistentes = <?php echo json_encode($alergias); ?>;
const MAX_FOTOS_GLOBAL = 5;

// Função: Toast de Notificação
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast-notification');
    const msg = document.getElementById('toast-message');
    const icon = document.getElementById('toast-icon');
    
    if (!toast || !msg) return;

    msg.textContent = message;
    toast.className = `toast ${type}`;
    
    if (icon) {
        if (type === 'success') icon.innerHTML = '<i class="fas fa-check"></i>';
        else icon.innerHTML = '<i class="fas fa-times"></i>';
    }
    
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

// --- VALIDAÇÃO DE CAMPOS VAZIOS NO SUBMIT ---
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-cadastro-pet');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Função auxiliar para marcar erro
        function setError(id, message) {
            const el = document.getElementById(id);
            if(el) {
                el.classList.add('input-error');
                // Tenta achar msg de erro próxima ou pelo ID específico
                const err = el.parentNode.querySelector('.error-message') || document.getElementById('error-'+id);
                if(err) {
                    err.style.display = 'block';
                    if(message) err.textContent = message;
                }
            }
        }

        function clearErrors() {
            document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
        }

        clearErrors();

        // 1. Validações básicas (inputs simples)
       
        const requiredIds = ['nome', 'especie-real', 'sexo-real', 'porte-real', 'status_vacinacao-real', 'status_castracao-real'];
        requiredIds.forEach(id => {
            const el = document.getElementById(id);
            // Para selects customizados, o valor pode estar no input hidden ou no visual?
            // Aqui assumimos que os IDs apontam para os inputs que guardam o valor
            if(!el || !el.value.trim()) {
                setError(id);
                isValid = false;
            }
        });

        // 2. Idade
        const idadeVal = document.getElementById('idade_valor');
        if(!idadeVal || !idadeVal.value) {
            setError('idade_valor');
            const errDiv = document.getElementById('error-idade');
            if(errDiv) errDiv.style.display = 'block';
            isValid = false;
        }

        // 3. Amamentação (Checkbox)
        const checkAmamentacao = document.getElementById('check_amamentacao');
        if(checkAmamentacao && !checkAmamentacao.checked) {
             const wrapper = document.getElementById('container-amamentacao');
             if(wrapper) wrapper.classList.add('input-error');
             const errDiv = document.getElementById('error-amamentacao');
             if(errDiv) errDiv.style.display = 'block';
             isValid = false;
        }

        // 4. Carteirinha (File) - Obrigatório apenas se vacinado = sim
        const statusVacina = document.getElementById('status_vacinacao-real');
        if (statusVacina && statusVacina.value === 'sim') {
            const vacinaInput = document.getElementById('file_vacina');
            if(vacinaInput && vacinaInput.files.length === 0) {
                const dropArea = document.getElementById('drop-area-vacina');
                if(dropArea) dropArea.classList.add('input-error');
                const errDiv = document.getElementById('error-vacina');
                if(errDiv) errDiv.style.display = 'block';
                isValid = false;
            }
        }

        // 5. Fotos
        const fotosInput = document.getElementById('fotos_novas_final');
        // Nota: No cadastro, totalFotosAtuais é 0, então só olhamos o input de novas
        if(fotosInput && fotosInput.files.length === 0) {
            const dropArea = document.getElementById('drop-area');
            if(dropArea) dropArea.classList.add('input-error');
            const errDiv = document.getElementById('error-fotos');
            if(errDiv) errDiv.style.display = 'block';
            isValid = false;
        }

        if(!isValid) {
            e.preventDefault();
            showToast('Preencha todos os campos obrigatórios!', 'danger');
            // Scroll para o primeiro erro
            const firstError = document.querySelector('.input-error, .error-message[style="display: block;"]');
            if(firstError) {
                firstError.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        }
    });
});

// --- LÓGICA DE IDADE (MESES vs ANOS) ---
document.addEventListener('DOMContentLoaded', function() {
    const idadeValorInput = document.querySelector('input[name="idade_valor"]');
    const idadeUnidadeSelect = document.getElementById('idade_unidade-real');
    
    if (!idadeValorInput || !idadeUnidadeSelect) return;

    function checkAgeLimit() {
        // Removemos a limitação estrita no HTML (max=11) e controlamos aqui
        if (idadeUnidadeSelect.value === 'meses') {
            idadeValorInput.setAttribute('max', '11');
            
            // Se o usuário digitar algo maior que 11, avisamos
            if(idadeValorInput.value && parseInt(idadeValorInput.value) > 11) {
                idadeValorInput.value = 11; 
                showToast('A idade em meses não pode ser maior que 11.', 'warning');
            }
        } else {
            // Se for ANOS, removemos o limite máximo
            idadeValorInput.removeAttribute('max');
        }
    }

    idadeValorInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, ''); // Apenas números
        checkAgeLimit();
    });
    
    // Observa mudança no select hidden (disparado pelo custom select)
    idadeUnidadeSelect.addEventListener('change', checkAgeLimit);
    
    // Executa na carga inicial para definir o estado correto
    checkAgeLimit();
});

// --- UPLOAD DE VACINA (DRAG & DROP) ---
document.addEventListener('DOMContentLoaded', function() {
    const dropAreaVacina = document.getElementById('drop-area-vacina');
    const fileInputVacina = document.getElementById('file_vacina');
    const fileNameVacina = document.getElementById('file-name-vacina');
    const previewVacinaContainer = document.getElementById('preview-vacina-container');
    const previewVacina = document.getElementById('preview-vacina');

    if (!dropAreaVacina) return;

    // Prevenir comportamentos padrão
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });

    // Efeitos visuais
    ['dragenter', 'dragover'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, () => dropAreaVacina.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropAreaVacina.addEventListener(eventName, () => dropAreaVacina.classList.remove('dragover'), false);
    });

    // Handle Drop
    dropAreaVacina.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        handleVacinaFile(files);
    }, false);

    // Handle Click
    dropAreaVacina.addEventListener('click', () => fileInputVacina.click());
    fileInputVacina.addEventListener('change', function() { handleVacinaFile(this.files); });

    function handleVacinaFile(files) {
        if (files.length) {
            const file = files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
            
            if (!allowedTypes.includes(file.type)) {
                showToast('Formato inválido. Use JPG, PNG, WEBP ou PDF.', 'danger');
                return;
            }
            
            // Atualiza interface
            fileNameVacina.textContent = file.name;
            if(previewVacinaContainer) previewVacinaContainer.classList.remove('hidden');
            
            if(previewVacina) {
                previewVacina.textContent = 'Arquivo selecionado: ' + file.name;
                previewVacina.style.color = 'green';
            }
            
            // Remove erro visual
            dropAreaVacina.classList.remove('input-error');
            const errDiv = document.getElementById('error-vacina');
            if(errDiv) errDiv.style.display = 'none';
            
            // Atualiza input
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInputVacina.files = dt.files;
        }
    }
});

// --- UPLOAD DE FOTOS E CONVERSÃO WEBP (COM LIMITE) ---
document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('fotos_novas_input');
    const finalInput = document.getElementById('fotos_novas_final');
    const fileNameSpan = document.getElementById('file-name-span');
    const previewContainer = document.getElementById('fotos-preview-container');
    
    if (!dropArea || !fileInput) return;

    // Validação Inicial
    validarLimiteFotos();

    // Listeners Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    dropArea.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
    dropArea.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function() { handleFiles(this.files); });

    async function handleFiles(files) {
        if (!files.length) return;

        // Calcula totais (Assumindo totalFotosAtuais global definido na página)
        // Se não estiver definido (página de cadastro), assume 0
        const atuais = (typeof totalFotosAtuais !== 'undefined') ? totalFotosAtuais : 0;
        const novas = files.length;
        
        if ((atuais + novas) > MAX_FOTOS_GLOBAL) {
            showToast(`Limite de ${MAX_FOTOS_GLOBAL} fotos excedido!`, 'danger');
            return;
        }

        fileNameSpan.textContent = 'Processando...';

        try {
            const promises = Array.from(files).map(file => {
                // Preview
                const reader = new FileReader();
                reader.onload = (e) => addImagePreview(e.target.result, file.name);
                reader.readAsDataURL(file);
                // Converter
                return convertToWebP(file);
            });

            const converted = await Promise.all(promises);
            
            // Adiciona ao input final
            const dt = new DataTransfer();
            converted.forEach(f => dt.items.add(f));
            finalInput.files = dt.files;
            
            fileNameSpan.textContent = `${converted.length} nova(s) foto(s)`;
            
            // Remove erro visual
            dropArea.classList.remove('input-error');
            const errDiv = document.getElementById('error-fotos');
            if(errDiv) errDiv.style.display = 'none';

            validarLimiteFotos();

        } catch (error) {
            console.error(error);
            showToast('Erro ao processar imagens.', 'danger');
        }
    }

    function addImagePreview(src, name) {
        const div = document.createElement('div');
        div.className = 'foto-preview';
        div.innerHTML = `<img src="${src}"><button type="button" class="remove-preview">×</button>`;
        
        div.querySelector('button').addEventListener('click', () => {
            div.remove();
            finalInput.value = ''; // Limpa tudo para simplificar
            previewContainer.innerHTML = '';
            fileNameSpan.textContent = 'Arraste ou clique para selecionar';
            validarLimiteFotos();
        });
        
        previewContainer.appendChild(div);
    }
});

function validarLimiteFotos() {
    const fotosNovasInput = document.getElementById('fotos_novas_final');
    if(!fotosNovasInput) return;

    const novas = fotosNovasInput.files.length;
    // Se for edição, considera fotos atuais marcadas para exclusão. Se cadastro, atuais é 0.
    const atuaisTotal = (typeof totalFotosAtuais !== 'undefined') ? totalFotosAtuais : 0;
    let marcadas = 0;
    
    // Verifica se existem checkboxes de exclusão (página de edição)
    const checkboxes = document.querySelectorAll('input[name="fotos_para_excluir[]"]:checked');
    if(checkboxes) marcadas = checkboxes.length;

    const total = (atuaisTotal - marcadas) + novas;
    
    const helper = document.getElementById('limite-fotos-helper');
    const btn = document.getElementById('submit-btn');

    if (total > MAX_FOTOS_GLOBAL) {
        if(helper) {
            helper.textContent = `Limite excedido (${total}/${MAX_FOTOS_GLOBAL})`;
            helper.style.color = 'red';
        }
        if(btn) btn.disabled = true;
    } else {
        if(helper) {
            helper.textContent = `Total: ${total}/${MAX_FOTOS_GLOBAL}`;
            helper.style.color = '#666';
        }
        if(btn) btn.disabled = false;
    }
}

// Função de Conversão WebP
function convertToWebP(file) {
    return new Promise((resolve, reject) => {
        if (file.type === 'image/webp') { resolve(file); return; }
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                canvas.toBlob((blob) => {
                    const newName = file.name.replace(/\.[^/.]+$/, "") + ".webp";
                    resolve(new File([blob], newName, { type: 'image/webp' }));
                }, 'image/webp', 0.8);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// --- CONTROLE DE CAMPOS DINÂMICOS (ALERGIA/MEDICAÇÃO) ---
document.addEventListener('DOMContentLoaded', function() {
    const alergiaField = document.getElementById('alergia-field');
    const medicacaoField = document.getElementById('medicacao-field');
    const alergiasContainer = document.getElementById('alergias-container');
    const addAlergiaBtn = document.getElementById('add-alergia-btn');
    
    if(!alergiaField || !medicacaoField) return;

    // Variável global 'alergiasExistentes' deve ser definida no PHP
    if (typeof alergiasExistentes !== 'undefined' && alergiasExistentes.length > 0 && alergiasExistentes[0] !== '') {
        alergiasExistentes.forEach((alergia) => {
            if (alergia.trim() !== '') addAlergiaInput(alergia);
        });
    } else {
        addAlergiaInput('');
    }
    
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
        const icon = document.createElement('i');
        icon.className = 'fas fa-times';
        removeButton.appendChild(icon);
        inputGroup.append(input, removeButton);
        alergiasContainer.appendChild(inputGroup);
        updateRemoveButtons();
    }
    
    function updateRemoveButtons() {
        const removeButtons = alergiasContainer.querySelectorAll('.btn-remove-alergia');
        removeButtons.forEach((btn, index) => {
            btn.disabled = (index === 0 && removeButtons.length === 1);
        });
    }
    
    if(addAlergiaBtn) addAlergiaBtn.addEventListener('click', () => addAlergiaInput());
    
    alergiasContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-alergia')) {
            const group = e.target.closest('.alergia-input-group');
            if (group && alergiasContainer.children.length > 1) {
                group.remove();
                updateRemoveButtons();
            }
        }
    });
    
    // Função Global para o Modal chamar
    window.toggleDynamicFields = function(selectedTags) {
        const hasAlergia = selectedTags.some(tag => tag.value === 'Alergia');
        const hasMedicacao = selectedTags.some(tag => tag.value === 'Medicação');
        
        if (hasAlergia) {
            alergiaField.style.display = 'block';
        } else {
            alergiaField.style.display = 'none';
            // Reseta mas mantém um input vazio
            alergiasContainer.innerHTML = '';
            addAlergiaInput('');
        }
        
        if (hasMedicacao) {
            medicacaoField.style.display = 'block';
        } else {
            medicacaoField.style.display = 'none';
            document.getElementById('medicacao_input').value = '';
        }
    };
});

// --- CONTROLE DE VISIBILIDADE DA VACINAÇÃO ---
document.addEventListener('DOMContentLoaded', function() {
    const statusVacinacaoReal = document.getElementById('status_vacinacao-real');
    const vacinadoContent = document.getElementById('vacinado-content');
    const naoVacinadoContent = document.getElementById('nao-vacinado-content');
    const fileVacina = document.getElementById('file_vacina');

    function toggleVacinacaoSection() {
        if(!statusVacinacaoReal || !vacinadoContent) return;
        
        const isVacinado = statusVacinacaoReal.value === 'sim';
        vacinadoContent.style.display = isVacinado ? 'block' : 'none';
        naoVacinadoContent.style.display = isVacinado ? 'none' : 'block';
        
        if (!isVacinado && typeof window.clearVacinaPreview === 'function') {
            window.clearVacinaPreview();
        }
    }

    if (statusVacinacaoReal) {
        toggleVacinacaoSection();
        statusVacinacaoReal.addEventListener('change', toggleVacinacaoSection);
    }
});

// --- SELECTS CUSTOMIZADOS ---
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.custom-select-wrapper').forEach(setupCustomSelect);
});

function setupCustomSelect(wrapper) {
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const optionsList = wrapper.querySelector('.custom-select-options');
    const options = wrapper.querySelectorAll('.custom-option');
    const valueSpan = trigger.querySelector('.custom-select-value');
    const realSelect = document.getElementById(wrapper.dataset.targetSelect);

    if (realSelect) {
        options.forEach(opt => {
            if (opt.dataset.value == realSelect.value) {
                opt.classList.add('selected');
                valueSpan.textContent = opt.textContent;
                valueSpan.classList.remove('placeholder');
            }
        });
    }

    trigger.addEventListener('click', () => {
        const open = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', !open);
        optionsList.style.display = open ? 'none' : 'block';
    });

    options.forEach(opt => {
        opt.addEventListener('click', () => {
            options.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            valueSpan.textContent = opt.textContent;
            valueSpan.classList.remove('placeholder');
            if (realSelect) {
                realSelect.value = opt.dataset.value;
                realSelect.dispatchEvent(new Event('change'));
            }
            trigger.setAttribute('aria-expanded', 'false');
            optionsList.style.display = 'none';
        });
    });
    
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            trigger.setAttribute('aria-expanded', 'false');
            optionsList.style.display = 'none';
        }
    });
}
</script>

<script type="module">
    // --- MODAL DE CARACTERÍSTICAS ---
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('charModal');
    const openBtn = document.getElementById('openModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const cancelBtn = document.getElementById('cancelModalBtn');
    const saveBtn = document.getElementById('saveModalBtn');
    const allTagsInModal = document.querySelectorAll('.char-tag');
    const hiddenTagsContainer = document.getElementById('hidden-tags-container');
    const tagsPreview = document.getElementById('tagsPreview');
    const tagsPlaceholder = document.getElementById('tagsPlaceholder');
    
    if(!modal) return;

    let selectedTags = [];

    // Carrega tags pré-selecionadas (definidas no PHP)
    if(typeof existingCharacteristics !== 'undefined') {
        allTagsInModal.forEach(tag => {
            if (existingCharacteristics.includes(tag.dataset.value)) {
                tag.classList.add('active');
                const iconHTML = tag.querySelector('i:first-child').outerHTML;
                selectedTags.push({ value: tag.dataset.value, iconHTML: iconHTML });
            }
        });
        saveAndApplyTags();
    }

    function openModal() { modal.style.display = 'flex'; }
    function closeModal() { modal.style.display = 'none'; }

    if(openBtn) openBtn.addEventListener('click', openModal);
    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    if(cancelBtn) cancelBtn.addEventListener('click', closeModal);

    allTagsInModal.forEach(tag => {
        tag.addEventListener('click', () => {
            const container = tag.closest('.char-tags-container');
            const categoryType = container.dataset.category;
            const value = tag.dataset.value;
            const iconHTML = tag.querySelector('i:first-child').outerHTML;

            if (tag.classList.contains('active')) {
                tag.classList.remove('active');
                selectedTags = selectedTags.filter(t => t.value !== value);
            } else {
                // Lógica Single Choice
                if (categoryType === 'single-energy' || categoryType === 'single-training') {
                    const siblings = container.querySelectorAll('.char-tag.active');
                    siblings.forEach(sibling => {
                        sibling.classList.remove('active');
                        selectedTags = selectedTags.filter(t => t.value !== sibling.dataset.value);
                    });
                }
                tag.classList.add('active');
                selectedTags.push({ value: value, iconHTML: iconHTML });
            }
            if(saveBtn) saveBtn.textContent = `Salvar Seleção (${selectedTags.length})`;
        });
    });

    function saveAndApplyTags() {
        hiddenTagsContainer.innerHTML = '';
        tagsPreview.innerHTML = '';
        
        if (selectedTags.length > 0) {
            tagsPlaceholder.style.display = 'none';
            selectedTags.forEach(tag => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'caracteristicas[]';
                input.value = tag.value;
                hiddenTagsContainer.appendChild(input);
                
                const span = document.createElement('span');
                span.className = 'char-tag-input';
                span.innerHTML = tag.iconHTML + ' ' + tag.value;
                tagsPreview.appendChild(span);
            });
        } else {
            tagsPlaceholder.style.display = 'block';
        }
        
        // Atualiza campos dinâmicos
        if (typeof window.toggleDynamicFields === 'function') {
            window.toggleDynamicFields(selectedTags);
        }
    }

    if(saveBtn) {
        saveBtn.addEventListener('click', () => {
            saveAndApplyTags();
            closeModal();
        });
    }
});
</script>

</body>
</html>
