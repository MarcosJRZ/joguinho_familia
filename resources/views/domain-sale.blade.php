<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domínio a Venda</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
        }

        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .subtitle {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 40px;
            line-height: 1.5;
        }

        .contact-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .domain-name {
            color: #764ba2;
            font-weight: 600;
            margin-top: 30px;
            font-size: 1.4em;
        }

        @media (max-width: 600px) {
            .container {
                padding: 40px 20px;
            }

            h1 {
                font-size: 2em;
            }

            .subtitle {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="icon">🌐</span>
        <h1>Domínio a Venda</h1>
        <p class="subtitle">
            Este domínio está disponível para compra.<br>
            Entre em contato para mais informações e negociação.
        </p>
        <a href="mailto:marcos.csj.z@outlook.com.br?subject=Interesse no Domínio&body=Olá, tenho interesse em adquirir este domínio. Podemos conversar sobre valores e condições?" class="contact-btn">
            📧 Entrar em Contato
        </a>
        <div class="domain-name">
            {{ request()->getHost() }}
        </div>
    </div>
</body>
</html>