<?php
define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
$_GET['tab'] = 'cadastro_usuario';
require dirname(__DIR__) . '/autenticacao.php';
