<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Meja extends Model
{
    use HasFactory;
    protected $fillable = ['kode_barcode'];

    public function pesanans()
    {
        return $this->hasMany(Pesanan::class);
    }
}
