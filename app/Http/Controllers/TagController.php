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
     * Store a new tag. Slug is auto-generated from the name.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = Auth::id();

        Tag::create([
            'user_id' => $userId,
            'name'    => $data['name'],
            'slug'    => $this->uniqueSlug($userId, $data['name']),
            'color'   => $data['color'] ?? '#6b7280',
            'icon'    => $data['icon'] ?? null,
        ]);

        return redirect()->route('tags.index')->with('success', 'Tag criada.');
    }

    /**
     * Update a tag. Slug is regenerated if the name changes.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);

        $data = $request->validated();
        $tag->fill([
            'name'  => $data['name'],
            'color' => $data['color'] ?? '#6b7280',
            'icon'  => $data['icon'] ?? null,
        ]);

        if ($tag->isDirty('name')) {
            $tag->slug = $this->uniqueSlug($tag->user_id, $data['name'], $tag->id);
        }

        $tag->save();

        return redirect()->route('tags.index')->with('success', 'Tag atualizada.');
    }

    /**
     * Soft-delete a tag.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);
        $tag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag removida.');
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

        return back()->with('success', 'Tag vinculada.');
    }

    /**
     * Detach a transaction from a tag.
     */
    public function detach(Request $request, Tag $tag, Transaction $transaction): RedirectResponse
    {
        abort_unless($tag->user_id === Auth::id(), 403);
        abort_unless($transaction->user_id === Auth::id(), 403);

        $tag->transactions()->detach($transaction->id);

        return back()->with('success', 'Tag removida da transação.');
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
