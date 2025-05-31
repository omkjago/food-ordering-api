<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Menu;
use App\Models\Meja;
use App\Models\PemesanInfo; // Make sure to import PemesanInfo
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // Import Log facade

class PesananController extends Controller
{
    // ... (scanMeja, store, bayar methods remain as you have them) ...

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

    public function getStatus(Request $request)
    {
        $request->validate([
            'order_token' => 'required|string',
        ]);

        $orderToken = $request->input('order_token');

        try {
            // Find the order by order_token
            $order = Pesanan::where('order_token', $orderToken)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            // Return the order status
            return response()->json([
                'success' => true,
                'status' => $order->status // Assuming 'status' is a column in your orders table
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching order status: " . $e->getMessage(), ['order_token' => $orderToken]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Handle cash payment, save customer info, and update order status.
     * This method is called directly by the frontend for cash payments.
     */
    public function processCashPayment(Request $request)
    {

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

        if (!$order) {
            Log::error('Order not found for cash payment', ['order_token' => $orderToken]);
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan.'], 404);
        }

        if ($order->status !== 'pending') {
            Log::warning('Cash payment attempted on non-pending order', ['order_token' => $orderToken, 'status' => $order->status]);
            return response()->json(['success' => false, 'message' => 'Pesanan ini sudah dibayar atau diproses.'], 400);
        }

        try {
            // Create PemesanInfo record
            $pemesanInfo = PemesanInfo::create([
                'pesanan_id' => $order->id,
                'nama_pemesan' => $customerName,
                'email_pemesan' => $customerEmail,
                'no_hp' => $customerPhone,
            ]);

            // Update order with pemesan_info_id and status
            $order->pemesan_info_id = $pemesanInfo->id;
            $order->status = 'pending'; // Mark as completed for cash payments

            // Check if email exists in users table and link user_id if applicable
            $user = \App\Models\User::where('email', $customerEmail)->first();
            if ($user) {
                $order->user_id = $user->id;
            }
            
            $order->save();

            return response()->json(['success' => true, 'message' => 'Pembayaran tunai berhasil diproses dan informasi pelanggan disimpan.', 'order_token' => $order->order_token]);

        } catch (\Exception $e) {
            Log::error("Error processing cash payment for order {$orderToken}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal memproses pembayaran tunai karena kesalahan server internal.'], 500);
        }
    }
}