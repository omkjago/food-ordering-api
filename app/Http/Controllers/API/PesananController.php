<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Menu;
use App\Models\Meja;
use App\Models\PemesanInfo;
use App\Models\AddOn; // Import model AddOn
use App\Models\PesananItemAddOn; // Import model PesananItemAddOn untuk pivot table
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PesananController extends Controller
{
    /**
     * Scan meja berdasarkan barcode.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanMeja(Request $request)
    {
        // Validasi input barcode
        $request->validate(['barcode' => 'required|string']);

        // Cari meja berdasarkan kode barcode
        $meja = Meja::where('kode_barcode', $request->barcode)->first();

        // Jika meja tidak ditemukan, kembalikan response 404
        if (! $meja) {
            return response()->json(['message' => 'Meja tidak ditemukan'], 404);
        }

        // Kembalikan data meja
        return response()->json($meja);
    }

    /**
     * Menyimpan atau memperbarui pesanan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validasi data yang masuk
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'meja_id' => 'required|exists:mejas,id',
                'global_notes' => 'nullable|string|max:500', // Validasi untuk catatan global
                'items'   => 'required|array',
                'items.*.menu_id' => 'required|exists:menus,id',
                'items.*.jumlah'  => 'required|integer|min:1',
                'items.*.catatan' => 'nullable|string|max:255', // Validasi catatan per item
                'items.*.addons'  => 'nullable|array', // Validasi add-ons per item
                'items.*.addons.*' => 'exists:add_ons,id', // Validasi ID add-on
            ]);
        } catch (ValidationException $e) {
            // Jika validasi gagal, kembalikan response error
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }

        $total = 0;
        $orderItemsData = []; // Untuk menyimpan data item sebelum membuat pesanan

        // Iterasi setiap item dalam pesanan untuk menghitung total dan mengumpulkan data
        foreach ($request->items as $item) {
            $menu = Menu::find($item['menu_id']);
            if (!$menu) {
                return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
            }

            $itemPrice = $menu->harga * $item['jumlah'];

            $selectedAddons = [];
            if (isset($item['addons']) && is_array($item['addons'])) {
                foreach ($item['addons'] as $addonId) {
                    $addon = AddOn::find($addonId);
                    if ($addon) {
                        // Harga add-on dikalikan jumlah menu utama
                        $itemPrice += $addon->harga * $item['jumlah']; 
                        $selectedAddons[] = $addon->id;
                    }
                }
            }
            $total += $itemPrice;

            $orderItemsData[] = [
                'menu_id' => $item['menu_id'],
                'jumlah' => $item['jumlah'],
                'catatan' => $item['catatan'] ?? null,
                'addons' => $selectedAddons,
            ];
        }

        // Cek apakah ada order_token yang sudah ada dari localStorage untuk pembaruan pesanan
        $orderToken = $request->input('order_token');
        $pesanan = null;

        if ($orderToken) {
            $pesanan = Pesanan::where('order_token', $orderToken)->first();
            // Jika pesanan ditemukan dan statusnya bukan 'pending', tolak pembaruan
            if ($pesanan && $pesanan->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan ini sudah dibayar atau diproses.',
                    'order_token' => $orderToken,
                    'status' => $pesanan->status
                ], 400);
            }
        }

        if ($pesanan) {
            // Perbarui pesanan yang sudah ada
            $pesanan->total_harga = $total;
            $pesanan->global_notes = $request->global_notes ?? null; // Perbarui catatan global
            $pesanan->save();

            // Hapus item yang sudah ada dan tambahkan kembali untuk menyederhanakan pembaruan
            $pesanan->items()->delete();
            foreach ($orderItemsData as $itemData) {
                $pesananItem = PesananItem::create([
                    'pesanan_id' => $pesanan->id,
                    'menu_id' => $itemData['menu_id'],
                    'jumlah' => $itemData['jumlah'],
                    'catatan' => $itemData['catatan'],
                ]);
                // Perbaiki: Gunakan create() untuk PesananItemAddOn
                if (!empty($itemData['addons'])) {
                    foreach ($itemData['addons'] as $addonId) {
                        PesananItemAddOn::create([
                            'pesanan_item_id' => $pesananItem->id,
                            'add_on_id' => $addonId,
                        ]);
                    }
                }
            }
        } else {
            // Buat pesanan baru
            $pesanan = Pesanan::create([
                'user_id' => $request->user_id ?? null,
                'meja_id' => $request->meja_id,
                'status' => 'pending',
                'total_harga' => $total,
                'order_token' => Str::uuid(),
                'global_notes' => $request->global_notes ?? null, // Simpan catatan global
            ]);

            // Tambahkan item pesanan dan add-on terkait
            foreach ($orderItemsData as $itemData) {
                $pesananItem = PesananItem::create([
                    'pesanan_id' => $pesanan->id,
                    'menu_id' => $itemData['menu_id'],
                    'jumlah' => $itemData['jumlah'],
                    'catatan' => $itemData['catatan'],
                ]);
                // Perbaiki: Gunakan create() untuk PesananItemAddOn
                if (!empty($itemData['addons'])) {
                    foreach ($itemData['addons'] as $addonId) {
                        PesananItemAddOn::create([
                            'pesanan_item_id' => $pesananItem->id,
                            'add_on_id' => $addonId,
                        ]);
                    }
                }
            }
        }

        // Kembalikan response sukses
        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat/diperbarui',
            'data' => [
                'pesanan' => $pesanan,
                'order_token' => $pesanan->order_token,
            ]
        ], 201);
    }

    /**
     * Memproses pembayaran pesanan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function bayar(Request $request, $id)
    {
        // Validasi metode pembayaran
        $request->validate(['metode' => 'required|in:tunai,non-tunai']);

        // Cari pesanan berdasarkan ID dan user ID yang terautentikasi
        $pesanan = Pesanan::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        // Jika status pesanan bukan 'pending', tolak pembayaran
        if ($pesanan->status !== 'pending') {
            return response()->json(['message' => 'Pesanan sudah dibayar atau diproses.'], 400);
        }

        // Perbarui status pesanan menjadi 'selesai'
        $pesanan->status = 'selesai';
        $pesanan->save();

        // Buat entri pembayaran baru
        $pembayaran = $pesanan->pembayaran()->create([
            'metode' => $request->metode,
            'status' => 'completed',
            'barcode_pembayaran' => $request->metode === 'non-tunai' ? uniqid('bayar_') : null,
        ]);

        // Kembalikan response pembayaran berhasil
        return response()->json(['message' => 'Pembayaran diproses', 'pembayaran' => $pembayaran]);
    }

    /**
     * Mengambil detail pesanan berdasarkan order_token.
     *
     * @param  string  $order_token
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByOrderToken($order_token)
    {
        // Perbaikan: Tambahkan 'items.addons.addOn' untuk memuat detail add-on
        $pesanan = Pesanan::with(['items.menu', 'meja', 'items.addons.addOn']) 
                            ->where('order_token', $order_token)
                            ->first(); // Tidak perlu latest() karena order_token unik

        // Jika pesanan tidak ditemukan, kembalikan response 404
        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan dengan token ini.'], 404);
        }

        // Kembalikan data pesanan
        return response()->json($pesanan);
    }

    /**
     * Konfirmasi pembayaran pesanan oleh kasir.
     * Metode ini harus dilindungi dengan middleware otentikasi kasir/admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        // Validasi input
        $request->validate([
            'order_id' => 'required|exists:pesanans,id',
            'metode_pembayaran' => 'required|in:tunai,non-tunai',
        ]);

        $pesanan = Pesanan::find($request->order_id);

        // Jika pesanan tidak ditemukan, kembalikan response 404
        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // Jika status pesanan bukan 'pending', tolak konfirmasi
        if ($pesanan->status !== 'pending') {
            return response()->json(['message' => 'Pesanan ini sudah dibayar atau diproses. Status: ' . $pesanan->status], 400);
        }

        // Perbarui status pesanan menjadi 'selesai'
        $pesanan->status = 'selesai';
        $pesanan->save();

        // Buat entri pembayaran baru
        $pembayaran = $pesanan->pembayaran()->create([
            'metode' => $request->metode_pembayaran,
            'status' => 'completed',
            'barcode_pembayaran' => $request->metode_pembayaran === 'non-tunai' ? uniqid('konf_') : null,
        ]);

        // Kembalikan response sukses
        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dikonfirmasi dan pembayaran dicatat.',
            'pesanan' => $pesanan->load('pembayaran')
        ]);
    }

    /**
     * Mengambil status pesanan berdasarkan order_token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus(Request $request)
    {
        // Validasi input order_token
        $request->validate([
            'order_token' => 'required|string',
        ]);

        $orderToken = $request->input('order_token');

        try {
            // Cari pesanan berdasarkan order_token
            $order = Pesanan::where('order_token', $orderToken)->first();

            // Jika pesanan tidak ditemukan, kembalikan response 404
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            // Kembalikan status pesanan
            return response()->json([
                'success' => true,
                'status' => $order->status // Asumsi 'status' adalah kolom di tabel pesanan Anda
            ]);

        } catch (\Exception $e) {
            // Log error dan kembalikan response error server
            Log::error("Error fetching order status: " . $e->getMessage(), ['order_token' => $orderToken]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Menangani pembayaran tunai, menyimpan info pelanggan, dan memperbarui status pesanan.
     * Metode ini dipanggil langsung oleh frontend untuk pembayaran tunai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCashPayment(Request $request)
    {
        // Validasi input pembayaran tunai
        $request->validate([
            'order_token' => 'required|string|exists:pesanans,order_token',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'required|email|max:255',
        ]);

        $orderToken = $request->order_token;
        $customerName = $request->customer_name;
        $customerPhone = $request->customer_phone;
        $customerEmail = $request->customer_email;

        $order = Pesanan::where('order_token', $orderToken)->first();

        // Jika pesanan tidak ditemukan, log error dan kembalikan response 404
        if (!$order) {
            Log::error('Order not found for cash payment', ['order_token' => $orderToken]);
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan.'], 404);
        }

        // Jika status pesanan bukan 'pending', log warning dan tolak pembayaran
        if ($order->status !== 'pending') {
            Log::warning('Cash payment attempted on non-pending order', ['order_token' => $orderToken, 'status' => $order->status]);
            return response()->json(['success' => false, 'message' => 'Pesanan ini sudah dibayar atau diproses.'], 400);
        }

        try {
            // Buat record PemesanInfo
            $pemesanInfo = PemesanInfo::create([
                'pesanan_id' => $order->id,
                'nama_pemesan' => $customerName,
                'email_pemesan' => $customerEmail,
                'no_hp' => $customerPhone,
            ]);

            // Perbarui pesanan dengan pemesan_info_id dan status
            $order->pemesan_info_id = $pemesanInfo->id;
            $order->status = 'pending'; // Status tetap pending, menunggu konfirmasi kasir
            // Cek jika email ada di tabel users dan tautkan user_id jika berlaku
            $user = \App\Models\User::where('email', $customerEmail)->first();
            if ($user) {
                $order->user_id = $user->id;
            }
            
            $order->save();

            // Kembalikan response sukses
            return response()->json(['success' => true, 'message' => 'Pembayaran tunai berhasil diproses dan informasi pelanggan disimpan.', 'order_token' => $order->order_token]);

        } catch (\Exception $e) {
            // Log error dan kembalikan response error server
            Log::error("Error processing cash payment for order {$orderToken}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal memproses pembayaran tunai karena kesalahan server internal.'], 500);
        }
    }
}
