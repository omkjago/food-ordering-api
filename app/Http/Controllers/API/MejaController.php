<?php

// app/Http/Controllers/Api/MejaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meja;

class MejaController extends Controller
{
    public function index()
    {
        $mejas = Meja::select('id', 'kode_barcode')->get();
        return response()->json($mejas);
    }
}
