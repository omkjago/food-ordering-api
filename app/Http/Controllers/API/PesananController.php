<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Menu;
use App\Models\Meja; // Masih dibutuhkan untuk relasi Pesanan
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PesananController extends Controller
{
    // ... (scanMeja dan store methods remain as you have them, not directly used by this specific cashier flow) ...

    public function scanMeja(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $meja = Meja::where('kode_barcode', $request->barcode)->first();

        if (! $meja) {
            return response()->json(['message' => 'Meja tidak ditemukan'], 404);
        }

        return response()->json($meja);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'meja_id' => 'required|exists:mejas,id',
                'items'   => 'required|array',
                'items.*.menu_id' => 'required|exists:menus,id',
                'items.*.jumlah'  => 'required|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }

        $total = 0;
        foreach ($request->items as $item) {
            $menu = Menu::find($item['menu_id']);
            $total += $menu->harga * $item['jumlah'];
        }

        $pesanan = Pesanan::create([
            'user_id' => $request->user_id ?? null,
            'meja_id' => $request->meja_id,
            'status' => 'pending',
            'total_harga' => $total,
            'order_token' => Str::uuid(),
        ]);

        foreach ($request->items as $item) {
            PesananItem::create([
                'pesanan_id' => $pesanan->id,
                'menu_id' => $item['menu_id'],
                'jumlah' => $item['jumlah'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat',
            'data' => [
                'pesanan' => $pesanan,
                'order_token' => $pesanan->order_token,
            ]
        ], 201);
    }

    public function bayar(Request $request, $id)
    {
        $request->validate(['metode' => 'required|in:tunai,non-tunai']);

        $pesanan = Pesanan::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        if ($pesanan->status !== 'pending') {
            return response()->json(['message' => 'Pesanan sudah dibayar atau diproses.'], 400);
        }

        $pesanan->status = 'selesai';
        $pesanan->save();

        $pembayaran = $pesanan->pembayaran()->create([
            'metode' => $request->metode,
            'status' => 'completed',
            'barcode_pembayaran' => $request->metode === 'non-tunai' ? uniqid('bayar_') : null,
        ]);

        return response()->json(['message' => 'Pembayaran diproses', 'pembayaran' => $pembayaran]);
    }

    /**
     * Mengambil detail pesanan berdasarkan order_token.
     * Ini adalah endpoint baru untuk kasus barcode berisi order_token.
     */
    public function getByOrderToken($order_token)
    {
        $pesanan = Pesanan::with(['items.menu', 'meja'])
                            ->where('order_token', $order_token)
                            ->first(); // Tidak perlu latest() karena order_token unik

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan dengan token ini.'], 404);
        }

        // Opsional: Anda mungkin hanya ingin menampilkan pesanan yang masih 'pending'
        // if ($pesanan->status !== 'pending') {
        //     return response()->json(['message' => 'Pesanan ini sudah dibayar atau diproses. Status: ' . $pesanan->status], 400);
        // }

        return response()->json($pesanan);
    }

    /**
     * Konfirmasi pembayaran pesanan oleh kasir.
     * Metode ini harus dilindungi dengan middleware otentikasi kasir/admin.
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:pesanans,id',
            'metode_pembayaran' => 'required|in:tunai,non-tunai',
        ]);

        $pesanan = Pesanan::find($request->order_id);

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($pesanan->status !== 'pending') {
            return response()->json(['message' => 'Pesanan ini sudah dibayar atau diproses. Status: ' . $pesanan->status], 400);
        }

        $pesanan->status = 'selesai';
        $pesanan->save();

        $pembayaran = $pesanan->pembayaran()->create([
            'metode' => $request->metode_pembayaran,
            'status' => 'completed',
            'barcode_pembayaran' => $request->metode_pembayaran === 'non-tunai' ? uniqid('konf_') : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dikonfirmasi dan pembayaran dicatat.',
            'pesanan' => $pesanan->load('pembayaran')
        ]);
    }
}