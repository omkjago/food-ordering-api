<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
{
    \App\Models\User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    \App\Models\User::create([
        'name' => 'Member',
        'email' => 'member@example.com',
        'password' => bcrypt('password'),
        'role' => 'member',
    ]);
    \App\Models\Meja::factory(10)->create();
    \App\Models\Menu::factory(20)->create();
    \App\Models\Pesanan::factory(10)->create();
    \App\Models\PesananItem::factory(30)->create();
    \App\Models\Pembayaran::factory(10)->create();

}

}
