<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/chat.php';
$html = ob_get_clean();

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

    $backButton = '<a href="' . htmlspecialchars(ADOTE_PATAS_BASE_URL . 'chat/', ENT_QUOTES, 'UTF-8') . '" class="d-md-none me-2 text-decoration-none" aria-label="Voltar para conversas" style="color: var(--cor-vermelho); font-size: 1.25rem;"><i class="fa-solid fa-arrow-left"></i></a>';
    $html = str_replace(
        '<div class="chat-active-header">',
        '<div class="chat-active-header">' . $backButton,
        $html
    );
}

echo $html;
