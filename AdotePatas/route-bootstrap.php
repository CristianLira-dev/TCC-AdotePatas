<?php

if (!defined('ADOTE_PATAS_ROUTING_LOADED')) {
    define('ADOTE_PATAS_ROUTING_LOADED', true);
    chdir(__DIR__);

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $scriptDir = str_replace('\\', '/', dirname($scriptName));

    if (defined('ADOTE_PATAS_ROUTE_WRAPPER') && ADOTE_PATAS_ROUTE_WRAPPER) {
        $scriptDir = str_replace('\\', '/', dirname($scriptDir));
    }

    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '' || $scriptDir === '.' || $scriptDir === '/') {
        $scriptDir = '';
    }

    define('ADOTE_PATAS_BASE_URL', $scriptDir . '/');

    function adotePatasRouteMap(): array
    {
        return [
            'Sobre-nos.php' => 'sobre-nos/',
            'ajuda.php' => 'ajuda/',
            'autenticacao.php' => 'login/',
            'cadastrar-pet.php' => 'cadastrar-pet/',
            'chat.php' => 'chat/',
            'como-adotar.php' => 'como-adotar/',
            'editar-pet.php' => 'editar-pet/',
            'formulario-adocao.php' => 'formulario-adocao/',
            'ongs-parceiras.php' => 'ongs-parceiras/',
            'perfil.php' => 'perfil/',
            'pet-detalhe.php' => 'pet-detalhe/',
            'pets-adocao.php' => 'pets/',
            'politicas-privacidade.php' => 'politicas-privacidade/',
            'recuperar-senha.php' => 'recuperar-senha/',
            'termos-de-uso.php' => 'termos-de-uso/',
            'trocar-senha.php' => 'trocar-senha/',
            'verificar-email.php' => 'verificar-email/',
            'adocao-sucesso.php' => 'adocao-sucesso/',
            'sair.php' => 'sair/',
        ];
    }

    function adotePatasUrl(string $path = ''): string
    {
        return ADOTE_PATAS_BASE_URL . ltrim($path, '/');
    }

    function adotePatasNormalizeLocation(string $location): string
    {
        $location = trim($location);
        if ($location === '' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $location) || str_starts_with($location, '//')) {
            return $location;
        }

        if ($location === '.' || $location === './' || $location === 'index.php' || $location === './index.php') {
            return adotePatasUrl();
        }

        $relative = preg_replace('~^\./~', '', $location);
        $relative = ltrim($relative, '/');

        $authRoutes = [
            'autenticacao.php?tab=login' => 'login/',
            'autenticacao.php?tab=cadastro_usuario' => 'cadastro/',
            'autenticacao.php?tab=cadastro_ong' => 'cadastro-ong/',
        ];

        foreach ($authRoutes as $legacy => $clean) {
            if ($relative === $legacy) {
                return adotePatasUrl($clean);
            }
        }

        if (str_starts_with($relative, 'perfil?')) {
            return adotePatasUrl('perfil/?' . substr($relative, strlen('perfil?')));
        }

        foreach (adotePatasRouteMap() as $legacy => $clean) {
            if ($relative === $legacy) {
                return adotePatasUrl($clean);
            }
            if (str_starts_with($relative, $legacy . '?')) {
                return adotePatasUrl($clean . '?' . substr($relative, strlen($legacy) + 1));
            }
        }

        $aliases = [
            'login' => 'login/', 'cadastro' => 'cadastro/', 'cadastro-ong' => 'cadastro-ong/',
            'pets' => 'pets/', 'perfil' => 'perfil/', 'sobre-nos' => 'sobre-nos/',
            'ajuda' => 'ajuda/', 'chat' => 'chat/', 'cadastrar-pet' => 'cadastrar-pet/'
        ];

        return isset($aliases[$relative]) ? adotePatasUrl($aliases[$relative]) : $location;
    }

    if (function_exists('header_register_callback')) {
        header_register_callback(function (): void {
            foreach (headers_list() as $headerLine) {
                if (stripos($headerLine, 'Location:') !== 0) {
                    continue;
                }
                $location = trim(substr($headerLine, strlen('Location:')));
                $normalized = adotePatasNormalizeLocation($location);
                if ($normalized !== $location && $normalized !== '') {
                    header_remove('Location');
                    header('Location: ' . $normalized);
                }
                break;
            }
        });
    }

    ob_start(function (string $buffer): string {
        if (stripos($buffer, '<html') === false && stripos($buffer, '<!DOCTYPE') === false) {
            return $buffer;
        }

        if (stripos($buffer, '<base ') === false) {
            $baseUrl = htmlspecialchars(ADOTE_PATAS_BASE_URL, ENT_QUOTES, 'UTF-8');
            $buffer = preg_replace_callback('/<head\b[^>]*>/i', static fn(array $match): string => $match[0] . "\n  <base href=\"{$baseUrl}\">", $buffer, 1);
        }

        $specific = [
            'autenticacao.php?tab=cadastro_usuario' => 'cadastro/',
            'autenticacao.php?tab=cadastro_ong' => 'cadastro-ong/',
            'autenticacao.php?tab=login' => 'login/',
            '?tab=cadastro_usuario' => 'cadastro/',
            '?tab=cadastro_ong' => 'cadastro-ong/',
            '?tab=login' => 'login/',
        ];
        $buffer = str_replace(array_keys($specific), array_values($specific), $buffer);

        foreach (adotePatasRouteMap() as $legacy => $clean) {
            $buffer = str_replace($legacy, $clean, $buffer);
        }

        // Directory routes must include the trailing slash on form actions.
        // Without it Apache redirects /login -> /login/ and a POST may be converted
        // to GET, discarding credentials and registration data.
        $buffer = preg_replace_callback(
            '~(<form\b[^>]*\baction=)(["\'])(login|cadastro|cadastro-ong|perfil|pets|chat|cadastrar-pet)\2~i',
            static function (array $match): string {
                $route = strtolower($match[3]) . '/';
                $url = htmlspecialchars(adotePatasUrl($route), ENT_QUOTES, 'UTF-8');
                return $match[1] . $match[2] . $url . $match[2];
            },
            $buffer
        );

        $buffer = preg_replace('~pet-detalhe/([0-9]+)/?~', 'pet-detalhe/?id=$1', $buffer);
        $buffer = preg_replace('~formulario/([0-9]+)/?~', 'formulario-adocao/?id=$1', $buffer);
        $buffer = preg_replace('~chat/([0-9]+)/?~', 'chat/?id=$1', $buffer);
        $buffer = preg_replace('~perfil\?([^"\'<>\s]+)~', 'perfil/?$1', $buffer);

        // O chat ocupa toda a área útil da viewport. Mantemos este CSS separado
        // para não misturar regras específicas de layout com o roteamento legado.
        if (stripos($buffer, 'class="chat-page-body"') !== false && stripos($buffer, 'chat/fullscreen.css') === false) {
            $layoutUrl = htmlspecialchars(
                adotePatasUrl('assets/css/pages/chat/fullscreen.css?v=20260914-1'),
                ENT_QUOTES,
                'UTF-8'
            );
            $buffer = str_replace(
                '</head>',
                '  <link rel="stylesheet" href="' . $layoutUrl . '">' . "\n</head>",
                $buffer
            );
        }

        return $buffer;
    });
}
