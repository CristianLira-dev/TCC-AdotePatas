<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada - Adote Patas</title>
    <link rel="icon" type="image/png" href="/images/global/Logo-AdotePatas.png">
    <script src="https://unpkg.com/@lottiefiles/lottie-player@2.0.12/dist/lottie-player.js"></script>
    <style>
        :root {
            --cor-vermelho: #b65c52;
            --cor-vermelho-escuro: #8f443c;
            --cor-texto: #4c4c4c;
            --cor-fundo: #fff8f6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Poppins", Arial, sans-serif;
            color: var(--cor-texto);
            background: var(--cor-fundo);
        }

        .error-card {
            width: min(720px, 100%);
            text-align: center;
        }

        .error-animation {
            width: min(360px, 80vw);
            height: min(360px, 80vw);
            margin: 0 auto -12px;
        }

        .error-code {
            margin: 0;
            color: var(--cor-vermelho);
            font-size: clamp(3rem, 10vw, 5.5rem);
            line-height: 1;
            font-weight: 800;
        }

        h1 {
            margin: 14px 0 10px;
            font-size: clamp(1.6rem, 4vw, 2.25rem);
        }

        p {
            max-width: 560px;
            margin: 0 auto 26px;
            line-height: 1.65;
            color: #6b6b6b;
        }

        .home-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 22px;
            border-radius: 999px;
            color: #fff;
            background: var(--cor-vermelho);
            text-decoration: none;
            font-weight: 700;
            transition: transform .2s ease, background .2s ease;
        }

        .home-button:hover {
            background: var(--cor-vermelho-escuro);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <main class="error-card">
        <lottie-player
            class="error-animation"
            src="/anima%C3%A7%C3%B5es/Error.json"
            background="transparent"
            speed="1"
            loop
            autoplay>
        </lottie-player>

        <p class="error-code">404</p>
        <h1>Página não encontrada</h1>
        <p>A página que você tentou acessar não existe, foi removida ou teve o endereço alterado.</p>
        <a class="home-button" href="/">Voltar para o início</a>
    </main>
</body>
</html>
