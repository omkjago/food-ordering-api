<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pesanan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'meja_id',
        'status',
        'total_harga',
        'order_token',
        'pemesan_info_id',
        'global_notes',
    ];

    // Tambahkan properti $appends untuk menyertakan pelanggan_nama secara otomatis
    protected $appends = ['pelanggan_nama'];

    // Accessor untuk pelanggan_nama
    public function getPelangganNamaAttribute()
    {
        // Prioritaskan nama dari user jika ada
        if ($this->user && $this->user->name) {
            return $this->user->name;
        }

        // Kemudian dari pemesanInfo jika ada
        if ($this->pemesanInfo && $this->pemesanInfo->nama_pemesan) {
            return $this->pemesanInfo->nama_pemesan;
        }

        // Jika tidak ada, fallback ke 'Tamu' atau informasi meja
        if ($this->meja) {
            return 'Meja ' . ($this->meja->kode_barcode ?? $this->meja->id);
        }

        return 'Tamu';
    }


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