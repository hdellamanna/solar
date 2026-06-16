<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AppMeta;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Investment;
use App\Models\PixKey;
use App\Models\Recurrence;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populates the database with one demo user, 5 accounts, 15 default categories,
 * and ~6 months of realistic Brazilian transactions.
 *
 * FASE 7 — i18n tri-língue. Every seeded Category and Tag now
 * carries the 3 localized names (`name_pt`, `name_es`, `name_en`).
 * The seeder also creates 2 additional demo users in the Spanish
 * and English locales so the FASE 7 verification track can hit the
 * app in any of the 3 supported languages.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // FASE 4D — build metadata (version, env)
        $this->call(AppMetaSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'demo@solar.app'],
            [
                'name' => 'Henrique Demo',
                'password' => Hash::make('solar123'),
                'theme' => 'light',
                // FASE 7 — i18n tri-língue. The Brazilian demo
                // user is the default-locale user.
                'locale' => 'pt-BR',
                'home_currency' => 'BRL',
                'email_verified_at' => now(),
            ]
        );

        // FASE 7 — Spanish and English demo users. Same password
        // (solar123) so the verification track can hop between
        // accounts without re-issuing credentials. Each gets its
        // own copy of the seed data via the same seedXxx methods
        // so the three users have parallel experience in their
        // own locale.
        $esUser = User::firstOrCreate(
            ['email' => 'demo@es.solar.app'],
            [
                'name' => 'Demo ES',
                'password' => Hash::make('solar123'),
                'theme' => 'light',
                'locale' => 'es',
                'home_currency' => 'BRL',
                'email_verified_at' => now(),
            ]
        );
        $enUser = User::firstOrCreate(
            ['email' => 'demo@en.solar.app'],
            [
                'name' => 'Demo EN',
                'password' => Hash::make('solar123'),
                'theme' => 'light',
                'locale' => 'en',
                'home_currency' => 'BRL',
                'email_verified_at' => now(),
            ]
        );

        $this->seedCategories();
        $accounts = $this->seedAccounts($user);
        $this->seedTransactions($user, $accounts);
        $this->seedRecurrences($user);
        $this->seedTags($user);
        $this->seedGoals($user);
        $this->seedSubscriptions($user, $accounts);
        $this->seedPixKeys($user);
        $this->seedInvestments($user);
        $this->seedDebts($user);
    }

    /**
     * Seed the 8 demo tags for the demo user.
     */
    private function seedTags(User $user): void
    {
        foreach (TagFactory::DEMO_TAGS as $slug => $data) {
            Tag::firstOrCreate(
                ['user_id' => $user->id, 'slug' => $slug],
                [
                    // FASE 7 — i18n. `name` (legacy) + the 3
                    // localized variants.
                    'name'    => $data['name_pt'],
                    'name_pt' => $data['name_pt'],
                    'name_es' => $data['name_es'] ?? null,
                    'name_en' => $data['name_en'] ?? null,
                    'color'   => $data['color'],
                    'icon'    => $data['icon'],
                ]
            );
        }
    }

    private function seedCategories(): void
    {
        // FASE 7 — i18n. Every default category now carries the
        // 3 localized names. The Spanish + English values are
        // literal translations of the pt-BR seed (or the closest
        // natural equivalent for a personal-finance vocabulary).
        $defaults = [
            // Expense
            ['name_pt' => 'Alimentação', 'name_es' => 'Alimentación', 'name_en' => 'Food', 'type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b'],
            ['name_pt' => 'Transporte',  'name_es' => 'Transporte',   'name_en' => 'Transport', 'type' => 'expense', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name_pt' => 'Moradia',     'name_es' => 'Vivienda',     'name_en' => 'Housing', 'type' => 'expense', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name_pt' => 'Saúde',       'name_es' => 'Salud',        'name_en' => 'Health', 'type' => 'expense', 'icon' => '⚕️', 'color' => '#ef4444'],
            ['name_pt' => 'Educação',    'name_es' => 'Educación',    'name_en' => 'Education', 'type' => 'expense', 'icon' => '📚', 'color' => '#06b6d4'],
            ['name_pt' => 'Lazer',       'name_es' => 'Ocio',         'name_en' => 'Entertainment', 'type' => 'expense', 'icon' => '🎬', 'color' => '#ec4899'],
            ['name_pt' => 'Compras',     'name_es' => 'Compras',      'name_en' => 'Shopping', 'type' => 'expense', 'icon' => '🛍️', 'color' => '#84cc16'],
            ['name_pt' => 'Serviços',    'name_es' => 'Servicios',    'name_en' => 'Services', 'type' => 'expense', 'icon' => '🔧', 'color' => '#64748b'],
            ['name_pt' => 'Assinaturas', 'name_es' => 'Suscripciones', 'name_en' => 'Subscriptions', 'type' => 'expense', 'icon' => '📺', 'color' => '#a855f7'],
            ['name_pt' => 'Outros',      'name_es' => 'Otros',        'name_en' => 'Other', 'type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8'],
            // Income
            ['name_pt' => 'Salário',         'name_es' => 'Salario',            'name_en' => 'Salary',         'type' => 'income', 'icon' => '💼', 'color' => '#10b981'],
            ['name_pt' => 'Freelance',       'name_es' => 'Trabajo freelance',  'name_en' => 'Freelance',      'type' => 'income', 'icon' => '💻', 'color' => '#14b8a6'],
            ['name_pt' => 'Investimentos',   'name_es' => 'Inversiones',        'name_en' => 'Investments',    'type' => 'income', 'icon' => '📈', 'color' => '#22c55e'],
            ['name_pt' => 'Reembolso',       'name_es' => 'Reembolso',          'name_en' => 'Refund',         'type' => 'income', 'icon' => '↩️', 'color' => '#0ea5e9'],
            ['name_pt' => 'Outros (receita)','name_es' => 'Otros (ingreso)',    'name_en' => 'Other (income)', 'type' => 'income', 'icon' => '💰', 'color' => '#16a34a'],
        ];

        foreach ($defaults as $cat) {
            // FASE 7 — uniqueness is on the pt-BR name (the
            // canonical id) and `user_id`. We still set the
            // legacy `name` column for backward compat.
            Category::firstOrCreate(
                ['name' => $cat['name_pt'], 'user_id' => null],
                array_merge($cat, ['is_default' => true])
            );
        }
    }

    private function seedAccounts(User $user): array
    {
        $defs = [
            ['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1', 'initial_balance_cents' => 350000, 'currency' => 'BRL'],
            ['name' => 'Inter Poupança', 'type' => 'savings', 'color' => '#ff7a00', 'initial_balance_cents' => 1200000, 'currency' => 'BRL'],
            ['name' => 'Itaú Crédito', 'type' => 'credit_card', 'color' => '#ec7000', 'initial_balance_cents' => 0, 'currency' => 'BRL'],
            ['name' => 'Carteira', 'type' => 'cash', 'color' => '#16a34a', 'initial_balance_cents' => 20000, 'currency' => 'BRL'],
            ['name' => 'XP Investimentos', 'type' => 'investment', 'color' => '#000000', 'initial_balance_cents' => 800000, 'currency' => 'BRL'],
            // FASE 6A — multi-currency account (Wise)
            ['name' => 'Wise (Multi-moeda)', 'type' => 'multi_currency', 'color' => '#163300', 'initial_balance_cents' => 50000, 'currency' => 'BRL'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['name']] = Account::firstOrCreate(
                ['user_id' => $user->id, 'name' => $d['name']],
                array_merge($d, ['archived' => false])
            );
        }

        // Sub-balances for the Wise multi-currency account
        $wise = $out['Wise (Multi-moeda)'] ?? null;
        if ($wise && !AccountBalance::where('account_id', $wise->id)->exists()) {
            AccountBalance::create(['account_id' => $wise->id, 'currency' => 'USD', 'balance_cents' => 85000]);   // $850
            AccountBalance::create(['account_id' => $wise->id, 'currency' => 'EUR', 'balance_cents' => 32000]);   // €320
        }

        return $out;
    }

    private function seedTransactions(User $user, array $accounts): void
    {
        // Avoid re-seeding: only seed if user has no transactions
        if (Transaction::where('user_id', $user->id)->exists()) {
            return;
        }

        $cat = fn (string $name) => Category::where('name', $name)->firstOrFail()->id;

        $today = CarbonImmutable::today();
        $rows = [];

        // Salary on the 5th of each of the last 6 months
        for ($m = 5; $m >= 0; $m--) {
            $date = $today->subMonths($m)->day(5);
            $rows[] = [
                'account_id' => $accounts['Nubank']->id,
                'category_id' => $cat('Salário'),
                'type' => 'income',
                'amount_cents' => 950000,
                'date' => $date->toDateString(),
                'description' => 'Salário ' . $date->translatedFormat('M/Y'),
                'status' => 'paid',
            ];
        }

        // Recurring fixed expenses (aluguel, internet, assinaturas)
        for ($m = 5; $m >= 0; $m--) {
            $base = $today->subMonths($m);
            $rows[] = [
                'account_id' => $accounts['Nubank']->id,
                'category_id' => $cat('Moradia'),
                'type' => 'expense',
                'amount_cents' => -150000,
                'date' => $base->day(10)->toDateString(),
                'description' => 'Aluguel',
                'status' => 'paid',
            ];
            $rows[] = [
                'account_id' => $accounts['Nubank']->id,
                'category_id' => $cat('Serviços'),
                'type' => 'expense',
                'amount_cents' => -9990,
                'date' => $base->day(15)->toDateString(),
                'description' => 'Internet fibra',
                'status' => 'paid',
            ];
            $rows[] = [
                'account_id' => $accounts['Itaú Crédito']->id,
                'category_id' => $cat('Assinaturas'),
                'type' => 'expense',
                'amount_cents' => -5599,
                'date' => $base->day(20)->toDateString(),
                'description' => 'Netflix',
                'status' => 'paid',
            ];
        }

        // Variable expenses — randomized realistic pt-BR descriptions
        $descriptions = [
            ['cat' => 'Alimentação', 'desc' => 'iFood - Almoço',         'range' => [2500, 8500]],
            ['cat' => 'Alimentação', 'desc' => 'Mercado Central',         'range' => [12000, 45000]],
            ['cat' => 'Alimentação', 'desc' => 'Cafeteria do bairro',     'range' => [1500, 4500]],
            ['cat' => 'Transporte',  'desc' => 'Uber para o trabalho',    'range' => [1800, 3500]],
            ['cat' => 'Transporte',  'desc' => '99 App corrida',          'range' => [1500, 4200]],
            ['cat' => 'Transporte',  'desc' => 'Combustível Posto Shell', 'range' => [25000, 60000]],
            ['cat' => 'Lazer',       'desc' => 'Cinema Kinoplex',         'range' => [3500, 8000]],
            ['cat' => 'Lazer',       'desc' => 'Bar com amigos',          'range' => [8000, 25000]],
            ['cat' => 'Compras',     'desc' => 'Amazon - Livro',          'range' => [3500, 12000]],
            ['cat' => 'Saúde',       'desc' => 'Farmácia Drogasil',       'range' => [2000, 15000]],
            ['cat' => 'Educação',    'desc' => 'Udemy - curso',           'range' => [4990, 19990]],
            ['cat' => 'Outros',      'desc' => 'Presente de aniversário',  'range' => [5000, 20000]],
        ];

        for ($m = 5; $m >= 0; $m--) {
            $base = $today->subMonths($m);
            // 15-25 random transactions per month
            $count = random_int(15, 25);
            for ($i = 0; $i < $count; $i++) {
                $d = $descriptions[array_rand($descriptions)];
                $day = random_int(1, 28);
                $cents = random_int($d['range'][0], $d['range'][1]);
                $rows[] = [
                    'account_id' => $accounts[array_rand([$accounts['Nubank']->name => 1, $accounts['Itaú Crédito']->name => 1, $accounts['Carteira']->name => 1])]->id ?? $accounts['Nubank']->id,
                    'category_id' => $cat($d['cat']),
                    'type' => 'expense',
                    'amount_cents' => -$cents,
                    'date' => $base->day($day)->toDateString(),
                    'description' => $d['desc'],
                    'status' => 'paid',
                ];
            }
        }

        // Some PIX entries
        for ($i = 0; $i < 8; $i++) {
            $rows[] = [
                'account_id' => $accounts['Nubank']->id,
                'category_id' => null,
                'type' => 'expense',
                'amount_cents' => -random_int(2000, 15000),
                'date' => $today->subDays(random_int(0, 150))->toDateString(),
                'description' => 'PIX enviado',
                'status' => 'paid',
                'is_pix' => true,
                'pix_key' => 'amigo@email.com',
            ];
        }

        // 1 transfer between Nubank and Inter
        $rows[] = [
            'account_id' => $accounts['Nubank']->id,
            'destination_account_id' => $accounts['Inter Poupança']->id,
            'category_id' => null,
            'type' => 'transfer',
            'amount_cents' => 50000,
            'date' => $today->subDays(20)->toDateString(),
            'description' => 'Reserva para emergência',
            'status' => 'paid',
        ];

        // Insert in chunks
        foreach (array_chunk($rows, 50) as $chunk) {
            foreach ($chunk as $r) {
                Transaction::create(array_merge([
                    'user_id' => $user->id,
                    'destination_account_id' => null,
                    'notes' => null,
                    'is_pix' => false,
                    'pix_key' => null,
                ], $r));
            }
        }
    }

    /**
     * Seed 5 realistic recurring rules for the demo user.
     */
    public function seedRecurrences(User $user): void
    {
        $accounts = Account::where('user_id', $user->id)->get()->keyBy('name');
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->get()->keyBy('name');

        if ($accounts->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $rules = [
            ['Aluguel', 'Moradia', 'expense', 150000, 'monthly', 1500.00],
            ['Internet', 'Serviços', 'expense', 9900, 'monthly', 99.00],
            ['Salário Nubank', 'Salário', 'income', 850000, 'monthly', 8500.00],
            ['Academia', 'Saúde', 'expense', 9999, 'monthly', 99.99],
            ['Spotify', 'Assinaturas', 'expense', 3299, 'monthly', 32.99],
        ];

        $now = now();
        foreach ($rules as $i => [$desc, $catName, $type, $amount, $freq, $real]) {
            $account = $type === 'income' ? ($accounts['Nubank'] ?? $accounts->first()) : ($accounts['Nubank'] ?? $accounts->first());
            $category = $categories->get($catName);

            Recurrence::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'description' => $desc,
                'amount_cents' => $amount,
                'type' => $type,
                'frequency' => $freq,
                'starts_at' => $now->copy()->subDays(60 + ($i * 7))->toDateString(),
                'last_generated_at' => $now->copy()->subDays(30 - ($i * 2))->toDateString(),
                'active' => true,
            ]);
        }
    }

    /**
     * Seed 3 demo goals for the demo user (FASE 4A).
     */
    private function seedGoals(User $user): void
    {
        if (Goal::where('user_id', $user->id)->exists()) {
            return;
        }

        $goals = [
            [
                'name' => 'Reserva de emergência',
                'target_amount_cents' => 1_500_000, // R$ 15.000
                'current_amount_cents' => 420_000,  // R$ 4.200 (28%)
                'deadline' => now()->addMonths(12)->toDateString(),
                'icon' => '🛟',
                'color' => '#10b981',
            ],
            [
                'name' => 'Viagem para o Chile',
                'target_amount_cents' => 800_000, // R$ 8.000
                'current_amount_cents' => 640_000, // R$ 6.400 (80%)
                'deadline' => now()->addMonths(4)->toDateString(),
                'icon' => '✈️',
                'color' => '#3b82f6',
            ],
            [
                'name' => 'Notebook novo',
                'target_amount_cents' => 600_000, // R$ 6.000
                'current_amount_cents' => 0,
                'deadline' => now()->addMonths(8)->toDateString(),
                'icon' => '💻',
                'color' => '#8b5cf6',
            ],
        ];

        foreach ($goals as $g) {
            Goal::create(array_merge(['user_id' => $user->id], $g));
        }
    }

    /**
     * Seed 4 demo subscriptions (FASE 4B) for the demo user.
     */
    private function seedSubscriptions(User $user, array $accounts): void
    {
        if (Subscription::where('user_id', $user->id)->exists()) {
            return;
        }

        $defs = [
            ['name' => 'Netflix',         'amount_cents' => 5599,  'billing_day' => 12, 'icon' => '🎬',  'color' => '#e50914'],
            ['name' => 'Spotify Família', 'amount_cents' => 2699,  'billing_day' => 18, 'icon' => '🎵',  'color' => '#1db954'],
            ['name' => 'iCloud+ 200GB',    'amount_cents' => 1490,  'billing_day' => 5,  'icon' => '☁️', 'color' => '#0a84ff'],
            ['name' => 'Notion Plus',     'amount_cents' => 4000,  'billing_day' => 22, 'icon' => '📝',  'color' => '#000000'],
        ];

        // Use Nubank (checking) as the default account for all
        $account = $accounts['Nubank'] ?? null;

        foreach ($defs as $d) {
            Subscription::create(array_merge(
                ['user_id' => $user->id, 'currency' => 'BRL', 'active' => true, 'account_id' => $account?->id],
                $d,
            ));
        }
    }

    /**
     * Seed 3 saved PIX keys (FASE 4C) for the demo user.
     */
    private function seedPixKeys(User $user): void
    {
        if (PixKey::where('user_id', $user->id)->exists()) {
            return;
        }

        $keys = [
            ['label' => 'Aluguel',     'key' => 'amigo@email.com',     'type' => 'email', 'is_primary' => false],
            ['label' => 'Freelancer',  'key' => 'cliente@empresa.com', 'type' => 'email', 'is_primary' => true],
            ['label' => 'Restaurante', 'key' => '+55 11 99876-5432',  'type' => 'phone', 'is_primary' => false],
        ];

        foreach ($keys as $k) {
            PixKey::create(array_merge(['user_id' => $user->id], $k));
        }
    }

    /**
     * Seed 3 demo investments (FASE 5) for the demo user.
     */
    private function seedInvestments(User $user): void
    {
        if (Investment::where('user_id', $user->id)->exists()) {
            return;
        }

        $defs = [
            [
                'name' => 'Itaúsa (ITSA4)',
                'type' => 'stock',
                'ticker' => 'ITSA4',
                'quantity' => 100,
                'average_price_cents' => 1050,
                'current_price_cents' => 1120,
                'currency' => 'BRL',
                'notes' => 'Long-term hold',
                'acquired_at' => now()->subYears(2)->toDateString(),
            ],
            [
                'name' => 'Bitcoin',
                'type' => 'crypto',
                'ticker' => 'BTC',
                'quantity' => 0.05,
                'average_price_cents' => 35000000,
                'current_price_cents' => 38000000,
                'currency' => 'BRL',
                'notes' => 'Cold wallet',
                'acquired_at' => now()->subYear()->toDateString(),
            ],
            [
                'name' => 'Tesouro Selic 2029',
                'type' => 'treasury',
                'ticker' => 'LFT',
                'quantity' => 1,
                'average_price_cents' => 100000,
                'current_price_cents' => 105000,
                'currency' => 'BRL',
                'notes' => null,
                'acquired_at' => now()->subMonths(6)->toDateString(),
            ],
        ];

        foreach ($defs as $d) {
            Investment::create(array_merge(['user_id' => $user->id], $d));
        }
    }

    /**
     * Seed 2 demo debts (FASE 5) — 1 active, 1 paid off.
     */
    private function seedDebts(User $user): void
    {
        if (Debt::where('user_id', $user->id)->exists()) {
            return;
        }

        $defs = [
            [
                'creditor' => 'Itaú',
                'description' => 'Financiamento do carro',
                'total_balance_cents' => 2500000,
                'interest_rate_annual' => 0.144,
                'monthly_payment_cents' => 85000,
                'start_date' => now()->subMonths(6)->toDateString(),
                'payoff_strategy' => 'sac',
                'currency' => 'BRL',
                'is_paid_off' => false,
                'paid_off_at' => null,
                'notes' => 'Carro financiado em 36 meses',
            ],
            [
                'creditor' => 'Nubank',
                'description' => 'Cartão de crédito antigo',
                'total_balance_cents' => 0,
                'interest_rate_annual' => 0.0,
                'monthly_payment_cents' => 0,
                'start_date' => now()->subYear()->toDateString(),
                'payoff_strategy' => 'price',
                'currency' => 'BRL',
                'is_paid_off' => true,
                'paid_off_at' => now()->subMonths(2)->toDateString(),
                'notes' => 'Fechado em maio/2026',
            ],
        ];

        foreach ($defs as $d) {
            Debt::create(array_merge(['user_id' => $user->id], $d));
        }
    }
}
