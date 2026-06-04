<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populates the database with one demo user, 5 accounts, 15 default categories,
 * and ~6 months of realistic Brazilian transactions.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@solar.app'],
            [
                'name' => 'Henrique Demo',
                'password' => Hash::make('solar123'),
                'theme' => 'light',
                'email_verified_at' => now(),
            ]
        );

        $this->seedCategories();
        $accounts = $this->seedAccounts($user);
        $this->seedTransactions($user, $accounts);
    }

    private function seedCategories(): void
    {
        $defaults = [
            // Expense
            ['name' => 'Alimentação', 'type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b'],
            ['name' => 'Transporte', 'type' => 'expense', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name' => 'Moradia', 'type' => 'expense', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name' => 'Saúde', 'type' => 'expense', 'icon' => '⚕️', 'color' => '#ef4444'],
            ['name' => 'Educação', 'type' => 'expense', 'icon' => '📚', 'color' => '#06b6d4'],
            ['name' => 'Lazer', 'type' => 'expense', 'icon' => '🎬', 'color' => '#ec4899'],
            ['name' => 'Compras', 'type' => 'expense', 'icon' => '🛍️', 'color' => '#84cc16'],
            ['name' => 'Serviços', 'type' => 'expense', 'icon' => '🔧', 'color' => '#64748b'],
            ['name' => 'Assinaturas', 'type' => 'expense', 'icon' => '📺', 'color' => '#a855f7'],
            ['name' => 'Outros', 'type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8'],
            // Income
            ['name' => 'Salário', 'type' => 'income', 'icon' => '💼', 'color' => '#10b981'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻', 'color' => '#14b8a6'],
            ['name' => 'Investimentos', 'type' => 'income', 'icon' => '📈', 'color' => '#22c55e'],
            ['name' => 'Reembolso', 'type' => 'income', 'icon' => '↩️', 'color' => '#0ea5e9'],
            ['name' => 'Outros (receita)', 'type' => 'income', 'icon' => '💰', 'color' => '#16a34a'],
        ];

        foreach ($defaults as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name'], 'user_id' => null],
                array_merge($cat, ['is_default' => true])
            );
        }
    }

    private function seedAccounts(User $user): array
    {
        $defs = [
            ['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1', 'initial_balance_cents' => 350000],
            ['name' => 'Inter Poupança', 'type' => 'savings', 'color' => '#ff7a00', 'initial_balance_cents' => 1200000],
            ['name' => 'Itaú Crédito', 'type' => 'credit_card', 'color' => '#ec7000', 'initial_balance_cents' => 0],
            ['name' => 'Carteira', 'type' => 'cash', 'color' => '#16a34a', 'initial_balance_cents' => 20000],
            ['name' => 'XP Investimentos', 'type' => 'investment', 'color' => '#000000', 'initial_balance_cents' => 800000],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['name']] = Account::firstOrCreate(
                ['user_id' => $user->id, 'name' => $d['name']],
                array_merge($d, ['currency' => 'BRL', 'archived' => false])
            );
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
}
