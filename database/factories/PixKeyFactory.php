<?php

namespace Database\Factories;

use App\Models\PixKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PixKey>
 */
class PixKeyFactory extends Factory
{
    protected $model = PixKey::class;

    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(PixKey::TYPES));
        $key = match ($type) {
            'cpf' => fake()->numerify('###.###.###-##'),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+55 ## 9####-####'),
            'evp' => fake()->uuid(),
        };

        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Pai', 'Mãe', 'Aluguel', 'Conta conjunta', 'Freelancer', 'Amigo']),
            'key' => $key,
            'type' => $type,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
