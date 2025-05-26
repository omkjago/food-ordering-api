<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pesanans', function (Blueprint $table) {
            $table->id();
            $table->char('order_token', 36)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('meja_id')->constrained('mejas');
            $table->enum('status', ['pending', 'aktif', 'selesai']);
            $table->decimal('total_harga', 10, 2);
            $table->timestamps();
            // $table->foreignId('pemesan_info_id')->nullable()->constrained('pemesan_infos')->onDelete('cascade'); // HAPUS BARIS INI DULU
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesanans');
    }
};