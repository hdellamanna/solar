<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD + pause/resume for the authenticated user's tracked subscriptions (FASE 4B).
 *
 * A subscription is a recurring service charge the user wants to keep an
 * eye on (Netflix, Spotify, iCloud, etc). The `next_billing_at` is derived
 * from the `billing_day` field — see {@see Subscription::getNextBillingAtAttribute()}.
 */
class SubscriptionController extends Controller
{
    /** Curated service-style icon set for the picker. */
    private const ICONS = [
        '🎬' => 'streaming', '🎵' => 'music', '🎧' => 'audio', '▶️' => 'video',
        '☁️' => 'cloud', '📝' => 'productivity', '🎨' => 'design', '🖌️' => 'art',
        '🔐' => 'security', '📦' => 'delivery', '🏰' => 'family', '🐙' => 'code',
        '📚' => 'reading', '💪' => 'gym', '🎮' => 'gaming', '📰' => 'news',
    ];

    private const COLORS = [
        '#ef4444', '#f59e0b', '#10b981', '#0ea5e9', '#3b82f6',
        '#6366f1', '#8b5cf6', '#a855f7', '#ec4899', '#64748b',
        '#000000', '#06b6d4',
    ];

    /**
     * Display a listing of the user's subscriptions with computed totals.
     */
    public function index(Request $request): Response
    {
        $userId = Auth::id();
        $showCancelled = $request->boolean('cancelled');

        $query = Subscription::with(['account', 'category'])
            ->where('user_id', $userId)
            ->whereNotNull('user_id')
            ->orderBy('cancelled_at')
            ->orderByRaw('active DESC')
            ->orderBy('name');

        if (!$showCancelled) {
            $query->notCancelled();
        }

        $subscriptions = $query->get()->map(fn (Subscription $s) => $this->serialize($s));

        $active = $subscriptions->where('active', true)->where('cancelled_at', null);
        $totals = [
            'active_count' => $active->count(),
            'monthly_cents' => (int) $active->sum('monthly_cents'),
            'yearly_cents' => (int) $active->sum('yearly_cents'),
        ];

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'totals' => $totals,
            'filters' => ['cancelled' => $showCancelled],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Subscriptions/Create', $this->formProps());
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);

        Subscription::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'amount_cents' => $amountCents,
            'currency' => 'BRL',
            'billing_day' => (int) $data['billing_day'],
            'account_id' => $data['account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'icon' => $data['icon'] ?? '🎬',
            'color' => $data['color'] ?? '#ef4444',
            'active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura cadastrada.');
    }

    public function edit(Subscription $subscription): Response
    {
        $this->authorizeOwner($subscription);

        return Inertia::render('Subscriptions/Edit', array_merge(
            $this->formProps(),
            ['subscription' => $this->serialize($subscription)],
        ));
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);

        $subscription->update([
            'name' => $data['name'],
            'amount_cents' => $amountCents,
            'billing_day' => (int) $data['billing_day'],
            'account_id' => $data['account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'icon' => $data['icon'] ?? '🎬',
            'color' => $data['color'] ?? '#ef4444',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura atualizada.');
    }

    /**
     * Soft-cancel the subscription (kept in history; excluded from active totals).
     */
    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);
        $subscription->update(['cancelled_at' => now(), 'active' => false]);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura cancelada.');
    }

    /**
     * Toggle active <-> paused. Paused keeps the row visible but excludes it
     * from monthly totals.
     */
    public function toggleActive(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);
        $subscription->update(['active' => !$subscription->active]);
        return back();
    }

    /**
     * Un-cancel (reactivate a previously cancelled subscription).
     */
    public function reactivate(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);
        $subscription->update(['cancelled_at' => null, 'active' => true]);
        return back();
    }

    protected function authorizeOwner(Subscription $s): void
    {
        abort_unless($s->user_id === Auth::id(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(): array
    {
        $userId = Auth::id();
        $accounts = \App\Models\Account::where('user_id', $userId)
            ->where('archived', false)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color', 'icon']);
        $categories = \App\Models\Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('type', 'expense')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color']);

        return [
            'accounts' => $accounts,
            'categories' => $categories,
            'icons' => array_keys(self::ICONS),
            'colors' => self::COLORS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Subscription $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'amount_cents' => $s->amount_cents,
            'amount_decimal' => $s->amount_decimal,
            'amount_formatted' => $s->amount_formatted,
            'currency' => $s->currency,
            'billing_day' => $s->billing_day,
            'next_billing_at' => $s->next_billing_at->toDateString(),
            'days_until_billing' => $s->days_until_billing,
            'monthly_cents' => $s->monthly_cents,
            'yearly_cents' => $s->yearly_cents,
            'active' => $s->active,
            'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            'is_cancelled' => $s->is_cancelled,
            'icon' => $s->icon,
            'color' => $s->color,
            'notes' => $s->notes,
            'account' => $s->account?->only(['id', 'name', 'color', 'type']),
            'category' => $s->category?->only(['id', 'name', 'color', 'icon']),
        ];
    }
}
