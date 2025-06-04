<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePesananItemAddOnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pesanan_item_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_item_id')->constrained('pesanan_items')->onDelete('cascade');
            $table->foreignId('add_on_id')->constrained('add_ons')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['pesanan_item_id', 'add_on_id']); // Mencegah duplikasi add-on pada item yang sama
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pesanan_item_add_ons');
    }
}