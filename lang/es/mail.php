<?php

return [

    /*
    | FASE 7 — i18n tri-língue. Spanish mail copy.
    | Same key set as lang/pt-BR/mail.php and lang/en/mail.php.
    */

    'verify' => [
        'subject' => 'Confirma tu correo - Solar Money',
        'greeting' => 'Confirma tu correo electronico',
        'intro' => 'Hola :name, haz clic en el boton de abajo para confirmar tu correo electronico y empezar a usar Solar Money.',
        'expire' => 'El enlace caduca en 60 minutos.',
        'action' => 'Confirmar correo',
        'fallback_url_label' => 'O pega este enlace en el navegador:',
        'footer' => 'Si no creaste esta cuenta, ignora este correo.',
    ],

    'reset' => [
        'subject' => 'Restablece tu contrasena - Solar Money',
        'greeting' => 'Restablecer contrasena',
        'intro' => 'Hola :name, haz clic en el boton de abajo para restablecer tu contrasena.',
        'expire' => 'El enlace caduca en 60 minutos.',
        'action' => 'Restablecer contrasena',
        'fallback_url_label' => 'O pega este enlace en el navegador:',
        'footer' => 'Si no solicitaste esto, ignora este correo — tu contrasena no se cambiara.',
    ],

    '2fa_enroll' => [
        'subject' => 'Activar verificacion en dos pasos - Solar Money',
        'greeting' => 'Activar verificacion en dos pasos',
        'intro' => 'Hola :name, solicitaste activar la verificacion en dos pasos (2FA) en tu cuenta de Solar Money.',
        'step_1' => 'Haz clic en el boton de abajo para abrir la pagina de confirmacion.',
        'step_2' => 'Escanea el codigo QR con tu app autenticadora (Google Authenticator, 1Password, Authy, etc).',
        'step_3' => 'Ingresa el codigo de 6 digitos que genera la app para completar la activacion.',
        'expire' => 'El enlace caduca en 60 minutos.',
        'action' => 'Activar 2FA',
        'fallback_url_label' => 'O pega este enlace en el navegador:',
        'footer' => 'Si no solicitaste esto, ignora este correo — tu cuenta permanecera sin cambios.',
    ],

    '2fa_disable' => [
        'subject' => 'Desactivar verificacion en dos pasos - Solar Money',
        'greeting' => 'Desactivar verificacion en dos pasos',
        'warning' => 'Atencion: desactivar la verificacion en dos pasos elimina una capa importante de proteccion de tu cuenta. Cualquier persona con tu contrasena podra entrar.',
        'intro' => 'Hola :name, solicitaste desactivar la verificacion en dos pasos. Haz clic en el boton de abajo y confirma tu contrasena para continuar.',
        'expire' => 'El enlace caduca en 60 minutos.',
        'action' => 'Desactivar 2FA',
        'fallback_url_label' => 'O pega este enlace en el navegador:',
        'footer' => 'Si no solicitaste esto, ignora este correo — tu cuenta permanecera protegida.',
    ],

    'common' => [
        'brand' => 'Solar Money',
        'salutation' => 'Hasta pronto,',
        'team' => 'Equipo Solar Money',
        'copy_preheader' => 'Solar Money - Finanzas personales',
    ],
];
