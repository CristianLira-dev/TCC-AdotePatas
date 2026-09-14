<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/conexao.php';

// O chat legado reutiliza os mesmos placeholders nomeados mais de uma vez
// nas consultas de conversa. Habilitamos emulação apenas nesta rota para
// manter essas queries compatíveis sem alterar o comportamento global do PDO.
$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

ob_start();
require dirname(__DIR__) . '/chat.php';
$html = ob_get_clean();

$assetBase = ADOTE_PATAS_BASE_URL;
$baseUrl = htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8');

// Foto do próprio usuário logado para o header/offcanvas do chat.
$currentUserPhoto = null;
if (isset($_SESSION['user_id'], $_SESSION['user_tipo']) && in_array($_SESSION['user_tipo'], ['usuario', 'ong'], true)) {
    try {
        $currentTable = $_SESSION['user_tipo'] === 'usuario' ? 'usuario' : 'ong';
        $currentIdColumn = $_SESSION['user_tipo'] === 'usuario' ? 'id_usuario' : 'id_ong';
        $stmtCurrentPhoto = $conn->prepare("SELECT foto_perfil FROM {$currentTable} WHERE {$currentIdColumn} = :id LIMIT 1");
        $stmtCurrentPhoto->execute([':id' => (int) $_SESSION['user_id']]);
        $currentUserPhoto = $stmtCurrentPhoto->fetchColumn() ?: null;
    } catch (Throwable $e) {
        error_log('Erro ao carregar foto do usuário no chat: ' . $e->getMessage());
    }
}

// O chat legado ainda pode gerar caminhos locais com base em SERVER_NAME.
// Na rota por diretório, normaliza tudo para a base real calculada pelo roteamento.
$legacyLocalBase = '/TCC-AdotePatas/AdotePatas/';
if ($assetBase !== $legacyLocalBase) {
    $html = str_replace($legacyLocalBase, $assetBase, $html);
}

// Remove o link legado do chat para impedir caminhos relativos incorretos/cache antigo.
$html = preg_replace(
    '~<link[^>]+href="[^"]*assets/css/pages/chat/chat\.css[^"]*"[^>]*>~i',
    '',
    $html,
    1
);

// Carrega diretamente os estilos globais que já funcionam corretamente no servidor.
$chatStyles = <<<HTML
    <link rel="stylesheet" href="{$baseUrl}assets/css/global/global.css?v=20260913-4">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/header.css?v=20260913-4">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/offcanvas.css?v=20260913-4">
HTML;

// O InfinityFree estava entregando a página sem aplicar o chat.css principal.
// Para eliminar a dependência desse carregamento externo, incorporamos o CSS no HTML.
$chatCssPath = dirname(__DIR__) . '/assets/css/pages/chat/chat.css';
$chatCss = '';

if (is_readable($chatCssPath)) {
    $chatCss = file_get_contents($chatCssPath) ?: '';

    // Os arquivos globais/partials já são carregados acima.
    $chatCss = preg_replace('~@import\s+["\'][^"\']+["\']\s*;\s*~i', '', $chatCss);

    // Como o CSS passa a ficar inline, URLs relativas precisam partir da raiz do projeto.
    $wallpaperUrl = str_replace("'", "\\'", $assetBase . 'images/chat/wallpaper.jpg');
    $chatCss = str_replace(
        "url('../../../images/chat/wallpaper.jpg')",
        "url('{$wallpaperUrl}')",
        $chatCss
    );

    $chatStyles .= "\n<style id=\"chat-page-inline-styles\">\n{$chatCss}\n</style>";
}

$chatStyles .= <<<'CSS'
<style id="chat-profile-photo-styles">
.chat-avatar-icon-fallback{display:flex;align-items:center;justify-content:center;color:var(--cor-vermelho,#b65c52);background:#fff7f5;font-size:1.6rem;border:1px solid rgba(0,0,0,.06)}
.profile-user-photo{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--cor-rosa-pastel,#f0c9c4);display:block}
.sidebar-user-photo{width:82px;height:82px;border-radius:50%;object-fit:cover;border:3px solid var(--cor-rosa-pastel,#f0c9c4);display:inline-block}
</style>
CSS;

$html = str_replace('</head>', $chatStyles . "\n</head>", $html);

// Garante explicitamente o caminho correto do JavaScript principal do chat.
$html = preg_replace(
    '~src="[^"]*assets/js/pages/chat/file-size-upload\.js"~',
    'src="' . $baseUrl . 'assets/js/pages/chat/file-size-upload.js?v=20260913-4"',
    $html,
    1
);

// Corrige o endpoint de polling para o nome real do arquivo.
$html = str_replace('buscar_mensagens.php', 'buscar-mensagens.php', $html);

// Sem RewriteRule, as conversas usam query string: /chat/?id=123.
$html = preg_replace(
    '~href="([^"<>]*?)chat/([0-9]+)/?"~',
    'href="$1chat/?id=$2"',
    $html
);

// Remove definitivamente o placeholder teste.jpg dos avatares do chat.
// Sem foto, usa ícone. Se uma foto salva falhar ao carregar, cai para o mesmo ícone.
$html = preg_replace_callback(
    '~<img\b(?=[^>]*\bclass="[^"]*\bchat-avatar\b[^"]*")[^>]*>~i',
    static function (array $match): string {
        $tag = $match[0];
        $src = '';

        if (preg_match('~\bsrc="([^"]*)"~i', $tag, $srcMatch)) {
            $src = html_entity_decode($srcMatch[1], ENT_QUOTES, 'UTF-8');
        }

        $fallback = '<span class="chat-avatar chat-avatar-icon-fallback" aria-label="Usuário sem foto"><i class="fa-regular fa-circle-user"></i></span>';

        if ($src === '' || str_contains($src, 'images/perfil/teste.jpg')) {
            return $fallback;
        }

        $tag = preg_replace('~\s+onerror="[^"]*"~i', '', $tag) ?: $tag;
        $tag = preg_replace(
            '~>$~',
            ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">',
            $tag,
            1
        ) ?: $tag;

        $hiddenFallback = '<span class="chat-avatar chat-avatar-icon-fallback" style="display:none" aria-label="Usuário sem foto"><i class="fa-regular fa-circle-user"></i></span>';

        return $tag . $hiddenFallback;
    },
    $html
);

// Usa a foto do próprio usuário no header e no offcanvas quando disponível.
if ($currentUserPhoto) {
    $currentPhotoUrl = htmlspecialchars($assetBase . ltrim($currentUserPhoto, '/'), ENT_QUOTES, 'UTF-8');

    $headerAvatar = '<img src="' . $currentPhotoUrl . '" alt="Minha foto de perfil" class="profile-user-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user profile-icon logged-in" style="display:none"></i>';
    $sidebarAvatar = '<img src="' . $currentPhotoUrl . '" alt="Minha foto de perfil" class="sidebar-user-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user sidebar-profile-icon logged-in" style="display:none"></i>';

    $html = str_replace(
        '<i class="fa-regular fa-circle-user profile-icon logged-in"></i>',
        $headerAvatar,
        $html
    );
    $html = str_replace(
        '<i class="fa-regular fa-circle-user sidebar-profile-icon logged-in"></i>',
        $sidebarAvatar,
        $html
    );
}

// Ajusta a experiência mobile: lista primeiro; conversa em tela cheia quando selecionada.
if (!empty($_GET['id'])) {
    $html = str_replace(
        'class="col-lg-4 col-md-5 col-12 chat-sidebar"',
        'class="col-lg-4 col-md-5 col-12 chat-sidebar d-none d-md-block"',
        $html
    );

    $html = str_replace(
        'class="col-lg-8 col-md-7 d-none d-md-flex chat-conversation-area"',
        'class="col-lg-8 col-md-7 col-12 d-flex chat-conversation-area"',
        $html
    );

    $backButton = '<a href="' . htmlspecialchars($assetBase . 'chat/', ENT_QUOTES, 'UTF-8') . '" class="d-md-none me-2 text-decoration-none" aria-label="Voltar para conversas" style="color: var(--cor-vermelho); font-size: 1.25rem;"><i class="fa-solid fa-arrow-left"></i></a>';
    $html = str_replace(
        '<div class="chat-active-header">',
        '<div class="chat-active-header">' . $backButton,
        $html
    );
}

echo $html;
