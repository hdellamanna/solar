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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = Auth::id();
        /** @var \App\Models\Tag $tag */
        $tag = $this->route('tag');

        return [
            'name'  => [
                'required', 'string', 'max:60',
                Rule::unique('tags', 'name')
                    ->ignore($tag?->id)
                    ->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'icon'  => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Custom error messages (pt-BR).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da tag é obrigatório.',
            'name.unique'   => 'Você já tem uma tag com esse nome.',
            'name.max'      => 'O nome da tag não pode ter mais de 60 caracteres.',
            'color.regex'   => 'A cor deve estar em formato hexadecimal (ex: #3b82f6).',
            'icon.max'      => 'O ícone é muito longo.',
        ];
    }
}
