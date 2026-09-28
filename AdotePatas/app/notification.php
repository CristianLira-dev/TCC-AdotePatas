<?php

declare(strict_types=1);

function appNotify(
    PDO $connection,
    int $recipientId,
    string $recipientType,
    string $title,
    string $message,
    ?string $actionUrl = null
): void {
    if ($recipientId <= 0 || !in_array($recipientType, ['usuario', 'ong', 'admin'], true)) {
        return;
    }

    if ($actionUrl !== null) {
        $actionUrl = trim($actionUrl);
        if ($actionUrl === '' || str_starts_with($actionUrl, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $actionUrl)) {
            $actionUrl = null;
        }
    }

    $safeTitle = function_exists('mb_substr') ? mb_substr($title, 0, 160) : substr($title, 0, 160);

    try {
        $statement = $connection->prepare(
            'INSERT INTO notificacao (recipient_id, recipient_type, title, message, action_url, created_at)'
            . ' VALUES (:recipient_id, :recipient_type, :title, :message, :action_url, NOW())'
        );
        $statement->execute([
            ':recipient_id' => $recipientId,
            ':recipient_type' => $recipientType,
            ':title' => $safeTitle,
            ':message' => $message,
            ':action_url' => $actionUrl,
        ]);
    } catch (Throwable $error) {
        error_log('Falha ao criar notificação: ' . $error->getMessage());
    }
}
