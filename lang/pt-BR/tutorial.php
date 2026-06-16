<?php

return [

    /*
    | FASE 7 — i18n tri-língue. Portuguese (Brazil) Tutorial chapters.
    |
    | 6 chapters, each with title (1 line), subtitle (1 line), and
    | body (1-3 short paragraphs). The English and Spanish files
    | have the EXACT same key set so the TutorialController can
    | swap translations based on the active locale.
    */

    'chapter' => [
        1 => [
            'slug' => 'contas-e-categorias',
            'title' => 'Contas e categorias',
            'subtitle' => 'A base de tudo',
            'body' => "A base de tudo. Crie contas para onde o dinheiro entra e sai, e uma arvore de categorias que faca sentido pra voce.\n\nO Solar trata cada conta como um cenario proprio — conta corrente, poupanca, cartao de credito, carteira, investimento — e cada transacao pertence a exatamente uma conta. As categorias entram como uma arvore: 'Alimentacao' no topo, com 'Restaurantes' e 'Mercado' embaixo se voce quiser. Simples ou detalhado, voce decide.",
        ],
        2 => [
            'slug' => 'transacoes',
            'title' => 'Transacoes',
            'subtitle' => 'O coracao do Solar',
            'body' => "O coracao do Solar. Cada transacao e um lancamento com data, valor, conta, categoria e descricao.\n\nVoce pode lancar rapido pela barra superior, importar um OFX do banco, ou usar a sugestao automatica de categoria com IA — ela aprende com seus lancamentos passados. Transacoes parceladas, recorrentes, divididas entre categorias e marcadas como PIX tem suporte de primeira classe.",
        ],
        3 => [
            'slug' => 'metas-e-orcamentos',
            'title' => 'Metas e orcamentos',
            'subtitle' => 'A disciplina do Solar',
            'body' => "A disciplina do Solar, sem virar um app de produtividade. Defina o que importa e o app te mostra quando voce se afasta do plano.\n\nMetas de economia (com prazo, valor alvo, e icone) e orcamentos por categoria (mensal ou personalizado) dividem o mesmo painel. Quando voce ultrapassa 80% do orcamento de 'Lazer', o Solar te avisa sem julgar.",
        ],
        4 => [
            'slug' => 'pix-e-transferencias',
            'title' => 'PIX e transferencias',
            'subtitle' => 'Registre cada movimento',
            'body' => "Registre cada movimento entre contas, e entre voce e o mundo, com 3 toques.\n\nSalve suas chaves PIX favoritas (email, telefone, CPF/CNPJ, chave aleatoria) e use o QR Code do comprovante para preencher o lancamento. Transferencias entre contas proprias sao reconhecidas automaticamente e nao inflam seu relatorio de gastos.",
        ],
        5 => [
            'slug' => 'investimentos-e-dividas',
            'title' => 'Investimentos e dividas',
            'subtitle' => 'Os dois lados do patrimonio',
            'body' => "Os dois lados do patrimonio liquido. O Solar trata ambos com a mesma seriedade.\n\nAcompanhe acoes, FIIs, cripto e tesouro com cotacao manual ou sincronizada. Registre financiamentos (SAC ou Price) e o Solar simula a amortizacao para voce ver o impacto de cada pagamento extra na parcela final.",
        ],
        6 => [
            'slug' => 'seguranca',
            'title' => 'Seguranca',
            'subtitle' => 'Sua ledger pessoal',
            'body' => "Sua ledger pessoal precisa ser tao segura quanto seu banco. O Solar vem com 2FA, dispositivos confiaveis e auditoria built-in.\n\nAtive a autenticacao em duas etapas com qualquer app TOTP, gerencie os dispositivos que ja passaram pelo desafio, e revise a auditoria de sessoes quando precisar. A senha e hashada com bcrypt, os tokens sao SHA-256 e expiram em 60 minutos.",
        ],
    ],
];
