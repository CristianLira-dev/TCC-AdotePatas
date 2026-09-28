<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/email-service.php';

header('Content-Type: application/json; charset=UTF-8');

function responderContato(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderContato(false, 'Método não permitido.', 405);
}

if (!appRateLimit('contact', 5, 3600)) {
    responderContato(false, 'Muitas mensagens foram enviadas. Tente novamente mais tarde.', 429);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    responderContato(false, 'Dados inválidos.', 400);
}

if (!empty($input['website'])) {
    responderContato(true, 'Mensagem enviada com sucesso.');
}

$nome = trim((string) ($input['nome'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$assunto = trim((string) ($input['assunto'] ?? ''));
$mensagem = trim((string) ($input['mensagem'] ?? ''));

if ($nome === '' || $assunto === '' || $mensagem === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderContato(false, 'Preencha corretamente todos os campos obrigatórios.', 422);
}

if (mb_strlen($nome) > 120 || mb_strlen($assunto) > 160 || mb_strlen($mensagem) > 5000) {
    responderContato(false, 'Um ou mais campos ultrapassam o limite permitido.', 422);
}

$recipient = adotePatasEmailEnv('CONTACT_RECIPIENT', adotePatasEmailEnv('MAIL_FROM_ADDRESS'));
if ($recipient === '') {
    error_log('Contato não enviado: CONTACT_RECIPIENT não configurado.');
    responderContato(false, 'O canal de contato está temporariamente indisponível.', 503);
}

$sent = adotePatasSendEmail(
    $recipient,
    'Administração Adote Patas',
    'Fale Conosco: ' . $assunto,
    'Nova mensagem de contato',
    "Nome: {$nome}\nE-mail para resposta: {$email}\nAssunto: {$assunto}\n\n{$mensagem}"
);

if (!$sent) {
    responderContato(false, 'Não foi possível enviar a mensagem agora. Tente novamente mais tarde.', 503);
}

responderContato(true, 'Mensagem enviada com sucesso.');
