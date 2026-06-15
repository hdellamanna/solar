<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.2fa_enroll.subject') }}</title>
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
        <div class="logo">{{ __('mail.common.brand') }}</div>
        <h1 class="headline">{{ __('mail.2fa_enroll.greeting') }}</h1>
        <p class="body">
            {{ __('mail.2fa_enroll.intro', ['name' => $user->name]) }}
        </p>
        <ol class="steps">
            <li>{{ __('mail.2fa_enroll.step_1') }}</li>
            <li>{{ __('mail.2fa_enroll.step_2') }}</li>
            <li>{{ __('mail.2fa_enroll.step_3') }}</li>
        </ol>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $confirmUrl }}" class="btn">{{ __('mail.2fa_enroll.action') }}</a>
        </p>
        <p class="muted">{{ __('mail.2fa_enroll.fallback_url_label') }}</p>
        <p class="url">{{ $confirmUrl }}</p>
        <p class="muted">
            {{ __('mail.2fa_enroll.expire') }}
            {{ __('mail.2fa_enroll.footer') }}
        </p>
    </div>
</body>
</html>
