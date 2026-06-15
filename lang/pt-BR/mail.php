<?php

return [

    /*
    | FASE 7 — i18n tri-língue. Portuguese (Brazil) mail copy.
    |
    | Subjects are ASCII-only (no accents) because some clients mangle
    | non-ASCII subjects. The body's `greeting`, `intro`, `action`, and
    | `footer` lines ARE translated with full accents.
    */

    'verify' => [
        'subject' => 'Confirme seu email - Solar Money',
        'greeting' => 'Confirme seu email',
        'intro' => 'Ola :name, clique no botao abaixo para confirmar seu email e comecar a usar o Solar Money.',
        'expire' => 'O link expira em 60 minutos.',
        'action' => 'Confirmar email',
        'fallback_url_label' => 'Ou cole este link no navegador:',
        'footer' => 'Se voce nao criou essa conta, ignore este email.',
    ],

    'reset' => [
        'subject' => 'Redefina sua senha - Solar Money',
        'greeting' => 'Redefinir senha',
        'intro' => 'Ola :name, clique no botao abaixo para redefinir sua senha.',
        'expire' => 'O link expira em 60 minutos.',
        'action' => 'Redefinir senha',
        'fallback_url_label' => 'Ou cole este link no navegador:',
        'footer' => 'Se voce nao solicitou isso, ignore este email — sua senha nao sera alterada.',
    ],

    '2fa_enroll' => [
        'subject' => 'Ativar verificacao em duas etapas - Solar Money',
        'greeting' => 'Ativar verificacao em duas etapas',
        'intro' => 'Ola :name, voce solicitou a ativacao da verificacao em duas etapas (2FA) na sua conta do Solar Money.',
        'step_1' => 'Clique no botao abaixo para abrir a pagina de confirmacao.',
        'step_2' => 'Escaneie o QR code com seu app autenticador (Google Authenticator, 1Password, Authy, etc).',
        'step_3' => 'Digite o codigo de 6 digitos gerado pelo app para concluir a ativacao.',
        'expire' => 'O link expira em 60 minutos.',
        'action' => 'Ativar 2FA',
        'fallback_url_label' => 'Ou cole este link no navegador:',
        'footer' => 'Se voce nao solicitou isso, ignore este email — sua conta permanecera inalterada.',
    ],

    '2fa_disable' => [
        'subject' => 'Desativar verificacao em duas etapas - Solar Money',
        'greeting' => 'Desativar verificacao em duas etapas',
        'warning' => 'Atencao: desativar a verificacao em duas etapas remove uma camada importante de protecao da sua conta. Qualquer pessoa com acesso a sua senha podera entrar.',
        'intro' => 'Ola :name, voce solicitou a desativacao da verificacao em duas etapas. Clique no botao abaixo e confirme sua senha para concluir.',
        'expire' => 'O link expira em 60 minutos.',
        'action' => 'Desativar 2FA',
        'fallback_url_label' => 'Ou cole este link no navegador:',
        'footer' => 'Se voce nao solicitou isso, ignore este email — sua conta permanecera protegida.',
    ],

    'common' => [
        'brand' => 'Solar Money',
        'salutation' => 'Ate logo,',
        'team' => 'Equipe Solar Money',
        'copy_preheader' => 'Solar Money - Financas pessoais',
    ],
];
