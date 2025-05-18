<?php

namespace Database\Factories;

use App\Models\Pesanan;
use App\Models\User;
use App\Models\Meja;
use Illuminate\Database\Eloquent\Factories\Factory;

class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()->id ?? User::factory(),
            'meja_id' => Meja::inRandomOrder()->first()->id ?? Meja::factory(),
            'status' => $this->faker->randomElement(['pending', 'aktif', 'selesai']),
            'total_harga' => 0, // akan dihitung ulang setelah item ditambahkan
        ];
    }
}
