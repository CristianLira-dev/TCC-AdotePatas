<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
$_GET['tab'] = 'login';

ob_start();
require dirname(__DIR__) . '/autenticacao.php';
$html = ob_get_clean();

// Usa exatamente a mesma estratégia da rota /perfil/: os arquivos Lottie
// recebem uma URL absoluta na raiz da aplicação antes de o HTML ser enviado.
$lottieBaseUrl = ADOTE_PATAS_BASE_URL . 'anima%C3%A7%C3%B5es/';
$html = str_replace('src="animações/', 'src="' . $lottieBaseUrl, $html);
$html = str_replace("src='animações/", "src='" . $lottieBaseUrl, $html);
$html = str_replace('src="anima%C3%A7%C3%B5es/', 'src="' . $lottieBaseUrl, $html);
$html = str_replace("src='anima%C3%A7%C3%B5es/", "src='" . $lottieBaseUrl, $html);

// Disponibiliza a mesma base absoluta para os Lotties criados via JavaScript
// (toasts), que não passam pela transformação de HTML do PHP.
$lottieMeta = '<meta name="adotepatas-lottie-base" content="' . htmlspecialchars($lottieBaseUrl, ENT_QUOTES, 'UTF-8') . '">';
$html = str_replace('</head>', '    ' . $lottieMeta . "\n</head>", $html);

// Evita que o navegador mantenha em cache a versão antiga dos módulos de autenticação.
$html = str_replace(
    'assets/js/pages/autenticacao/autenticacao.js" type="module"',
    'assets/js/pages/autenticacao/autenticacao.js?v=20260914-2" type="module"',
    $html
);

echo $html;
