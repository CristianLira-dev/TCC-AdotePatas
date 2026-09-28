<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/security.php';
require_once dirname(__DIR__) . '/app/upload.php';

$failures = [];

if (appEscape('<script>') !== '&lt;script&gt;') {
    $failures[] = 'appEscape deve neutralizar tags HTML.';
}

$token = appCsrfToken();
if (strlen($token) !== 64 || !appCsrfIsValid($token)) {
    $failures[] = 'O token CSRF deve ter 32 bytes aleatórios e validar corretamente.';
}

if (appCsrfIsValid('token-invalido')) {
    $failures[] = 'Um token CSRF inválido não pode ser aceito.';
}

$protectedHtml = appInjectSecurityMarkup('<!doctype html><html><head></head><body><form method="post"></form></body></html>');
if (!str_contains($protectedHtml, 'name="csrf-token"')
    || !str_contains($protectedHtml, 'name="csrf_token"')
    || !str_contains($protectedHtml, 'assets/js/security.js')) {
    $failures[] = 'A proteção automática deve cobrir meta tag, formulários e fetch.';
}

$rateBucket = 'security-test-' . bin2hex(random_bytes(8));
if (!appRateLimit($rateBucket, 1, 60, 'test') || appRateLimit($rateBucket, 1, 60, 'test')) {
    $failures[] = 'O limitador deve bloquear tentativas acima do máximo.';
}

$imageMimes = appImageMimeMap();
if (($imageMimes['image/jpeg'] ?? null) !== 'jpg' || isset($imageMimes['image/svg+xml'])) {
    $failures[] = 'A lista de imagens deve aceitar formatos raster seguros e rejeitar SVG.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Testes de segurança concluídos com sucesso.\n");
