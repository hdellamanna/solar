<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    /**
     * Allow any authenticated user to update their own tags.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Validation rules for updating a tag.
     *
     * FASE 7 — i18n. Same shape as {@see StoreTagRequest::rules()};
     * accepts either the legacy `name` field or the 3 localized
     * fields. See StoreTagRequest docblock for the full rationale.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = Auth::id();
        /** @var \App\Models\Tag $tag */
        $tag = $this->route('tag');

        return [
            'name'  => [
                'required_without_all:name_pt,name_es,name_en',
                'nullable', 'string', 'max:60',
                Rule::unique('tags', 'name')
                    ->ignore($tag?->id)
                    ->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'name_pt' => [
                'required_without_all:name,name_es,name_en',
                'nullable', 'string', 'max:60',
                Rule::unique('tags', 'name_pt')
                    ->ignore($tag?->id)
                    ->where(fn ($q) => $q->where('user_id', $userId)),
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
            'name.required' => 'validation.required',
            'name.unique'   => 'validation.unique',
            'name.max'      => 'O nome da tag não pode ter mais de 60 caracteres.',
            'color.regex'   => 'A cor deve estar em formato hexadecimal (ex: #3b82f6).',
            'icon.max'      => 'O ícone é muito longo.',
        ];
    }

    /**
     * FASE 7 — normalised payload for the controller.
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

        if (empty($localized) && ! empty($data['name'])) {
            $localized['name_pt'] = $data['name'];
        }

        $out = array_merge($data, $localized);
        $out['name'] = $localized['name_pt'] ?? $data['name'] ?? null;

        return $out;
    }
}
