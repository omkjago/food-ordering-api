<?php

namespace Database\Factories;

use App\Models\PesananItem;
use App\Models\Pesanan;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

class PesananItemFactory extends Factory
{
    protected $model = PesananItem::class;

    public function definition(): array
    {
        return [
            'pesanan_id' => Pesanan::inRandomOrder()->first()->id ?? Pesanan::factory(),
            'menu_id' => Menu::inRandomOrder()->first()->id ?? Menu::factory(),
            'jumlah' => $this->faker->numberBetween(1, 3),
        ];
    }
}
