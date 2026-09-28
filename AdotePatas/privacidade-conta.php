<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/session.php';
requerer_login();

header('Cache-Control: no-store, private');

$accountId = (int) $_SESSION['user_id'];
$accountType = (string) $_SESSION['user_tipo'];
if (!in_array($accountType, ['usuario', 'ong'], true)) {
    http_response_code(403);
    exit('Recurso disponível apenas para contas de usuário e ONG.');
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $table = $accountType === 'usuario' ? 'usuario' : 'ong';
    $idColumn = $accountType === 'usuario' ? 'id_usuario' : 'id_ong';
    $password = (string) ($_POST['password'] ?? '');
    $passwordStatement = $conn->prepare("SELECT senha FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
    $passwordStatement->execute([':id' => $accountId]);
    $passwordHash = (string) $passwordStatement->fetchColumn();
    $passwordIsValid = $password !== '' && password_verify($password, $passwordHash);

    if (!$passwordIsValid) {
        $message = 'Senha incorreta. A operação não foi realizada.';
        $messageType = 'danger';
    }

    if ($passwordIsValid && $action === 'export') {
        $profileStatement = $conn->prepare("SELECT * FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
        $profileStatement->execute([':id' => $accountId]);
        $profile = $profileStatement->fetch(PDO::FETCH_ASSOC) ?: [];
        unset($profile['senha']);

        $petColumn = $accountType === 'usuario' ? 'id_usuario_fk' : 'id_ong_fk';
        $petsStatement = $conn->prepare("SELECT * FROM pet WHERE {$petColumn} = :id ORDER BY id_pet");
        $petsStatement->execute([':id' => $accountId]);

        $messagesStatement = $conn->prepare(
            'SELECT id_mensagem, id_conversa_fk, conteudo, tipo_conteudo, arquivo_nome, data_envio'
            . ' FROM mensagem WHERE id_remetente_fk = :id AND tipo_remetente = :type ORDER BY data_envio'
        );
        $messagesStatement->execute([':id' => $accountId, ':type' => $accountType]);

        $data = [
            'exported_at' => date(DATE_ATOM),
            'account_type' => $accountType,
            'profile' => $profile,
            'pets' => $petsStatement->fetchAll(PDO::FETCH_ASSOC),
            'messages' => $messagesStatement->fetchAll(PDO::FETCH_ASSOC),
        ];

        if ($accountType === 'usuario') {
            $favorites = $conn->prepare('SELECT id_pet FROM favorito WHERE id_usuario = :id ORDER BY id_pet');
            $favorites->execute([':id' => $accountId]);
            $data['favorites'] = array_map('intval', $favorites->fetchAll(PDO::FETCH_COLUMN));

            $requests = $conn->prepare('SELECT * FROM solicitacao WHERE id_usuario = :id ORDER BY id_solicitacao');
            $requests->execute([':id' => $accountId]);
            $data['adoption_requests'] = $requests->fetchAll(PDO::FETCH_ASSOC);
        }

        appAudit($conn, 'export', 'account', $accountId, ['account_type' => $accountType]);
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="adote-patas-meus-dados.json"');
        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    if ($passwordIsValid && $action === 'request_deletion') {
            $request = $conn->prepare(
                "INSERT INTO account_deletion_request (account_id, account_type, status, requested_at)"
                . " VALUES (:id, :type, 'pending', NOW())"
                . " ON DUPLICATE KEY UPDATE status = 'pending', requested_at = VALUES(requested_at), resolved_at = NULL"
            );
            $request->execute([':id' => $accountId, ':type' => $accountType]);
            appAudit($conn, 'request_deletion', 'account', $accountId, ['account_type' => $accountType]);
            $message = 'Solicitação de exclusão registrada. A administração fará a revisão com segurança.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacidade da conta — Adote Patas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width:760px">
    <a href="perfil/" class="text-decoration-none">← Voltar ao perfil</a>
    <h1 class="mt-3">Privacidade da conta</h1>
    <p class="text-muted">Exporte seus dados ou solicite a exclusão da conta.</p>
    <?php if ($message !== ''): ?><div class="alert alert-<?php echo appEscape($messageType); ?>"><?php echo appEscape($message); ?></div><?php endif; ?>
    <section class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Exportar meus dados</h2>
        <p>Gera um arquivo JSON com perfil, pets, favoritos, solicitações e mensagens da própria conta.</p>
        <form method="post">
            <input type="hidden" name="action" value="export">
            <label class="form-label" for="export-password">Confirme sua senha</label>
            <input class="form-control mb-3" id="export-password" name="password" type="password" required autocomplete="current-password">
            <button class="btn btn-primary" type="submit">Baixar meus dados</button>
        </form>
    </div></section>
    <section class="card border-danger shadow-sm"><div class="card-body">
        <h2 class="h5 text-danger">Solicitar exclusão</h2>
        <p>A solicitação passa por revisão para preservar registros de adoções e obrigações legais.</p>
        <form method="post" onsubmit="return confirm('Confirma a solicitação de exclusão da conta?')">
            <input type="hidden" name="action" value="request_deletion">
            <label class="form-label" for="privacy-password">Confirme sua senha</label>
            <input class="form-control mb-3" id="privacy-password" name="password" type="password" required autocomplete="current-password">
            <button class="btn btn-outline-danger" type="submit">Solicitar exclusão</button>
        </form>
    </div></section>
</main>
</body>
</html>
