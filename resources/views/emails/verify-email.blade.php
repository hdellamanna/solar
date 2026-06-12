<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirme seu email - Solar Money</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 32px;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
        }
        .logo {
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #FF8A3D 0%, #FFC93C 45%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .headline {
            font-size: 22px;
            font-weight: 700;
            color: #0b0f1a;
            margin: 24px 0 8px;
        }
        .body {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(180deg, #8b5cf6, #6d28d9);
            color: #ffffff;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 8px 24px -4px rgba(124, 58, 237, 0.4);
        }
        .muted {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 24px;
        }
        .url {
            word-break: break-all;
            color: #94a3b8;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Solar Money</div>
        <h1 class="headline">Confirme seu email</h1>
        <p class="body">
            Ola {{ $user->name }}, clique no botao abaixo para confirmar seu email e comecar a usar o Solar Money. O link expira em 60 minutos.
        </p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $verificationUrl }}" class="btn">Confirmar email</a>
        </p>
        <p class="muted">Ou cole este link no navegador:</p>
        <p class="url">{{ $verificationUrl }}</p>
        <p class="muted">Se voce nao criou essa conta, ignore este email.</p>
    </div>
</body>
</html>
