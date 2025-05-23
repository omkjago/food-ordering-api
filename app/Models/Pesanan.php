<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Pesanan extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'meja_id',
        'status',
        'total_harga',
        'order_token',  // Pastikan ini ada di sini
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function items()
    {
        return $this->hasMany(PesananItem::class);
    }

    public function pembayaran()
    {
        return $this->hasOne(Pembayaran::class);
    }
    public function pemesanInfo()
{
    return $this->belongsTo(PemesanInfo::class);
}
}
