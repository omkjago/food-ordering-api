<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategorySubcategoryToAddOnsTable extends Migration
{
    public function up()
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('harga');
            $table->string('sub_kategori')->nullable()->after('kategori');
        });
    }

    public function down()
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'sub_kategori']);
        });
    }
}