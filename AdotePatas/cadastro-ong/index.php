<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/registration-email-hook.php';
$_GET['tab'] = 'cadastro_ong';
require dirname(__DIR__) . '/autenticacao.php';
