<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php
    include 'session.php';
    echo "Bem-vindo, usuário! ". $_SESSION['nome'];  // Exibe o nome do usuário logado
    ?>
</body>
</html>