<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    /**
     * Allow any authenticated user to create their own tags.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Validation rules for creating a tag.
     *
     * FASE 7 — i18n tri-língue. The tag now carries 3 localized
     * names (`name_pt`, `name_es`, `name_en`) plus the legacy
     * `name` column (kept for slug-derivation stability and
     * pre-FASE-7 backward compatibility). The form may submit EITHER
     * the legacy `name` field OR the 3 localized fields (at least
     * one must be non-empty). The controller normalizes both
     * shapes into the same final state: a `name_pt` value (which
     * is also copied to `name` for slug purposes).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = Auth::id();

        return [
            // Legacy field — required so the pre-FASE-7 form path
            // (single `name`) keeps working. The `required_without`
            // rule says "or one of the localized fields must be
            // present" — together they implement the design-doc
            // contract: "at least one of the 3 localized fields OR
            // the legacy `name` shortcut must be non-empty".
            'name'  => [
                'required_without_all:name_pt,name_es,name_en',
                'nullable', 'string', 'max:60',
                Rule::unique('tags', 'name')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            // FASE 7 — localized names. Each is `required_without_all`
            // the other two + `name`: at least one of the 4
            // (3 localized + legacy) must be set.
            'name_pt' => [
                'required_without_all:name,name_es,name_en',
                'nullable', 'string', 'max:60',
                Rule::unique('tags', 'name_pt')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'name_es' => [
                'required_without_all:name,name_pt,name_en',
                'nullable', 'string', 'max:60',
            ],
            'name_en' => [
                'required_without_all:name,name_pt,name_es',
                'nullable', 'string', 'max:60',
            ],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'icon'  => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Custom error messages (translated per active locale).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'  => 'validation.required',
            'name.unique'    => 'validation.unique',
            'name.max'       => 'O nome da tag não pode ter mais de 60 caracteres.',
            'color.regex'    => 'A cor deve estar em formato hexadecimal (ex: #3b82f6).',
            'icon.max'       => 'O ícone é muito longo.',
        ];
    }

    /**
     * FASE 7 — normalised payload for the controller.
     *
     * Returns a `name` value derived from the highest-priority
     * available field (`name_pt` > `name`) and the 3 localized
     * fields as submitted. The controller uses this so the
     * existing slug-derivation logic keeps working.
     *
     * @return array<string, mixed>
     */
    public function normalizedData(): array
    {
        $data = $this->validated();

        $localized = array_filter([
            'name_pt' => $data['name_pt'] ?? null,
            'name_es' => $data['name_es'] ?? null,
            'name_en' => $data['name_en'] ?? null,
        ], fn ($v) => is_string($v) && $v !== '');

        // If the caller used the legacy `name` shortcut and did
        // not specify any localized field, fall back to `name` as
        // the pt-BR value. This is the post-FASE-7 happy path for
        // pre-FASE-7 forms (and the existing TagTest).
        if (empty($localized) && ! empty($data['name'])) {
            $localized['name_pt'] = $data['name'];
        }

        $out = array_merge($data, $localized);
        $out['name'] = $localized['name_pt'] ?? $data['name'] ?? null;

        return $out;
    }
}
