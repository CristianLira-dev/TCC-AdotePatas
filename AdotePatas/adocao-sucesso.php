<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'session.php'; // Nosso script de sessão

// Configuração do $base_path
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $base_path = '/TCC-AdotePatas/AdotePatas/';
} else {
    $base_path = '/'; 
}

// Pega o nome do usuário da sessão, se existir
$primeiro_nome = $_SESSION['primeiro_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação Enviada! - Adote Patas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #fcf8f7; }
        .success-container {
            max-width: 600px;
            margin-top: 50px;
            background-color: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745; /* Verde sucesso */
        }
        .btn-custom {
            background-color: #D9534F;
            border-color: #D9534F;
            color: #fff;
            font-weight: 500;
            margin: 5px;
        }
        .btn-custom:hover {
            background-color: #c9302c;
            border-color: #ac2925;
            color: #fff;
        }
        .btn-outline-custom {
            color: #D9534F;
            border-color: #D9534F;
        }
        .btn-outline-custom:hover {
            background-color: #D9534F;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Header Simples -->
    <header class="shadow-sm" style="background-color: #fff;">
      <nav class="navbar">
        <div class="container">
          <a class="navbar-brand" href="<?php echo $base_path; ?>./">
            <img src="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" style="height: 50px;">
          </a>
        </div>
      </nav>
    </header>

    <div class="container">
        <div class="success-container mx-auto">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1 class="mt-3">Solicitação Enviada!</h1>
            <p class="lead">Obrigado, <?php echo htmlspecialchars($primeiro_nome); ?>!</p>
            <p>Seu formulário de interesse foi enviado com sucesso para o protetor do pet. Ele tem até 48 horas para analisar e entrar em contato com você.</p>
            <p>Você pode acompanhar o status da sua solicitação na sua área de perfil.</p>
            <hr>
            <a href="<?php echo $base_path; ?>perfil?page=minhas-solicitacoes" class="btn btn-custom">Ver Minhas Solicitações</a>
            <a href="<?php echo $base_path; ?>pets" class="btn btn-outline-custom">Ver mais pets</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
