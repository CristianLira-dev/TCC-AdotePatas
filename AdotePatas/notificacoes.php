<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/session.php';
requerer_login();

$recipientId = (int) $_SESSION['user_id'];
$recipientType = (string) $_SESSION['user_tipo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statement = $conn->prepare(
        'UPDATE notificacao SET read_at = COALESCE(read_at, NOW())'
        . ' WHERE recipient_id = :recipient_id AND recipient_type = :recipient_type'
    );
    $statement->execute([':recipient_id' => $recipientId, ':recipient_type' => $recipientType]);
    header('Location: notificacoes/');
    exit;
}

$statement = $conn->prepare(
    'SELECT id_notificacao, title, message, action_url, read_at, created_at'
    . ' FROM notificacao WHERE recipient_id = :recipient_id AND recipient_type = :recipient_type'
    . ' ORDER BY created_at DESC LIMIT 100'
);
$statement->execute([':recipient_id' => $recipientId, ':recipient_type' => $recipientType]);
$notifications = $statement->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações — Adote Patas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global/global.css">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width:820px">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
        <div><a href="./" class="text-decoration-none">← Voltar</a><h1 class="mt-2">Notificações</h1></div>
        <?php if ($notifications !== []): ?>
            <form method="post"><button class="btn btn-outline-secondary" type="submit">Marcar todas como lidas</button></form>
        <?php endif; ?>
    </div>
    <?php if ($notifications === []): ?>
        <div class="alert alert-light border">Você ainda não possui notificações.</div>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($notifications as $notification): ?>
                <article class="card shadow-sm <?php echo $notification['read_at'] ? '' : 'border-danger'; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3">
                            <h2 class="h5 mb-2"><?php echo appEscape($notification['title']); ?></h2>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                        </div>
                        <p class="mb-2"><?php echo nl2br(appEscape($notification['message'])); ?></p>
                        <?php if (!empty($notification['action_url'])): ?>
                            <a href="<?php echo appEscape($notification['action_url']); ?>">Ver detalhes</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
