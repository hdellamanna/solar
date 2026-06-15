<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.2fa_disable.subject') }}</title>
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

        .btn-danger {
            display: inline-block;
            background: linear-gradient(180deg, #f43f5e, #be123c);
            color: #ffffff !important;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 8px 24px -4px rgba(190, 18, 60, 0.4);
        }

        .warning {
            background: #fef2f2;
            border-left: 4px solid #f43f5e;
            border-radius: 8px;
            padding: 12px 16px;
            color: #991b1b;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .muted { color: #94a3b8; font-size: 12px; margin-top: 24px; }
        .url { word-break: break-all; color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">{{ __('mail.common.brand') }}</div>
        <h1 class="headline">{{ __('mail.2fa_disable.greeting') }}</h1>
        <div class="warning">
            <strong>{{ __('mail.common.brand') }}:</strong> {{ __('mail.2fa_disable.warning') }}
        </div>
        <p class="body">
            {{ __('mail.2fa_disable.intro', ['name' => $user->name]) }}
        </p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $confirmUrl }}" class="btn-danger">{{ __('mail.2fa_disable.action') }}</a>
        </p>
        <p class="muted">{{ __('mail.2fa_disable.fallback_url_label') }}</p>
        <p class="url">{{ $confirmUrl }}</p>
        <p class="muted">
            {{ __('mail.2fa_disable.expire') }}
            {{ __('mail.2fa_disable.footer') }}
        </p>
    </div>
</body>
</html>
