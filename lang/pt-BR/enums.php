<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enum labels (FASE 7 — i18n tri-língue)
    |--------------------------------------------------------------------------
    |
    | User-facing labels for the in-code enum-like string columns.
    | Every key set is identical across pt-BR, es, and en.
    */

    'transaction' => [
        'income' => 'Receita',
        'expense' => 'Despesa',
        'transfer' => 'Transferência',
    ],

    'account' => [
        'checking' => 'Conta corrente',
        'savings' => 'Poupança',
        'credit' => 'Cartão de crédito',
        'credit_card' => 'Cartão de crédito',
        'investment' => 'Investimento',
        'cash' => 'Dinheiro',
        'multi_currency' => 'Multi-moeda',
        'other' => 'Outro',
    ],

    'recurrence' => [
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'yearly' => 'Anual',
    ],

    'frequency' => [
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'yearly' => 'Anual',
    ],

    'subscription' => [
        'status' => [
            'active' => 'Ativa',
            'cancelled' => 'Cancelada',
        ],
    ],

    'goal' => [
        'status' => [
            'in_progress' => 'Em andamento',
            'completed' => 'Concluída',
        ],
    ],
];
