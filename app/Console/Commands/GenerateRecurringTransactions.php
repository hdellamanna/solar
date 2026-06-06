<?php

namespace App\Console\Commands;

use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generates transactions from active recurrences whose `next_run_at` is due.
 *
 * Usage:
 *   php artisan transactions:generate-recurring
 *   php artisan transactions:generate-recurring --days=14
 *   php artisan transactions:generate-recurring --dry-run
 */
class GenerateRecurringTransactions extends Command
{
    protected $signature = 'transactions:generate-recurring
        {--days=7 : How many days ahead to look when generating transactions}
        {--dry-run : Report what would be generated without writing to the database}';

    protected $description = 'Materialise transactions from active recurrence rules.';

    public function handle(RecurrenceService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = CarbonImmutable::today()->addDays($days);

        $this->info(sprintf(
            '%s recorrências até %s (lookahead: %d dia(s))',
            $dryRun ? '[DRY-RUN] Simulando' : 'Gerando transações a partir de',
            $cutoff->toDateString(),
            $days
        ));

        $rows = $service->generateAll($days, ! $dryRun);

        if ($rows === []) {
            $this->warn('Nenhuma recorrência ativa encontrada.');
            return self::SUCCESS;
        }

        $table = [];
        $totalCreated = 0;
        foreach ($rows as $row) {
            $r = $row['recurrence'];
            $dates = $row['dates']->map(fn ($d) => CarbonImmutable::parse($d)->format('d/m/Y'))->implode(', ');
            $table[] = [
                $r->id,
                $r->description,
                $r->type,
                $r->human_frequency,
                'R$ ' . number_format(abs($r->amount_cents) / 100, 2, ',', '.'),
                $row['count'],
                $dates ?: '—',
            ];
            $totalCreated += $row['count'];
        }

        $this->table(
            ['#', 'Descrição', 'Tipo', 'Frequência', 'Valor', 'Qtd', 'Datas previstas'],
            $table
        );

        if ($dryRun) {
            $this->info(sprintf('[DRY-RUN] %d transação(ões) seriam geradas.', $totalCreated));
            return self::SUCCESS;
        }

        $this->info(sprintf('✔ %d transação(ões) gerada(s) com sucesso.', $totalCreated));
        return self::SUCCESS;
    }
}
