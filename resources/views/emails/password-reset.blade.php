<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redefina sua senha - Solar Money</title>
    <style>
        /* Resets */
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 32px; }
        table { border-collapse: collapse; }
        a { color: inherit; }

        /* Container */
        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 40px; }

        /* Logo — gradient text matching the Solar Money brand mark
           (orange-500 → yellow-400 → violet-600). Renders as a stack
           of solid-color fallbacks for clients that drop -webkit-text-fill-color. */
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

        /* Headline + body copy */
        .headline { font-size: 22px; font-weight: 700; color: #0b0f1a; margin: 24px 0 8px; }
        .body { color: #475569; line-height: 1.6; margin-bottom: 24px; }

        /* CTA button — violet gradient, glossy inset */
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

        /* Muted helpers */
        .muted { color: #94a3b8; font-size: 12px; margin-top: 24px; }
        .url { word-break: break-all; color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Solar Money</div>
        <h1 class="headline">Redefina sua senha</h1>
        <p class="body">
            Olá {{ $user->name }}, clique no botão abaixo para redefinir sua senha. O link expira em 60 minutos.
        </p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $resetUrl }}" class="btn">Redefinir senha</a>
        </p>
        <p class="muted">Ou cole este link no navegador:</p>
        <p class="url">{{ $resetUrl }}</p>
        <p class="muted">Se você não solicitou isso, ignore este email — sua senha não será alterada.</p>
    </div>
</body>
</html>
