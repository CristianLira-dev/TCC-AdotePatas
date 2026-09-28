<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/session.php';
require_once dirname(__DIR__) . '/email-service.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

$respond = static function (bool $success, string $email = '', string $error = '') use ($isAjax): never {
    if ($isAjax) {
        http_response_code($success ? 200 : ($error === 'invalid_email' ? 422 : 500));
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'email' => $success ? $email : null,
            'error' => $success ? null : $error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($success) {
        header('Location: ' . adotePatasUrl('login/?active_tab=recuperar&recovery_success=true&email=' . urlencode($email)));
        exit;
    }

    header('Location: ' . adotePatasUrl('login/?active_tab=recuperar&recovery_error=' . urlencode($error)));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adotePatasUrl('login/'));
    exit;
}

$email = strtolower(trim((string) ($_POST['email_recuperar'] ?? '')));

if (!appRateLimit('password-recovery', 10, 3600, appClientIp())) {
    $respond(true, $email);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(false, '', 'invalid_email');
}

$tokenStored = false;

try {
    $stmtUser = $conn->prepare('SELECT email FROM usuario WHERE email = :email LIMIT 1');
    $stmtUser->execute([':email' => $email]);
    $userFound = (bool) $stmtUser->fetchColumn();

    $stmtOng = $conn->prepare('SELECT email FROM ong WHERE email = :email LIMIT 1');
    $stmtOng->execute([':email' => $email]);
    $ongFound = (bool) $stmtOng->fetchColumn();

    // Resposta genérica para não revelar se o endereço está cadastrado.
    if (!$userFound && !$ongFound) {
        $respond(true, $email);
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $conn->beginTransaction();

    $stmtDelete = $conn->prepare(
        'DELETE FROM recuperar_senha_tolken WHERE email = :email OR expires_at <= :now'
    );
    $stmtDelete->execute([
        ':email' => $email,
        ':now' => date('Y-m-d H:i:s'),
    ]);

    $stmtInsert = $conn->prepare(
        'INSERT INTO recuperar_senha_tolken (email, token, expires_at) VALUES (:email, :token, :expires_at)'
    );
    $stmtInsert->execute([
        ':email' => $email,
        ':token' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    $conn->commit();
    $tokenStored = true;

    $resetLink = adotePatasAppUrl() . '/trocar-senha/?token=' . rawurlencode($token);
    $sent = adotePatasSendEmail(
        $email,
        '',
        'Redefinir senha - Adote Patas',
        'Redefinição de senha',
        'Recebemos uma solicitação para redefinir sua senha. O link abaixo é válido por 1 hora. Se você não fez essa solicitação, ignore esta mensagem.',
        'Redefinir minha senha',
        $resetLink
    );
    if (!$sent) {
        throw new RuntimeException('Falha ao enviar e-mail de recuperação.');
    }
    $respond(true, $email);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    if ($tokenStored) {
        try {
            $cleanup = $conn->prepare('DELETE FROM recuperar_senha_tolken WHERE email = :email');
            $cleanup->execute([':email' => $email]);
        } catch (Throwable $cleanupError) {
            error_log('Falha ao limpar token de recuperação: ' . $cleanupError->getMessage());
        }
    }

    error_log('Erro na recuperação de senha: ' . $e->getMessage());
    // Mantém resposta indistinguível para impedir enumeração de contas.
    $respond(true, $email);
}
