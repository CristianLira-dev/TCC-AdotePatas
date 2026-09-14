<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/ajuda.php';
$html = ob_get_clean();

// Corrige o card de Política de Privacidade para usar a rota limpa oficial.
$privacyUrl = htmlspecialchars(adotePatasUrl('politicas-privacidade/'), ENT_QUOTES, 'UTF-8');
$html = str_replace(
    [
        'href="politica-de-privacidade.php"',
        'href="politica-privacidade.php"',
        'href="politicas-privacidade.php"',
    ],
    'href="' . $privacyUrl . '"',
    $html
);

echo $html;
