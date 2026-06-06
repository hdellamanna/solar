<?php

namespace App\Services;

use App\Exceptions\InvalidTransactionSplitException;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Support\Facades\DB;

/**
 * Splits a transaction's amount across multiple users.
 *
 * The parent transaction's amount_cents is the source of truth and is kept
 * signed (negative for expenses, positive for incomes). Splits inherit the
 * same sign so per-user balances always sum back to the original total.
 */
class TransactionSplitService
{
    /**
     * Split the transaction equally among the given user ids.
     *
     * Because integer division can leave a remainder of up to (count-1)
     * cents, the cents are distributed one-by-one across the first splits
     * so the sum matches the transaction total exactly.
     *
     * @param  array<int>  $userIds
     * @return array<int, TransactionSplit>
     */
    public function splitEqually(Transaction $transaction, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (count($userIds) < 2) {
            throw new InvalidTransactionSplitException('A divisao igual requer pelo menos 2 pessoas.');
        }

        $total = (int) $transaction->amount_cents;
        $count = count($userIds);
        $base = intdiv($total, $count);
        $remainder = $total - ($base * $count); // residual cents, sign follows $total

        $amounts = [];
        foreach ($userIds as $i => $_) {
            // Distribute the remainder cents one at a time to keep the sum exact.
            $amounts[] = $base + ($i < abs($remainder) ? (int) ($remainder / max(abs($remainder), 1)) : 0);
        }
        // The simpler/cleaner distribution: add (or subtract) 1 cent to the
        // first abs($remainder) splits in the same sign direction.
        $amounts = array_fill(0, $count, $base);
        if ($remainder !== 0) {
            $step = $remainder > 0 ? 1 : -1;
            for ($i = 0; $i < abs($remainder); $i++) {
                $amounts[$i] += $step;
            }
        }

        return $this->apply($transaction, $userIds, $amounts);
    }

    /**
     * Split the transaction by percentage. Percentages are integers (0-100)
     * and the keys must match the user_ids.
     *
     * @param  array<int|string, int|float>  $percentages  e.g. [userId => 50, userId => 30, userId => 20]
     * @return array<int, TransactionSplit>
     */
    public function splitByPercentage(Transaction $transaction, array $percentages): array
    {
        if (empty($percentages)) {
            throw new InvalidTransactionSplitException('Informe os percentuais da divisao.');
        }

        $total = (int) $transaction->amount_cents;
        $sumPct = 0;
        foreach ($percentages as $pct) {
            $sumPct += (int) $pct;
        }
        if ($sumPct !== 100) {
            throw new InvalidTransactionSplitException("Os percentuais devem somar 100% (recebido: {$sumPct}%).");
        }

        $userIds = [];
        $amounts = [];
        $allocated = 0;
        $lastIndex = count($percentages) - 1;

        $i = 0;
        foreach ($percentages as $userId => $pct) {
            $userId = (int) $userId;
            $userIds[] = $userId;
            if ($i === $lastIndex) {
                // Last split absorbs rounding remainder so the sum is exact.
                $amounts[] = $total - $allocated;
            } else {
                $amt = (int) round($total * ((int) $pct) / 100);
                $amounts[] = $amt;
                $allocated += $amt;
            }
            $i++;
        }

        return $this->apply($transaction, $userIds, $amounts);
    }

    /**
     * Split the transaction by exact amounts (in cents). The amounts must
     * sum to the transaction's amount_cents.
     *
     * @param  array<int|string, int>  $amounts  e.g. [userId => 5000, userId => 5000]
     * @return array<int, TransactionSplit>
     */
    public function splitByAmount(Transaction $transaction, array $amounts): array
    {
        if (empty($amounts)) {
            throw new InvalidTransactionSplitException('Informe os valores da divisao.');
        }

        $userIds = [];
        $cents = [];
        foreach ($amounts as $userId => $amt) {
            $userIds[] = (int) $userId;
            $cents[] = (int) $amt;
        }

        $sum = array_sum($cents);
        // The sum of parts must match the absolute total of the transaction.
        $txTotal = (int) $transaction->amount_cents;
        $sign = $txTotal < 0 ? -1 : 1;
        if ($sum !== abs($txTotal)) {
            throw new InvalidTransactionSplitException(
                "A soma das partes (R$ " . number_format($sum / 100, 2, ',', '.') .
                ') difere do total da transacao (R$ ' . number_format($txTotal / 100, 2, ',', '.') . ').'
            );
        }

        // Apply the transaction's sign so splits inherit the sign of the parent (expense => negative).
        $signedCents = array_map(fn ($c) => $c * $sign, $cents);
        return $this->apply($transaction, $userIds, $signedCents);
    }

    /**
     * Toggle the "paid" status of a single split.
     */
    public function togglePaid(TransactionSplit $split): TransactionSplit
    {
        $split->is_paid = ! $split->is_paid;
        $split->paid_at = $split->is_paid ? now() : null;
        $split->save();
        return $split;
    }

    /**
     * Replace the existing splits for a transaction with the provided list.
     *
     * @param  array<int, array{user_id: int, category_id?: int|null, description?: string|null, amount_cents: int, paid_by_user_id?: int|null}>  $splits
     * @return array<int, TransactionSplit>
     */
    public function replaceSplits(Transaction $transaction, array $splits): array
    {
        return DB::transaction(function () use ($transaction, $splits) {
            // Validate the sum before persisting anything; throw so the controller
            // can return back()->withErrors(...) to the user.
            $sum = array_sum(array_map(fn ($s) => (int) $s['amount_cents'], $splits));
            $txTotal = (int) $transaction->amount_cents;
            if (abs($sum) !== abs($txTotal)) {
                throw new InvalidTransactionSplitException(
                    "A soma das partes (R$ " . number_format($sum / 100, 2, ',', '.') .
                    ') difere do total da transacao (R$ ' . number_format($txTotal / 100, 2, ',', '.') . ').'
                );
            }

            $transaction->splits()->delete();

            $created = [];
            foreach ($splits as $s) {
                $created[] = TransactionSplit::create([
                    'transaction_id' => $transaction->id,
                    'user_id' => (int) $s['user_id'],
                    'category_id' => isset($s['category_id']) ? (int) $s['category_id'] : null,
                    'paid_by_user_id' => isset($s['paid_by_user_id']) ? (int) $s['paid_by_user_id'] : null,
                    'description' => $s['description'] ?? null,
                    'amount_cents' => (int) $s['amount_cents'],
                    'is_paid' => false,
                ]);
            }
            return $created;
        });
    }

    /**
     * Persist the provided user/amount pairs and verify the sum.
     *
     * @param  array<int>  $userIds
     * @param  array<int>  $amounts
     * @return array<int, TransactionSplit>
     */
    private function apply(Transaction $transaction, array $userIds, array $amounts): array
    {
        if (count($userIds) !== count($amounts)) {
            throw new InvalidTransactionSplitException('Quantidade de usuarios e valores nao confere.');
        }
        $sum = array_sum($amounts);
        if ($sum !== (int) $transaction->amount_cents) {
            throw new InvalidTransactionSplitException(
                "A soma das partes (R$ " . number_format($sum / 100, 2, ',', '.') .
                ') difere do total da transacao (R$ ' . number_format($transaction->amount_cents / 100, 2, ',', '.') . ').'
            );
        }

        $splits = [];
        foreach ($userIds as $i => $userId) {
            $splits[] = [
                'user_id' => (int) $userId,
                'amount_cents' => (int) $amounts[$i],
            ];
        }
        return $this->replaceSplits($transaction, $splits);
    }
}
