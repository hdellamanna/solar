<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App chrome (FASE 7 — i18n tri-língue)
    |--------------------------------------------------------------------------
    |
    | Static UI strings reused across the SPA. The English and Spanish
    | files have the EXACT same key set as this one — the tests-coverage
    | track asserts that the three are key-for-key identical.
    */

    'brand' => 'Solar Money',
    'tagline' => 'Finanças pessoais simples',
    'save' => 'Salvar',
    'cancel' => 'Cancelar',
    'edit' => 'Editar',
    'delete' => 'Excluir',
    'back' => 'Voltar',
    'next' => 'Próximo',
    'previous' => 'Anterior',
    'loading' => 'Carregando…',
    'search' => 'Buscar',
    'login' => 'Entrar',
    'logout' => 'Sair',
    'register' => 'Criar conta',
    'dashboard' => 'Painel',
    'accounts' => 'Contas',
    'transactions' => 'Transações',
    'subscriptions' => 'Assinaturas',
    'goals' => 'Metas',
    'investments' => 'Investimentos',
    'debts' => 'Dívidas',
    'pix' => 'PIX',
    'tags' => 'Tags',
    'budgets' => 'Orçamentos',
    'reports' => 'Relatórios',
    'settings' => 'Configurações',
    'profile' => 'Perfil',
    'security' => 'Segurança',
    'appearance' => 'Aparência',
    'language' => 'Idioma',
    'tutorial' => 'Tutorial',

    // Settings save flashes
    'appearance_save_success' => 'Preferências salvas.',
    'language_save_success' => 'Idioma atualizado.',
    'profile_save_success' => 'Perfil atualizado.',
    'tag_created' => 'Tag criada.',
    'tag_updated' => 'Tag atualizada.',
    'tag_deleted' => 'Tag removida.',
    'tag_attached' => 'Tag vinculada.',
    'tag_detached' => 'Tag removida da transação.',

    // Common UI states
    'yes' => 'Sim',
    'no' => 'Não',
    'none' => 'Nenhum',
    'optional' => 'Opcional',
    'required' => 'Obrigatório',

    // Errors
    'error_generic' => 'Algo deu errado. Tente novamente.',
    'not_found' => 'Não encontrado.',
    'unauthorized' => 'Você não tem permissão para fazer isso.',

    // Time helpers (Carbon::diffForHumans pre-formatted alternatives)
    'just_now' => 'agora mesmo',
    'minutes_ago' => ':count min atrás',
    'hours_ago' => ':count h atrás',
    'days_ago' => ':count d atrás',
];
