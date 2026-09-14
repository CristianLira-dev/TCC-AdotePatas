<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/session.php';

use PHPMailer\PHPMailer\PHPMailer;

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

$email = trim((string) ($_POST['email_recuperar'] ?? ''));

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
        ':token' => $token,
        ':expires_at' => $expiresAt,
    ]);

    $conn->commit();
    $tokenStored = true;

    $smtpHost = trim((string) ($env['SMTP_HOST'] ?? 'smtp.gmail.com'));
    $smtpPort = (int) ($env['SMTP_PORT'] ?? 465);
    $smtpSecure = strtolower(trim((string) ($env['SMTP_SECURE'] ?? 'ssl')));
    $smtpUser = trim((string) ($env['SMTP_USER'] ?? ''));
    $smtpPassword = (string) ($env['SMTP_PASSWORD'] ?? '');
    $fromAddress = trim((string) ($env['MAIL_FROM_ADDRESS'] ?? $smtpUser));
    $fromName = trim((string) ($env['MAIL_FROM_NAME'] ?? 'Adote Patas - Suporte'));
    $appUrl = rtrim(trim((string) ($env['APP_URL'] ?? 'https://adotepatas.page.gd')), '/');

    if ($smtpUser === '' || $smtpPassword === '' || $fromAddress === '') {
        throw new RuntimeException('Configuração SMTP ausente no ambiente.');
    }

    $resetLink = $appUrl . '/trocar-senha/?token=' . rawurlencode($token);
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeResetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    $mail->Timeout = 15;
    $mail->SMTPSecure = ($smtpSecure === 'tls' || $smtpSecure === 'starttls')
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Redefinir senha - Adote Patas';
    $mail->Body = <<<HTML
<!doctype html>
<html lang="pt-BR">
<body style="margin:0;padding:0;background:#f7f4f3;font-family:Arial,sans-serif;color:#444;">
    <div style="max-width:600px;margin:0 auto;padding:32px 18px;">
        <div style="background:#fff;border-radius:18px;padding:32px;border:1px solid #f0dfdc;">
            <h1 style="margin:0 0 18px;color:#bf6964;font-size:26px;">Redefinição de senha</h1>
            <p style="line-height:1.7;margin:0 0 14px;">Olá!</p>
            <p style="line-height:1.7;margin:0 0 14px;">Recebemos uma solicitação para redefinir a senha da conta vinculada ao e-mail <strong>{$safeEmail}</strong>.</p>
            <p style="line-height:1.7;margin:0 0 24px;">O link abaixo é válido por <strong>1 hora</strong>.</p>
            <p style="margin:28px 0;text-align:center;">
                <a href="{$safeResetLink}" style="display:inline-block;background:#bf6964;color:#fff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:700;">Redefinir minha senha</a>
            </p>
            <p style="line-height:1.7;margin:24px 0 8px;">Se você não solicitou a alteração, pode ignorar este e-mail.</p>
            <p style="line-height:1.7;margin:0;color:#777;font-size:13px;">Por segurança, não compartilhe este link com outras pessoas.</p>
        </div>
        <p style="text-align:center;color:#999;font-size:12px;margin-top:18px;">Adote Patas</p>
    </div>
</body>
</html>
HTML;
    $mail->AltBody = "Recebemos uma solicitação para redefinir sua senha no Adote Patas. O link é válido por 1 hora: {$resetLink}";

    $mail->send();
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
    $respond(false, '', 'internal_error');
}
