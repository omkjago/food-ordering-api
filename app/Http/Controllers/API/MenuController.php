<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\AddOn; // Import model AddOn
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


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
            ->select(
                'menus.id as menu_id',
                'menus.nama',
                'menus.diskripsi',
                'menus.harga',
                'menus.image_path',
                'menus.stok',
                'menus.kategori', // Tambahkan kolom 'kategori' di sini
                DB::raw('SUM(pesanan_items.jumlah) as total_pesanan')
            )
            ->groupBy('menus.id', 'menus.nama','menus.diskripsi', 'menus.harga', 'menus.image_path', 'menus.stok', 'menus.kategori') // Tambahkan 'kategori' ke GROUP BY
            ->orderByDesc('total_pesanan') // Urutkan berdasarkan jumlah pemesanan terbanyak
            ->limit(5) // Ambil 5 menu terbanyak
            ->get();

        // Mengembalikan data favorit dalam bentuk JSON
        return response()->json($favorit);
    }

    /**
     * Get all available add-ons from the database.
     */
    public function getAddOns()
    {
        return response()->json(AddOn::all());
    }
}
