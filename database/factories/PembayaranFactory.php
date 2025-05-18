<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        $metode = $this->faker->randomElement(['tunai', 'non-tunai']);

        return [
            'pesanan_id' => Pesanan::inRandomOrder()->first()->id ?? Pesanan::factory(),
            'metode' => $metode,
            'barcode_pembayaran' => $metode === 'non-tunai' ? $this->faker->uuid() : null,
            'status' => $this->faker->randomElement(['pending', 'completed']),
        ];
    }
}
