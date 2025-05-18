<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Menu extends Model
{
    use HasFactory;

// app/Models/Menu.php

protected $fillable = [
    'nama',
    'harga',
    'diskripsi',
    'image_path',
];

    public function pesananItems()
    {
        return $this->hasMany(PesananItem::class);
    }
}
