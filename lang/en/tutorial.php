<?php

return [

    /*
    | FASE 7 — i18n tri-língue. English Tutorial chapters.
    | Same key set as lang/pt-BR/tutorial.php and lang/es/tutorial.php.
    */

    'chapter' => [
        1 => [
            'slug' => 'contas-e-categorias',
            'title' => 'Accounts and categories',
            'subtitle' => 'The foundation of everything',
            'body' => "The foundation of everything. Create accounts for the places where money comes in and out, and a category tree that makes sense to you.\n\nSolar treats each account as its own scene — checking, savings, credit card, cash, investment — and each transaction belongs to exactly one account. Categories form a tree: 'Food' at the top, with 'Restaurants' and 'Groceries' underneath if you want. Simple or detailed, you decide.",
        ],
        2 => [
            'slug' => 'transacoes',
            'title' => 'Transactions',
            'subtitle' => 'The heart of Solar',
            'body' => "The heart of Solar. Each transaction is a single entry with date, amount, account, category, and description.\n\nYou can log a transaction fast from the top bar, import an OFX from your bank, or use the AI-powered category suggestion — it learns from your past entries. Installment transactions, recurrences, splits across categories, and PIX-marked entries are all first-class.",
        ],
        3 => [
            'slug' => 'metas-e-orcamentos',
            'title' => 'Goals and budgets',
            'subtitle' => 'Solar’s discipline',
            'body' => "Solar’s discipline, without becoming a productivity app. Define what matters and the app shows you when you drift from the plan.\n\nSavings goals (with deadline, target amount, and icon) and per-category budgets (monthly or custom) share the same panel. When you cross 80% of your 'Entertainment' budget, Solar nudges you without judgment.",
        ],
        4 => [
            'slug' => 'pix-e-transferencias',
            'title' => 'PIX and transfers',
            'subtitle' => 'Record every move',
            'body' => "Record every move between accounts — and between you and the world — in three taps.\n\nSave your favorite PIX keys (email, phone, CPF/CNPJ, random key) and use the QR code from your receipt to prefill the entry. Transfers between your own accounts are detected automatically and do not inflate your spending report.",
        ],
        5 => [
            'slug' => 'investimentos-e-dividas',
            'title' => 'Investments and debts',
            'subtitle' => 'Both sides of net worth',
            'body' => "Both sides of net worth. Solar treats them with equal seriousness.\n\nTrack stocks, REITs, crypto, and treasury bonds with manual or synced pricing. Register financing contracts (SAC or Price) and Solar simulates the amortization so you can see the impact of every extra payment on the final installment.",
        ],
        6 => [
            'slug' => 'seguranca',
            'title' => 'Security',
            'subtitle' => 'Your personal ledger',
            'body' => "Your personal ledger needs to be as secure as your bank. Solar ships with 2FA, trusted devices, and built-in audit logs.\n\nEnable two-factor authentication with any TOTP app, manage the devices that have already cleared the challenge, and review the session audit log when you need to. Passwords are bcrypt-hashed, tokens are SHA-256, and tokens expire after 60 minutes.",
        ],
    ],
];
