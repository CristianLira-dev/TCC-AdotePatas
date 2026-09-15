<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function adotePatasEmailEnv(string $key, string $default = ''): string
{
    global $env;

    if (isset($env) && is_array($env) && array_key_exists($key, $env)) {
        return trim((string) $env[$key]);
    }

    if (array_key_exists($key, $_ENV)) {
        return trim((string) $_ENV[$key]);
    }

    $value = getenv($key);
    return $value !== false ? trim((string) $value) : $default;
}

function adotePatasAppUrl(): string
{
    $url = adotePatasEmailEnv('APP_URL', 'https://adotepatas.page.gd');
    return rtrim($url !== '' ? $url : 'https://adotepatas.page.gd', '/');
}

function adotePatasSendEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $title,
    string $message,
    ?string $buttonLabel = null,
    ?string $buttonUrl = null
): bool {
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('E-mail transacional não enviado: destinatário inválido.');
        return false;
    }

    $smtpHost = adotePatasEmailEnv('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = (int) adotePatasEmailEnv('SMTP_PORT', '465');
    $smtpSecure = strtolower(adotePatasEmailEnv('SMTP_SECURE', 'ssl'));
    $smtpUser = adotePatasEmailEnv('SMTP_USER');
    $smtpPassword = adotePatasEmailEnv('SMTP_PASSWORD');
    $fromAddress = adotePatasEmailEnv('MAIL_FROM_ADDRESS', $smtpUser);
    $fromName = adotePatasEmailEnv('MAIL_FROM_NAME', 'Adote Patas - Suporte');

    if ($smtpUser === '' || $smtpPassword === '' || $fromAddress === '') {
        error_log('E-mail transacional não enviado: configuração SMTP incompleta.');
        return false;
    }

    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Olá', ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $appUrl = adotePatasAppUrl();
    $logoUrl = $appUrl . '/images/global/Logo-AdotePatas.png';

    $buttonHtml = '';
    if ($buttonLabel !== null && $buttonLabel !== '' && $buttonUrl !== null && $buttonUrl !== '') {
        $safeButtonLabel = htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8');
        $safeButtonUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
        $buttonHtml = <<<HTML
            <p style="margin:28px 0 8px;text-align:center;">
                <a href="{$safeButtonUrl}" style="display:inline-block;background:#b8655b;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:999px;">{$safeButtonLabel}</a>
            </p>
        HTML;
    }

    $body = <<<HTML
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$safeTitle}</title>
    </head>
    <body style="margin:0;padding:0;background:#f7f3ef;font-family:Arial,Helvetica,sans-serif;color:#4f4f4f;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f3ef;padding:28px 12px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08);">
                        <tr>
                            <td align="center" style="padding:28px 28px 12px;">
                                <img src="{$logoUrl}" alt="Adote Patas" width="86" height="86" style="display:block;border:0;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 34px 34px;">
                                <h1 style="margin:0 0 18px;text-align:center;color:#b8655b;font-size:26px;line-height:1.25;">{$safeTitle}</h1>
                                <p style="font-size:16px;line-height:1.65;margin:0 0 14px;">Olá, <strong>{$safeName}</strong>!</p>
                                <p style="font-size:16px;line-height:1.65;margin:0;">{$safeMessage}</p>
                                {$buttonHtml}
                                <p style="font-size:13px;line-height:1.5;color:#888888;text-align:center;margin:30px 0 0;">Adote Patas • conectando pessoas e pets a novas histórias.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;
        $mail->Port = $smtpPort;

        if (in_array($smtpSecure, ['ssl', 'smtps'], true)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (in_array($smtpSecure, ['tls', 'starttls'], true)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpSecure !== '') {
            $mail->SMTPSecure = $smtpSecure;
        }

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = trim(
            'Olá, ' . ($recipientName !== '' ? $recipientName : 'tudo bem?') . "!\n\n" .
            $message .
            (($buttonUrl !== null && $buttonUrl !== '') ? "\n\n" . $buttonUrl : '')
        );

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Falha ao enviar e-mail transacional: ' . $e->getMessage());
        return false;
    }
}
