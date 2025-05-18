<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->word(),
            'diskripsi' => $this->faker->word(),
            'harga' => $this->faker->numberBetween(10000, 100000),
        ];
    }
}
