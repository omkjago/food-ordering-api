<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PesananItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pesanan_id',
        'menu_id',
        'jumlah',
        'catatan', // Tambahkan ini
    ];

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function addons()
    {
        return $this->hasMany(PesananItemAddon::class, 'pesanan_item_id'); // Sesuaikan 'pesanan_item_id' jika nama kolom foreign key berbeda
    }
}