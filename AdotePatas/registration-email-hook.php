<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$registrationType = (string) ($_POST['form_type'] ?? '');
if (!in_array($registrationType, ['cadastro_usuario', 'cadastro_ong'], true)) {
    return;
}

if ($registrationType === 'cadastro_usuario') {
    $registrationName = trim((string) ($_POST['nome-completo'] ?? ''));
    $registrationEmail = trim((string) ($_POST['email-cadastro'] ?? ''));
    $expectedSuccessMessage = 'Cadastro realizado com sucesso! Você já pode fazer login.';
} else {
    $registrationName = trim((string) ($_POST['nome_ong'] ?? ''));
    $registrationEmail = trim((string) ($_POST['email_ong'] ?? ''));
    $expectedSuccessMessage = 'Cadastro de ONG realizado com sucesso! Você já pode fazer login.';
}

register_shutdown_function(
    static function () use (
        $registrationType,
        $registrationName,
        $registrationEmail,
        $expectedSuccessMessage
    ): void {
        if ($registrationName === '' || $registrationEmail === '') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (($_SESSION['tipo_mensagem'] ?? '') !== 'success') {
            return;
        }

        if (($_SESSION['mensagem_status'] ?? '') !== $expectedSuccessMessage) {
            return;
        }

        require_once __DIR__ . '/email-service.php';

        $appUrl = adotePatasAppUrl();

        if ($registrationType === 'cadastro_ong') {
            adotePatasSendEmail(
                $registrationEmail,
                $registrationName,
                'Boas-vindas ao Adote Patas!',
                'Cadastro concluído com sucesso',
                'A sua ONG já faz parte do Adote Patas. Agora você pode acessar a plataforma e cadastrar pets para adoção.',
                'Acessar o Adote Patas',
                $appUrl . '/login/'
            );
            return;
        }

        adotePatasSendEmail(
            $registrationEmail,
            $registrationName,
            'Boas-vindas ao Adote Patas!',
            'Que bom ter você por aqui!',
            'Seu cadastro foi concluído com sucesso. Agora você já pode conhecer os pets, favoritar seus preferidos e iniciar processos de adoção.',
            'Conhecer os pets',
            $appUrl . '/pets/'
        );
    }
);
