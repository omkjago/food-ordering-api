<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemesanInfo extends Model
{
    protected $fillable = ['pesanan_id', 'nama_pemesan', 'email_pemesan', 'no_hp'];

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

}
