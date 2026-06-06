<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransactionSplitException;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\TransactionSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSplitTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(int $ownerId, int $amountCents, string $type = 'expense'): Transaction
    {
        $account = Account::create([
            'user_id' => $ownerId,
            'name' => 'Conta',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $signed = $type === 'expense' ? -abs($amountCents) : abs($amountCents);
        return Transaction::create([
            'user_id' => $ownerId,
            'account_id' => $account->id,
            'type' => $type,
            'amount_cents' => $signed,
            'date' => '2026-06-10',
            'description' => 'Compra dividida',
            'status' => 'paid',
        ]);
    }

    public function test_can_split_equally_between_two_people(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000); // R$ 100,00 expense (-10000)

        $splits = app(TransactionSplitService::class)->splitEqually($tx, [$owner->id, $other->id]);

        $this->assertCount(2, $splits);
        $sum = collect($splits)->sum(fn($s) => $s->amount_cents);
        $this->assertEquals(-10000, $sum, 'Sum must equal transaction total');
        $this->assertEquals(-5000, $splits[0]->amount_cents);
        $this->assertEquals(-5000, $splits[1]->amount_cents);
    }

    public function test_can_split_equally_between_three_people_with_remainder(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10001); // R$ 100,01 expense

        $splits = app(TransactionSplitService::class)->splitEqually($tx, [$owner->id, $a->id, $b->id]);

        $sum = collect($splits)->sum(fn($s) => $s->amount_cents);
        $this->assertEquals(-10001, $sum, 'Sum must equal transaction total (rounding distributed)');
        $this->assertCount(3, $splits);
    }

    public function test_can_split_by_percentage(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000);

        $splits = app(TransactionSplitService::class)->splitByPercentage($tx, [
            $owner->id => 50,
            $a->id => 30,
            $b->id => 20,
        ]);

        $sum = collect($splits)->sum(fn($s) => $s->amount_cents);
        $this->assertEquals(-10000, $sum);
        $byUser = collect($splits)->keyBy('user_id');
        $this->assertEquals(-5000, $byUser[$owner->id]->amount_cents);
        $this->assertEquals(-3000, $byUser[$a->id]->amount_cents);
        $this->assertEquals(-2000, $byUser[$b->id]->amount_cents);
    }

    public function test_can_split_by_exact_amounts(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000);

        $splits = app(TransactionSplitService::class)->splitByAmount($tx, [
            $owner->id => 4000,
            $a->id => 3500,
            $b->id => 2500,
        ]);

        $sum = collect($splits)->sum(fn($s) => $s->amount_cents);
        $this->assertEquals(-10000, $sum); // expense => splits inherit negative sign
        $this->assertCount(3, $splits);
    }

    public function test_split_rejects_wrong_sum(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000);

        $this->expectException(InvalidTransactionSplitException::class);
        app(TransactionSplitService::class)->splitByAmount($tx, [
            $owner->id => 5000,
            $a->id => 4000, // 9000, should fail
        ]);
    }

    public function test_split_by_percentage_rejects_non_100_sum(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000);

        $this->expectException(InvalidTransactionSplitException::class);
        app(TransactionSplitService::class)->splitByPercentage($tx, [
            $owner->id => 60,
            $a->id => 30, // 90, should fail
        ]);
    }

    public function test_can_toggle_split_as_paid(): void
    {
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $tx = $this->makeTransaction($owner->id, 10000);

        $splits = app(TransactionSplitService::class)->splitEqually($tx, [$owner->id, $a->id]);
        $target = $splits[0];

        $this->assertFalse($target->is_paid);

        $updated = app(TransactionSplitService::class)->togglePaid($target);
        $this->assertTrue($updated->is_paid);
        $this->assertNotNull($updated->paid_at);

        $toggled = app(TransactionSplitService::class)->togglePaid($updated->refresh());
        $this->assertFalse($toggled->is_paid);
        $this->assertNull($toggled->paid_at);
    }

    public function test_splits_are_isolated_between_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceAccount = Account::create([
            'user_id' => $alice->id, 'name' => 'Alice Conta', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $aliceTx = Transaction::create([
            'user_id' => $alice->id, 'account_id' => $aliceAccount->id,
            'type' => 'expense', 'amount_cents' => -5000,
            'date' => '2026-06-10', 'description' => 'Alice', 'status' => 'paid',
        ]);
        app(TransactionSplitService::class)->splitEqually($aliceTx, [$alice->id, $bob->id]);

        $bobAccount = Account::create([
            'user_id' => $bob->id, 'name' => 'Bob Conta', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $bobTx = Transaction::create([
            'user_id' => $bob->id, 'account_id' => $bobAccount->id,
            'type' => 'expense', 'amount_cents' => -5000,
            'date' => '2026-06-10', 'description' => 'Bob', 'status' => 'paid',
        ]);
        app(TransactionSplitService::class)->splitEqually($bobTx, [$alice->id, $bob->id]);

        $this->assertEquals(2, TransactionSplit::where('transaction_id', $aliceTx->id)->count());
        $this->assertEquals(2, TransactionSplit::where('transaction_id', $bobTx->id)->count());
        $this->assertEquals(4, TransactionSplit::count());

        // Soft-deleting Alice's transaction must NOT touch Bob's splits. The cascade
        // only fires on hard delete (forceDelete), so this also documents the safety
        // boundary for transactions in soft-delete state.
        $aliceTx->delete();
        $this->assertEquals(4, TransactionSplit::count());
        $this->assertEquals(2, TransactionSplit::where('transaction_id', $bobTx->id)->count());

        // Hard delete triggers cascade: Alice's 2 splits are removed, Bob's remain.
        $aliceTx->forceDelete();
        $this->assertEquals(2, TransactionSplit::count());
        $this->assertEquals(2, TransactionSplit::where('transaction_id', $bobTx->id)->count());
    }

    public function test_user_can_create_transaction_with_splits_via_http(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::create([
            'user_id' => $owner->id, 'name' => 'Conta', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => 'expense',
                'account_id' => $account->id,
                'amount' => '100.00',
                'date' => '2026-06-10',
                'description' => 'Jantar com amigos',
                'status' => 'paid',
                'splits' => [
                    ['user_id' => $owner->id, 'amount_cents' => -5000],
                    ['user_id' => $other->id, 'amount_cents' => -5000],
                ],
            ])
            ->assertRedirect(route('transactions.index'));

        $tx = Transaction::where('description', 'Jantar com amigos')->firstOrFail();
        $this->assertEquals(2, $tx->splits()->count());
    }

    public function test_user_cannot_create_transaction_with_invalid_split_sum(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::create([
            'user_id' => $owner->id, 'name' => 'Conta', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => 'expense',
                'account_id' => $account->id,
                'amount' => '100.00',
                'date' => '2026-06-10',
                'description' => 'Soma errada',
                'status' => 'paid',
                'splits' => [
                    ['user_id' => $owner->id, 'amount_cents' => -5000],
                    ['user_id' => $other->id, 'amount_cents' => -4000], // 9000 != 10000
                ],
            ])
            ->assertSessionHasErrors('splits');

        $this->assertEquals(0, Transaction::where('description', 'Soma errada')->count());
    }

    public function test_other_user_cannot_view_transaction_splits(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $account = Account::create([
            'user_id' => $owner->id, 'name' => 'Conta', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $tx = Transaction::create([
            'user_id' => $owner->id, 'account_id' => $account->id,
            'type' => 'expense', 'amount_cents' => -5000,
            'date' => '2026-06-10', 'description' => 'Privado', 'status' => 'paid',
        ]);

        $this->actingAs($stranger)
            ->get(route('transactions.show', $tx->id))
            ->assertStatus(403);
    }
}
