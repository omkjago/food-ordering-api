<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\PesananItem;
use Carbon\Carbon;
use DB;
use App\Models\Menu;
use App\Models\AddOn; // Import model AddOn
use Illuminate\Http\Request;
use App\Models\PemesanInfo;
use App\Models\Pembayaran;
use App\Models\PesananItemAddOn; // Add this line

class AdminController extends Controller
{
    public function aktif()
    {
        $pesanans = Pesanan::whereIn('status', ['aktif', 'selesai'])
            ->with(['items.menu', 'user', 'pemesanInfo'])
            ->get();

        return response()->json($pesanans);
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

        $pesanan = Pesanan::where('order_token', $request->order_token)
                    ->with(['items.menu', 'user', 'meja', 'pemesanInfo'])
                    ->first();

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        if ($pesanan->status !== 'selesai') {
            $pesanan->status = 'aktif';
            $pesanan->save();
        }

        return response()->json($pesanan);
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

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'menu_images/' . str_replace(' ', '_', strtolower($request->nama)) .'.' . $image->getClientOriginalExtension();
            $image->storeAs('public', $fileName);
            $imagePath = $fileName;
        }

        $menu = Menu::create([
            'nama' => $request->nama,
            'harga' => $request->harga,
            'diskripsi' => $request->diskripsi,
            'kategori' => $request->kategori,
            'stok' => $request->stok,
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
            $image->storeAs('public', $fileName);
            $menu->image_path = $fileName;
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
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();

        $pendapatanBulanIni = Pesanan::where('status', 'selesai')
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->sum('total_harga');

        $pendapatanBulanLalu = Pesanan::where('status', 'selesai')
            ->whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->sum('total_harga');

        $pesananBulanIni = Pesanan::whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $pesananBulanLalu = Pesanan::whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->count();

        $pelangganBulanIni = User::where('role', 'user')
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $pelangganBulanLalu = User::where('role', 'user')
            ->whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->count();

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

    public function popularProducts()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();

        $produkPopuler = DB::table('pesanan_items')
            ->join('menus', 'pesanan_items.menu_id', '=', 'menus.id')
            ->select(
                'menus.id as menu_id',
                'menus.nama',
                'menus.kategori',
                DB::raw('SUM(pesanan_items.jumlah) as total_pesanan')
            )
            ->groupBy('menus.id', 'menus.nama', 'menus.kategori')
            ->orderByDesc('total_pesanan')
            ->limit(5)
            ->get();

        $result = [];
        foreach($produkPopuler as $produk) {
            $bulanIni = DB::table('pesanan_items')
                ->join('pesanans', 'pesanan_items.pesanan_id', '=', 'pesanans.id')
                ->where('pesanan_items.menu_id', $produk->menu_id)
                ->where('pesanans.status', 'selesai')
                ->whereMonth('pesanans.created_at', $currentMonth->month)
                ->whereYear('pesanans.created_at', $currentMonth->year)
                ->sum('pesanan_items.jumlah');

            $bulanLalu = DB::table('pesanan_items')
                ->join('pesanans', 'pesanan_items.pesanan_id', '=', 'pesanans.id')
                ->where('pesanan_items.menu_id', $produk->menu_id)
                ->where('pesanans.status', 'selesai')
                ->whereMonth('pesanans.created_at', $previousMonth->month)
                ->whereYear('pesanans.created_at', $previousMonth->year)
                ->sum('pesanan_items.jumlah');

            $trend = 0;
            if ($bulanLalu > 0) {
                $trend = $bulanIni - $bulanLalu;
            }

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

    public function confirmPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:pesanans,id',
            'metode_pembayaran' => 'required|in:tunai,non-tunai',
            'amount_paid' => 'required_if:metode_pembayaran,tunai|numeric|min:0',
        ]);

        $pesanan = Pesanan::find($request->order_id);

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($pesanan->status !== 'pending') {
            return response()->json(['message' => 'Pesanan ini sudah dibayar atau diproses. Status: ' . $pesanan->status], 400);
        }

        $totalHarga = $pesanan->total_harga;
        $amountPaid = $request->amount_paid ?? 0;
        $change = 0;

        if ($request->metode_pembayaran === 'tunai') {
            if ($amountPaid < $totalHarga) {
                return response()->json(['message' => 'Uang yang diberikan tidak mencukupi.'], 400);
            }
            $change = $amountPaid - $totalHarga;
        }

        $pesanan->status = 'selesai';
        $pesanan->save();

        $pembayaran = $pesanan->pembayaran()->create([
            'metode_pembayaran' => $request->metode_pembayaran,
            'status' => 'completed',
            'barcode_pembayaran' => $request->metode_pembayaran === 'non-tunai' ? uniqid('konf_') : null,
            'amount_paid' => $amountPaid,
            'change_amount' => $change,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dikonfirmasi dan pembayaran dicatat.',
            'pesanan' => $pesanan->load('pembayaran'),
            'change' => $change
        ]);
    }

    public function printReceipt($order_id)
    {
        // Eager load pesanan items, their menus, and their addons with addon details
        $pesanan = Pesanan::with([
            'items.menu',
            'user',
            'meja',
            'pemesanInfo',
            'pembayaran',
            'items.addons.addOn' // Eager load nested relationships
        ])->find($order_id);

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $mejaInfo = $pesanan->meja ? ($pesanan->meja->nama_meja ?? $pesanan->meja->kode_barcode) : 'Tidak Ada Meja';
        if ($mejaInfo === 'Tidak Ada Meja' && $pesanan->pemesanInfo && $pesanan->pemesanInfo->nama_pemesan) {
            $mejaInfo = $pesanan->pelanggan_nama;
        }

        // Ambil nama admin yang sedang login
        $adminName = auth()->user()->name ?? 'Kasir';

        $receiptData = [
            'order_id' => $pesanan->id,
            'order_token' => $pesanan->order_token,
            'timestamp' => Carbon::parse($pesanan->updated_at)->format('d F Y H:i:s'),
            'customer_name' => $pesanan->pelanggan_nama,
            'table_info' => $mejaInfo,
            'global_notes' => $pesanan->global_notes,
            'status' => $pesanan->status,
            'total_amount' => $pesanan->total_harga,
            'payment_method' => $pesanan->pembayaran->metode_pembayaran ?? 'Tunai',
            'amount_paid' => $pesanan->pembayaran->amount_paid ?? null,
            'change' => $pesanan->pembayaran->change_amount ?? null,
            'admin_name' => $adminName, // Tambahkan nama admin di sini
            'items' => $pesanan->items->map(function($item) {
                // Prioritaskan harga dari menu, jika tidak ada baru harga_per_item, jika tidak ada juga 0
                $price = $item->menu ? (float)$item->menu->harga : ((float)$item->harga_per_item ?? 0);
                $quantity = (int)$item->jumlah;
                $itemSubtotal = $quantity * $price; // Calculate subtotal for the base item

                $addons = $item->addons->map(function($addonItem) {
                    return [
                        'name' => $addonItem->addOn->nama,
                        'price' => (float)$addonItem->addOn->harga,
                    ];
                });

                // Add the price of addons to the item's subtotal
                foreach ($addons as $addon) {
                    $itemSubtotal += $addon['price'] * $quantity; // Multiply add-on price by item quantity
                }


                return [
                    'menu_name' => $item->menu ? $item->menu->nama : 'Unknown Item',
                    'quantity' => $quantity,
                    'price_per_item' => $price,
                    'subtotal' => $itemSubtotal, // This now includes add-on prices
                    'notes' => $item->catatan,
                    'addons' => $addons, // Include addon details
                ];
            }),
        ];

        return response()->json($receiptData);
    }
    // ADD-ON MANAGEMENT METHODS
    public function indexAddons()
    {
        return response()->json(AddOn::all()); //
    }

    public function showAddon($id)
    {
        return AddOn::findOrFail($id); //
    }

    public function storeAddon(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'harga' => 'required|numeric|min:0',
            'kategori' => 'nullable|string|max:255',
            'sub_kategori' => 'nullable|string|max:255',
            'image_path' => 'nullable|string', // Jika Anda menambahkan path gambar untuk add-on
        ]);

        $addon = AddOn::create($request->all()); //

        return response()->json(['message' => 'Add-on berhasil ditambahkan', 'data' => $addon], 201);
    }

    public function updateAddon(Request $request, $id)
    {
        $addon = AddOn::findOrFail($id); //

        $request->validate([
            'nama' => 'required|string|max:255',
            'harga' => 'required|numeric|min:0',
            'kategori' => 'nullable|string|max:255',
            'sub_kategori' => 'nullable|string|max:255',
            'image_path' => 'nullable|string',
        ]);

        $addon->update($request->all()); //

        return response()->json(['message' => 'Add-on berhasil diupdate', 'data' => $addon]);
    }

    public function destroyAddon($id)
    {
        $addon = AddOn::findOrFail($id); //
        $addon->delete(); //

        return response()->json(['message' => 'Add-on berhasil dihapus']);
    }
}