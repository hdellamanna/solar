<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ativar verificacao em duas etapas - Solar Money</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 32px; }
        table { border-collapse: collapse; }
        a { color: inherit; }

        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 40px; }

        .logo {
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #FF8A3D 0%, #FFC93C 45%, #7c3aed 100%);
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .headline { font-size: 22px; font-weight: 700; color: #0b0f1a; margin: 24px 0 8px; }
        .body { color: #475569; line-height: 1.6; margin-bottom: 24px; }

        .btn {
            display: inline-block;
            background: linear-gradient(180deg, #8b5cf6, #6d28d9);
            color: #ffffff !important;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 8px 24px -4px rgba(124, 58, 237, 0.4);
        }

        .muted { color: #94a3b8; font-size: 12px; margin-top: 24px; }
        .url { word-break: break-all; color: #94a3b8; font-size: 12px; }

        .steps { color: #475569; line-height: 1.7; margin: 16px 0 24px; padding-left: 20px; }
        .steps li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Solar Money</div>
        <h1 class="headline">Ativar verificacao em duas etapas</h1>
        <p class="body">
            Ola {{ $user->name }}, voce solicitou a ativacao da verificacao em duas etapas (2FA) na sua conta do Solar Money.
        </p>
        <ol class="steps">
            <li>Clique no botao abaixo para abrir a pagina de confirmacao.</li>
            <li>Escaneie o QR code com seu app autenticador (Google Authenticator, 1Password, Authy, etc.).</li>
            <li>Digite o codigo de 6 digitos gerado pelo app para concluir a ativacao.</li>
        </ol>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $confirmUrl }}" class="btn">Ativar 2FA</a>
        </p>
        <p class="muted">Ou cole este link no navegador:</p>
        <p class="url">{{ $confirmUrl }}</p>
        <p class="muted">
            O link expira em 60 minutos. Se voce nao solicitou isso, ignore este email — sua conta permanecera inalterada.
        </p>
    </div>
</body>
</html>
