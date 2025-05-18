<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Pembayaran extends Model
{
    use HasFactory;
    protected $fillable = ['pesanan_id', 'metode', 'barcode_pembayaran', 'status'];

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }
}
