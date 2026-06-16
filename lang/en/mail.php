<?php

return [

    /*
    | FASE 7 — i18n tri-língue. English mail copy.
    | Same key set as lang/pt-BR/mail.php and lang/es/mail.php.
    */

    'verify' => [
        'subject' => 'Confirm your email - Solar Money',
        'greeting' => 'Confirm your email',
        'intro' => 'Hi :name, click the button below to confirm your email and start using Solar Money.',
        'expire' => 'The link expires in 60 minutes.',
        'action' => 'Confirm email',
        'fallback_url_label' => 'Or paste this link in your browser:',
        'footer' => 'If you did not create this account, please ignore this email.',
    ],

    'reset' => [
        'subject' => 'Reset your password - Solar Money',
        'greeting' => 'Reset your password',
        'intro' => 'Hi :name, click the button below to reset your password.',
        'expire' => 'The link expires in 60 minutes.',
        'action' => 'Reset password',
        'fallback_url_label' => 'Or paste this link in your browser:',
        'footer' => 'If you did not request this, please ignore this email — your password will not be changed.',
    ],

    '2fa_enroll' => [
        'subject' => 'Enable two-factor authentication - Solar Money',
        'greeting' => 'Enable two-factor authentication',
        'intro' => 'Hi :name, you requested to enable two-factor authentication (2FA) on your Solar Money account.',
        'step_1' => 'Click the button below to open the confirmation page.',
        'step_2' => 'Scan the QR code with your authenticator app (Google Authenticator, 1Password, Authy, etc).',
        'step_3' => 'Enter the 6-digit code the app generates to finish enabling 2FA.',
        'expire' => 'The link expires in 60 minutes.',
        'action' => 'Enable 2FA',
        'fallback_url_label' => 'Or paste this link in your browser:',
        'footer' => 'If you did not request this, please ignore this email — your account will remain unchanged.',
    ],

    '2fa_disable' => [
        'subject' => 'Disable two-factor authentication - Solar Money',
        'greeting' => 'Disable two-factor authentication',
        'warning' => 'Warning: disabling two-factor authentication removes an important layer of protection from your account. Anyone with your password will be able to sign in.',
        'intro' => 'Hi :name, you requested to disable two-factor authentication. Click the button below and confirm your password to continue.',
        'expire' => 'The link expires in 60 minutes.',
        'action' => 'Disable 2FA',
        'fallback_url_label' => 'Or paste this link in your browser:',
        'footer' => 'If you did not request this, please ignore this email — your account will remain protected.',
    ],

    'common' => [
        'brand' => 'Solar Money',
        'salutation' => 'Talk soon,',
        'team' => 'The Solar Money team',
        'copy_preheader' => 'Solar Money - Personal finance',
    ],
];
