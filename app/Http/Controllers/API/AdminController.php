<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\PesananItem;
use Carbon\Carbon;
use DB;
use App\Models\Menu;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function aktif()
    {
        return response()->json(Pesanan::where('status', 'selesai')->with('items.menu')->get());
    }

    public function pending()
    {
        return response()->json(Pesanan::where('status', 'pending')->with('items.menu')->get());
    }

    public function validasiPesanan(Request $request)
{
    $request->validate([
        'order_token' => 'required|string'
    ]);

    $pesanan = Pesanan::where('order_token', $request->order_token)->first();

    if (!$pesanan) {
        return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
    }

    // Misalnya status 'aktif' = sukses divalidasi
    $pesanan->status = 'aktif';
    $pesanan->save();

    return response()->json(['message' => 'Pesanan berhasil divalidasi']);

}

    public function menu()
    {
        return response()->json(Menu::all());
    }
    public function menuId($id)
    {
        return Menu::findOrFail($id);
    }

    public function storeMenu(Request $request)
{
    $request->validate([
        'nama' => 'required|string',
        'harga' => 'required|numeric',
        'diskripsi' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    // Menyimpan gambar (jika ada)
    $imagePath = null;
    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $fileName = 'menu_images/' . str_replace(' ', '_', strtolower($request->nama)) .'.' . $image->getClientOriginalExtension();
        $image->storeAs('public', $fileName);
        $imagePath = $fileName;
    }

    // Menyimpan menu baru
    $menu = Menu::create([
        'nama' => $request->nama,
        'harga' => $request->harga,
        'diskripsi' => $request->diskripsi,
        'image_path' => $imagePath,
    ]);

    return response()->json(['message' => 'Menu berhasil ditambahkan', 'data' => $menu]);
}



public function updateMenu(Request $request, $id)
{
    $menu = Menu::findOrFail($id);

    $menu->nama = $request->nama;
    $menu->kategori = $request->kategori;
    $menu->harga = $request->harga;
    $menu->stok = $request->stok;
    $menu->diskripsi = $request->diskripsi;

    $imagePath = null;
    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $fileName = 'menu_images/' . str_replace(' ', '_', strtolower($request->nama)) . '.' . $image->getClientOriginalExtension();
        $image->storeAs('public', $fileName); // disimpan di storage/app/public/menu_images/
        $menu->image_path = $fileName; // ⬅️ gunakan image_path sesuai field Anda
    }


    $menu->save();

    return response()->json(['message' => 'Menu updated successfully']);
}

    public function deleteMenu($id)
    {
        $menu = Menu::findOrFail($id);
        $menu->delete();

        return response()->json(['message' => 'Menu dihapus']);
    }
    public function statistics()
    {
        // Current month data
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();
        
        // Total pendapatan (total income)
        $pendapatanBulanIni = Pesanan::where('status', 'selesai')
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->sum('total_harga');
            
        $pendapatanBulanLalu = Pesanan::where('status', 'selesai')
            ->whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->sum('total_harga');
            
        // Pesanan baru (new orders)
        $pesananBulanIni = Pesanan::whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();
            
        $pesananBulanLalu = Pesanan::whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->count();
            
        // Pelanggan baru (new customers)
        $pelangganBulanIni = User::where('role', 'user')
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();
            
        $pelangganBulanLalu = User::where('role', 'user')
            ->whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->count();
            
        // Produk terjual (products sold)
        $produkBulanIni = PesananItem::whereHas('pesanan', function($query) use ($currentMonth) {
                $query->where('status', 'selesai')
                    ->whereMonth('created_at', $currentMonth->month)
                    ->whereYear('created_at', $currentMonth->year);
            })
            ->sum('jumlah');
            
        $produkBulanLalu = PesananItem::whereHas('pesanan', function($query) use ($previousMonth) {
                $query->where('status', 'selesai')
                    ->whereMonth('created_at', $previousMonth->month)
                    ->whereYear('created_at', $previousMonth->year);
            })
            ->sum('jumlah');
        
        return response()->json([
            'pendapatan' => [
                'current' => $pendapatanBulanIni,
                'previous' => $pendapatanBulanLalu,
            ],
            'pesanan' => [
                'current' => $pesananBulanIni,
                'previous' => $pesananBulanLalu,
            ],
            'pelanggan' => [
                'current' => $pelangganBulanIni,
                'previous' => $pelangganBulanLalu,
            ],
            'produk' => [
                'current' => $produkBulanIni,
                'previous' => $produkBulanLalu,
            ],
        ]);
    }

    /**
     * Get popular products
     * New method for the dashboard connection
     */
    public function popularProducts()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();
        
        // Get all-time popular products
        $produkPopuler = DB::table('pesanan_items')
            ->join('menus', 'pesanan_items.menu_id', '=', 'menus.id')
            ->select(
                'menus.id as menu_id', 
                'menus.nama', 
                'menus.kategori', // Make sure kategori field exists or adjust accordingly
                DB::raw('SUM(pesanan_items.jumlah) as total_pesanan')
            )
            ->groupBy('menus.id', 'menus.nama', 'menus.kategori')
            ->orderByDesc('total_pesanan')
            ->limit(5)
            ->get();
            
        // Get products details and trend comparison with previous month
        $result = [];
        foreach($produkPopuler as $produk) {
            // Calculate this month's sales for the product
            $bulanIni = DB::table('pesanan_items')
                ->join('pesanans', 'pesanan_items.pesanan_id', '=', 'pesanans.id')
                ->where('pesanan_items.menu_id', $produk->menu_id)
                ->where('pesanans.status', 'selesai')
                ->whereMonth('pesanans.created_at', $currentMonth->month)
                ->whereYear('pesanans.created_at', $currentMonth->year)
                ->sum('pesanan_items.jumlah');
                
            // Calculate previous month's sales for the product
            $bulanLalu = DB::table('pesanan_items')
                ->join('pesanans', 'pesanan_items.pesanan_id', '=', 'pesanans.id')
                ->where('pesanan_items.menu_id', $produk->menu_id)
                ->where('pesanans.status', 'selesai')
                ->whereMonth('pesanans.created_at', $previousMonth->month)
                ->whereYear('pesanans.created_at', $previousMonth->year)
                ->sum('pesanan_items.jumlah');
                
            // Calculate trend (positive or negative)
            $trend = 0;
            if ($bulanLalu > 0) {
                $trend = $bulanIni - $bulanLalu;
            }
            
            // Handle null kategori
            $kategori = $produk->kategori ?? 'Lainnya';
            
            $result[] = [
                'id' => $produk->menu_id,
                'nama' => $produk->nama,
                'kategori' => $kategori,
                'jumlah_terjual' => $produk->total_pesanan,
                'trend' => $trend
            ];
        }
        
        return response()->json($result);
    }

    public function recentOrders()
{
    $pesananTerbaru = DB::table('pesanans')
    ->select(
        'pesanans.id',
        'pesanans.user_id',
        'pesanans.total_harga as total',
        'pesanans.status',
        'pesanans.created_at as tanggal',
        DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, "Tamu") as pelanggan_nama'),
        DB::raw('(SELECT COUNT(id) FROM pesanan_items WHERE pesanan_id = pesanans.id) as jumlah_produk')
    )
    ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
    ->leftJoin('pemesan_infos', 'pesanans.id', '=', 'pemesan_infos.pesanan_id')
    ->whereNotNull('pesanans.created_at')
    ->orderBy('pesanans.created_at', 'desc')
    ->limit(5)
    ->get();

return response()->json($pesananTerbaru);
}
}
