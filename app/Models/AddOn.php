<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    use HasFactory;

protected $fillable = ['nama', 'harga', 'image_path', 'kategori', 'sub_kategori'];

    // Jika Anda ingin melihat item pesanan mana yang memiliki add-on ini
    public function pesananItems()
    {
        return $this->belongsToMany(PesananItem::class, 'pesanan_item_add_ons');
    }
}