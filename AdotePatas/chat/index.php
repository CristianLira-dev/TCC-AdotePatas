<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/chat.php';
$html = ob_get_clean();

$assetBase = ADOTE_PATAS_BASE_URL;
$baseUrl = htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8');

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
    <link rel="stylesheet" href="{$baseUrl}assets/css/global/global.css?v=20260913-3">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/header.css?v=20260913-3">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/offcanvas.css?v=20260913-3">
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

$html = str_replace('</head>', $chatStyles . "\n</head>", $html);

// Garante explicitamente o caminho correto do JavaScript principal do chat.
$html = preg_replace(
    '~src="[^"]*assets/js/pages/chat/file-size-upload\.js"~',
    'src="' . $baseUrl . 'assets/js/pages/chat/file-size-upload.js?v=20260913-3"',
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
