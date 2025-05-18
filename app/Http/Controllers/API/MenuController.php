<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MenuController extends Controller
{
    public function index()
    {
        return response()->json(Menu::all());
    }

public function getFavorit()
{
    // Query untuk menghitung menu favorit berdasarkan pesanan yang paling sering dipesan
    $favorit = DB::table('pesanan_items')
        ->join('menus', 'pesanan_items.menu_id', '=', 'menus.id')
        ->select('menus.id as menu_id', 'menus.nama', 'menus.diskripsi','menus.harga', 'menus.image_path', DB::raw('SUM(pesanan_items.jumlah) as total_pesanan'))
        ->groupBy('menus.id', 'menus.nama','menus.diskripsi', 'menus.harga', 'menus.image_path')
        ->orderByDesc('total_pesanan') // Urutkan berdasarkan jumlah pemesanan terbanyak
        ->limit(5) // Ambil 5 menu terbanyak
        ->get();

    // Mengembalikan data favorit dalam bentuk JSON
    return response()->json($favorit);
}


}
