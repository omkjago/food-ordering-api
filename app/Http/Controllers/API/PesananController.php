<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Menu;
use App\Models\Meja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PesananController extends Controller
{
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
                'order_token' => 'nullable|string',
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
            'order_token' => $pesanan->order_token,  // Kembalikan order_token
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

        $pesanan->status = 'aktif';
        $pesanan->save();

        $pembayaran = $pesanan->pembayaran()->create([
            'metode' => $request->metode,
            'status' => 'pending',
            'barcode_pembayaran' => $request->metode === 'non-tunai' ? uniqid('bayar_') : null,
        ]);

        return response()->json(['message' => 'Pembayaran diproses', 'pembayaran' => $pembayaran]);
    }
    // PesananController.php
public function getByBarcode($kode_barcode)
{
    $meja = Meja::where('kode_barcode', $kode_barcode)->first();
    if (!$meja) {
        return response()->json(['message' => 'Meja tidak ditemukan'], 404);
    }

    $pesanan = Pesanan::with('items.menu')->where('meja_id', $meja->id)->latest()->first();
    if (!$pesanan) {
        return response()->json(['message' => 'Belum ada pesanan'], 404);
    }

    return response()->json($pesanan);
}

}
