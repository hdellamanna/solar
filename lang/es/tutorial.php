<?php

return [

    /*
    | FASE 7 — i18n tri-língue. Spanish Tutorial chapters.
    | Same key set as lang/pt-BR/tutorial.php and lang/en/tutorial.php.
    */

    'chapter' => [
        1 => [
            'slug' => 'contas-e-categorias',
            'title' => 'Cuentas y categorias',
            'subtitle' => 'La base de todo',
            'body' => "La base de todo. Crea cuentas para donde entra y sale el dinero, y un arbol de categorias que tenga sentido para ti.\n\nSolar trata cada cuenta como un escenario propio — cuenta corriente, ahorro, tarjeta de credito, efectivo, inversion — y cada transaccion pertenece a exactamente una cuenta. Las categorias forman un arbol: 'Alimentacion' arriba, con 'Restaurantes' y 'Supermercado' abajo si quieres. Simple o detallado, tu decides.",
        ],
        2 => [
            'slug' => 'transacoes',
            'title' => 'Transacciones',
            'subtitle' => 'El corazon de Solar',
            'body' => "El corazon de Solar. Cada transaccion es un asiento con fecha, monto, cuenta, categoria y descripcion.\n\nPuedes registrar rapido desde la barra superior, importar un OFX del banco, o usar la sugerencia automatica de categoria con IA — aprende de tus registros anteriores. Transacciones en cuotas, recurrentes, divididas entre categorias y marcadas como PIX tienen soporte de primera clase.",
        ],
        3 => [
            'slug' => 'metas-e-orcamentos',
            'title' => 'Metas y presupuestos',
            'subtitle' => 'La disciplina de Solar',
            'body' => "La disciplina de Solar, sin convertirse en una app de productividad. Define lo que importa y la app te muestra cuando te alejas del plan.\n\nMetas de ahorro (con plazo, monto objetivo, e icono) y presupuestos por categoria (mensual o personalizado) comparten el mismo panel. Cuando superas el 80% del presupuesto de 'Ocio', Solar te avisa sin juzgar.",
        ],
        4 => [
            'slug' => 'pix-e-transferencias',
            'title' => 'PIX y transferencias',
            'subtitle' => 'Registra cada movimiento',
            'body' => "Registra cada movimiento entre cuentas, y entre tu y el mundo, con 3 toques.\n\nGuarda tus claves PIX favoritas (correo, telefono, CPF/CNPJ, clave aleatoria) y usa el QR del comprobante para completar el registro. Las transferencias entre cuentas propias se reconocen automaticamente y no inflan tu informe de gastos.",
        ],
        5 => [
            'slug' => 'investimentos-e-dividas',
            'title' => 'Inversiones y deudas',
            'subtitle' => 'Los dos lados del patrimonio',
            'body' => "Los dos lados del patrimonio neto. Solar trata ambos con la misma seriedad.\n\nSigue acciones, FIIs, cripto y bonos del tesoro con cotizacion manual o sincronizada. Registra financiamientos (SAC o Price) y Solar simula la amortizacion para que veas el impacto de cada pago extra en la cuota final.",
        ],
        6 => [
            'slug' => 'seguranca',
            'title' => 'Seguridad',
            'subtitle' => 'Tu libro mayor personal',
            'body' => "Tu libro mayor personal debe ser tan seguro como tu banco. Solar viene con 2FA, dispositivos de confianza y auditoria integrada.\n\nActiva la autenticacion en dos pasos con cualquier app TOTP, gestiona los dispositivos que ya pasaron el desafio, y revisa la auditoria de sesiones cuando lo necesites. La contrasena se hashea con bcrypt, los tokens son SHA-256 y caducan en 60 minutos.",
        ],
    ],
];
