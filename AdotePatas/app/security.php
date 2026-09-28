<?php

declare(strict_types=1);

if (!defined('ADOTE_PATAS_SECURITY_LOADED')) {
    define('ADOTE_PATAS_SECURITY_LOADED', true);

    function appIsHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    function appStartSession(): void
    {
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('adotepatas_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => appIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $now = time();
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);
        if ($now - $lastActivity > 7200) {
            session_unset();
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = $now;
    }

    function appApplySecurityHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self' https: data: blob:; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data: blob:; connect-src 'self' https: wss: ws:");

        if (appIsHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    function appCsrfToken(): string
    {
        appStartSession();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    function appRequestCsrfToken(): string
    {
        $header = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($header !== '') {
            return $header;
        }

        return trim((string) ($_POST['csrf_token'] ?? ''));
    }

    function appCsrfIsValid(?string $token = null): bool
    {
        appStartSession();
        $expected = (string) ($_SESSION['_csrf_token'] ?? '');
        $received = $token ?? appRequestCsrfToken();
        return $expected !== '' && $received !== '' && hash_equals($expected, $received);
    }

    function appIsJsonRequest(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_contains($contentType, 'application/json');
    }

    function appEnforceCsrf(): void
    {
        if (PHP_SAPI === 'cli' || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (appCsrfIsValid()) {
            return;
        }

        http_response_code(419);
        if (appIsJsonRequest()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Sua sessão expirou. Atualize a página e tente novamente.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Sessão expirada</title>'
                . '<body><main><h1>Sessão expirada</h1><p>Atualize a página e tente novamente.</p>'
                . '<p><a href="javascript:history.back()">Voltar</a></p></main></body></html>';
        }
        exit;
    }

    function appEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function appCompleteLogin(int $userId, string $userType, string $name, string $email): void
    {
        appStartSession();
        session_regenerate_id(true);
        $_SESSION['nome'] = $name;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_tipo'] = $userType;
        $_SESSION['_last_activity'] = time();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    function appClientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    function appRateLimit(string $bucket, int $maximumAttempts, int $windowSeconds, ?string $identity = null): bool
    {
        $identity ??= appClientIp();
        $key = hash('sha256', $bucket . '|' . $identity);
        $directory = sys_get_temp_dir() . '/adotepatas-rate-limit';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return true;
        }

        $path = $directory . '/' . $key . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return true;
        }

        $raw = stream_get_contents($handle);
        $state = json_decode($raw ?: '', true);
        $now = time();
        if (!is_array($state) || ($state['reset_at'] ?? 0) <= $now) {
            $state = ['attempts' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $allowed = (int) $state['attempts'] < $maximumAttempts;
        if ($allowed) {
            $state['attempts'] = (int) $state['attempts'] + 1;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $allowed;
    }

    function appInjectSecurityMarkup(string $html): string
    {
        if (stripos($html, '<html') === false && stripos($html, '<!doctype') === false) {
            return $html;
        }

        $token = appEscape(appCsrfToken());
        if (stripos($html, 'name="csrf-token"') === false) {
            $html = preg_replace('/<head\b[^>]*>/i', '$0' . "\n<meta name=\"csrf-token\" content=\"{$token}\">", $html, 1) ?? $html;
        }

        $html = preg_replace_callback(
            '/<form\b([^>]*)>/i',
            static function (array $match) use ($token): string {
                if (!preg_match('/\bmethod\s*=\s*(["\']?)post\1/i', $match[1])) {
                    return $match[0];
                }
                return $match[0] . '<input type="hidden" name="csrf_token" value="' . $token . '">';
            },
            $html
        ) ?? $html;

        if (stripos($html, 'assets/js/security.js') === false) {
            $html = str_ireplace('</body>', '<script src="assets/js/security.js" defer></script>' . "\n</body>", $html);
        }
        return $html;
    }

    appStartSession();
    appApplySecurityHeaders();

    if (PHP_SAPI !== 'cli' && !defined('ADOTE_PATAS_DISABLE_HTML_INJECTION')) {
        ob_start('appInjectSecurityMarkup');
    }
}
