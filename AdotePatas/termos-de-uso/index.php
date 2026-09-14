<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/termos-de-uso.php';
$html = ob_get_clean();

$baseUrl = ADOTE_PATAS_BASE_URL;
$homeUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
$privacyUrl = htmlspecialchars($baseUrl . 'politicas-privacidade/', ENT_QUOTES, 'UTF-8');
$faviconUrl = htmlspecialchars($baseUrl . 'images/global/Logo-AdotePatas.png', ENT_QUOTES, 'UTF-8');

// Mantém todos os links desta página no padrão de rotas limpas do projeto.
$links = [
    'href="ajuda.php"' => 'href="' . $baseUrl . 'ajuda/"',
    'href="chat.php"' => 'href="' . $baseUrl . 'chat/"',
    'href="sair.php"' => 'href="' . $baseUrl . 'sair/"',
    'href="login"' => 'href="' . $baseUrl . 'login/"',
    'href="sobre-nos"' => 'href="' . $baseUrl . 'sobre-nos/"',
    'href="perfil?page=perfil"' => 'href="' . $baseUrl . 'perfil/?page=perfil"',
    'href="perfil?page=meus-pets"' => 'href="' . $baseUrl . 'perfil/?page=meus-pets"',
    'href="perfil?page=pets-curtidos"' => 'href="' . $baseUrl . 'perfil/?page=pets-curtidos"',
    'href="./"' => 'href="' . $homeUrl . '"',
];
$html = str_replace(array_keys($links), array_values($links), $html);

if (stripos($html, 'rel="icon"') === false) {
    $html = str_replace(
        '</head>',
        '    <link rel="icon" type="image/png" href="' . $faviconUrl . '">' . "\n</head>",
        $html
    );
}

// Facilita a navegação entre os dois documentos legais.
$crossLink = '<p class="mt-3 mb-0"><a href="' . $privacyUrl . '" class="text-decoration-underline">Ler também a Política de Privacidade</a></p>';
$html = str_replace(
    '<p class="data-atualizacao fs-6">Última atualização: 09/11/2025</p>',
    '<p class="data-atualizacao fs-6">Última atualização: 09/11/2025</p>' . $crossLink,
    $html
);

echo $html;
