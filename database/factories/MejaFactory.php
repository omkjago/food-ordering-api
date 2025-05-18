<?php

namespace Database\Factories;

use App\Models\Meja;
use Illuminate\Database\Eloquent\Factories\Factory;

class MejaFactory extends Factory
{
    protected $model = Meja::class;

    public function definition(): array
    {
        return [
            'kode_barcode' => strtoupper($this->faker->unique()->bothify('MEJA-###')),
        ];
    }
}
