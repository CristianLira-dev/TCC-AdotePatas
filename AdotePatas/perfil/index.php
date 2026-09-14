<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/perfil.php';
$html = ob_get_clean();

// O banner1 continua disponível para perfis que já o utilizam, mas não pode mais
// ser escolhido novamente no modal de seleção.
$html = preg_replace(
    "~\\s*<div class='col-6 col-md-4 mb-3'>\\s*<div class='banner-option[^']*' data-banner='banner1\\.jpg'>\\s*<img[^>]*>\\s*(?:<div class='badge[^>]*>.*?</div>)?\\s*</div>\\s*</div>~s",
    '',
    $html,
    1
);

$baseUrl = ADOTE_PATAS_BASE_URL;
$fotoPerfil = null;
$paginaAtual = $_GET['page'] ?? 'perfil';

// Usa uma versão fixa do player para evitar mudanças inesperadas no @latest.
$html = str_replace(
    'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js',
    'https://unpkg.com/@lottiefiles/lottie-player@2.0.12/dist/lottie-player.js',
    $html
);

// As rotas por diretório podem fazer o navegador resolver incorretamente o caminho
// relativo da pasta com acento. Normalizamos todos os Lotties para a raiz do site.
$lottieBaseUrl = $baseUrl . 'anima%C3%A7%C3%B5es/';
$html = str_replace('src="animações/', 'src="' . $lottieBaseUrl, $html);
$html = str_replace("src='animações/", "src='" . $lottieBaseUrl, $html);

if (isset($_SESSION['user_id'], $_SESSION['user_tipo']) && in_array($_SESSION['user_tipo'], ['usuario', 'ong'], true)) {
    try {
        $table = $_SESSION['user_tipo'] === 'usuario' ? 'usuario' : 'ong';
        $idColumn = $_SESSION['user_tipo'] === 'usuario' ? 'id_usuario' : 'id_ong';

        $stmtFoto = $conn->prepare("SELECT foto_perfil FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
        $stmtFoto->execute([':id' => (int) $_SESSION['user_id']]);
        $fotoPerfil = $stmtFoto->fetchColumn() ?: null;
    } catch (Throwable $e) {
        error_log('Erro ao carregar foto de perfil: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['profile_photo_csrf'])) {
    $_SESSION['profile_photo_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['profile_photo_csrf'];
$fotoUrl = $fotoPerfil ? $baseUrl . ltrim($fotoPerfil, '/') : null;
$endpointUrl = $baseUrl . 'atualizar-foto-perfil/';

if ($fotoUrl) {
    $sidebarPhoto = '<img src="' . htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Foto de perfil" class="sidebar-profile-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user sidebar-profile-icon" style="display:none"></i>';
    $html = str_replace(
        '<i class="fa-regular fa-circle-user sidebar-profile-icon"></i>',
        $sidebarPhoto,
        $html
    );
}

// Este estilo precisa existir em todas as abas do perfil, não apenas em "Meu Perfil".
$sharedStyles = <<<'CSS'
<style id="profile-route-shared-styles">
.sidebar-header .sidebar-profile-photo{
    width:72px;
    height:72px;
    aspect-ratio:1 / 1;
    border-radius:50%;
    object-fit:cover;
    object-position:center;
    border:3px solid var(--cor-rosa-pastel,#f0c9c4);
    display:inline-block;
    flex-shrink:0;
}
@media(max-width:991.98px){
    .sidebar-header .sidebar-profile-photo{width:64px;height:64px}
}
</style>
CSS;
$html = str_replace('</head>', $sharedStyles . "\n</head>", $html);

if ($paginaAtual === 'perfil' && in_array($_SESSION['user_tipo'] ?? '', ['usuario', 'ong'], true)) {
    $avatarMarkup = $fotoUrl
        ? '<img id="profile-photo-preview" src="' . htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Foto de perfil">'
        : '<div id="profile-photo-fallback" class="profile-photo-fallback"><i class="fa-regular fa-circle-user"></i></div>';

    $endpointEscaped = htmlspecialchars($endpointUrl, ENT_QUOTES, 'UTF-8');
    $csrfEscaped = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

    $removeButton = $fotoUrl
        ? '<form action="' . $endpointEscaped . '" method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="' . $csrfEscaped . '"><input type="hidden" name="action" value="remove"><button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1"></i> Remover foto</button></form>'
        : '';

    $editor = <<<HTML
<div class="profile-photo-editor">
    <div class="profile-photo-avatar" id="profile-photo-avatar">
        {$avatarMarkup}
    </div>
    <div class="profile-photo-actions">
        <strong>Foto de perfil</strong>
        <small>JPG, PNG ou WEBP de até 5 MB.</small>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <form action="{$endpointEscaped}" method="POST" enctype="multipart/form-data" id="profile-photo-form" class="d-inline">
                <input type="hidden" name="csrf_token" value="{$csrfEscaped}">
                <input type="hidden" name="action" value="upload">
                <label for="profile-photo-input" class="btn btn-danger btn-sm mb-0">
                    <i class="fa-solid fa-camera me-1"></i> Alterar foto
                </label>
                <input type="file" id="profile-photo-input" name="foto_perfil" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()">
            </form>
            {$removeButton}
        </div>
    </div>
</div>
HTML;

    $html = str_replace('<h1>Meu Perfil</h1>', $editor . "\n<h1>Meu Perfil</h1>", $html);

    $styles = <<<'CSS'
<style id="profile-photo-styles">
.profile-photo-editor{display:flex;align-items:center;gap:1rem;margin:1.25rem 0 1.5rem;padding:1rem 1.1rem;border:1px solid rgba(0,0,0,.08);border-radius:16px;background:var(--cor-branca,#fff)}
.profile-photo-avatar{width:96px;height:96px;border-radius:50%;overflow:hidden;flex:0 0 96px;background:#f4f4f4;display:flex;align-items:center;justify-content:center;border:3px solid var(--cor-rosa-pastel,#f0c9c4)}
.profile-photo-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.profile-photo-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--cor-vermelho,#b65c52);font-size:3rem;background:#fff7f5}
.profile-photo-actions{display:flex;flex-direction:column;align-items:flex-start}
.profile-photo-actions small{color:#6c757d;margin-top:.15rem}
@media(max-width:575.98px){.profile-photo-editor{align-items:flex-start}.profile-photo-avatar{width:78px;height:78px;flex-basis:78px}.profile-photo-actions .btn{font-size:.78rem}}
</style>
CSS;

    $html = str_replace('</head>', $styles . "\n</head>", $html);
}

echo $html;
