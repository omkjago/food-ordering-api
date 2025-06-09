<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PesananItemAddOn extends Model
{
    use HasFactory;

    protected $table = 'pesanan_item_add_ons';

    protected $fillable = ['pesanan_item_id', 'add_on_id'];

    public function pesananItem()
    {
        return $this->belongsTo(PesananItem::class);
    }

    public function addOn()
    {
        return $this->belongsTo(AddOn::class);
    }
}