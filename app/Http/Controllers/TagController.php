<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Display the user's tag library as a grid of cards.
     */
    public function index(): Response
    {
        $tags = Tag::where('user_id', Auth::id())
            ->withCount('transactions')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
    }

    /**
     * Store a new tag. Slug is auto-generated from the pt-BR name
     * (the stable source — slug is internal, never user-facing).
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        $data = $request->normalizedData();
        $userId = Auth::id();

        Tag::create([
            'user_id' => $userId,
            'name'    => $data['name'],
            // FASE 7 — i18n tri-língue. The 3 localized variants
            // are persisted alongside the legacy `name` column. The
            // model's `creating` event keeps `name` in sync with
            // `name_pt` for backward compat.
            'name_pt' => $data['name_pt'] ?? $data['name'],
            'name_es' => $data['name_es'] ?? null,
            'name_en' => $data['name_en'] ?? null,
            'slug'    => $this->uniqueSlug($userId, $data['name']),
            'color'   => $data['color'] ?? '#6b7280',
            'icon'    => $data['icon'] ?? null,
        ]);

        return redirect()->route('tags.index')->with('success', __('app.tag_created'));
    }

    /**
     * Update a tag. Slug is regenerated if the pt-BR name changes.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);

        $data = $request->normalizedData();
        $tag->fill([
            'name'    => $data['name'],
            'name_pt' => $data['name_pt'] ?? $data['name'],
            'name_es' => $data['name_es'] ?? $tag->name_es,
            'name_en' => $data['name_en'] ?? $tag->name_en,
            'color'   => $data['color'] ?? '#6b7280',
            'icon'    => $data['icon'] ?? null,
        ]);

        if ($tag->isDirty('name')) {
            $tag->slug = $this->uniqueSlug($tag->user_id, $data['name'], $tag->id);
        }

        $tag->save();

        return redirect()->route('tags.index')->with('success', __('app.tag_updated'));
    }

    /**
     * Soft-delete a tag.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);
        $tag->delete();

        return redirect()->route('tags.index')->with('success', __('app.tag_deleted'));
    }

    /**
     * Attach a transaction to a tag (idempotent).
     */
    public function attach(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);

        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
        ]);

        $transaction = Transaction::findOrFail($data['transaction_id']);
        abort_unless($transaction->user_id === Auth::id(), 403);

        $tag->transactions()->syncWithoutDetaching([$transaction->id]);

        return back()->with('success', __('app.tag_attached'));
    }

    /**
     * Detach a transaction from a tag.
     */
    public function detach(Request $request, Tag $tag, Transaction $transaction): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);
        abort_unless($transaction->user_id === Auth::id(), 403);

        $tag->transactions()->detach($transaction->id);

        return back()->with('success', __('app.tag_detached'));
    }

    /**
     * JSON list of the user's tags with transaction counts (for autocomplete).
     */
    public function apiIndex(): JsonResponse
    {
        $tags = Tag::where('user_id', Auth::id())
            ->withCount('transactions')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color', 'icon']);

        return response()->json([
            'data' => $tags->map(fn (Tag $t) => [
                'id'                 => $t->id,
                'name'               => $t->name,
                'slug'               => $t->slug,
                'color'              => $t->color,
                'icon'               => $t->icon,
                'transaction_count'  => (int) $t->transactions_count,
            ]),
        ]);
    }

    /**
     * Generate a slug unique to the user. Optionally excludes a tag id (for updates).
     */
    private function uniqueSlug(int $userId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $i = 1;

        $query = Tag::where('user_id', $userId)->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = $base . '-' . (++$i);
            $query = Tag::where('user_id', $userId)->where('slug', $slug);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
    }
}
