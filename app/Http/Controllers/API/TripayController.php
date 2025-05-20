<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Pesanan;
use App\Models\PemesanInfo;
use App\Models\Meja;

class TripayController extends Controller
{
    public function checkout(Request $request)
    {
        Log::info('Checkout request received', $request->all());
        
        $orderToken = $request->order_token;
        $customerName = $request->customer_name;
        $customerPhone = $request->customer_phone;
        $customerEmail = $request->customer_email;
        
        // Validasi jika data customer kosong
        if (!$customerName || !$customerPhone || !$customerEmail) {
            return response()->json(['success' => false, 'message' => 'Nama, Email, dan nomor telepon harus diisi.'], 400);
        }

        $order = Pesanan::where('order_token', $orderToken)->first();
        if (!$order) {
            Log::error('Order not found', ['order_token' => $orderToken]);
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
        }

        // Simpan informasi customer ke cache untuk digunakan nanti di callback
        Cache::put('customer_data_' . $orderToken, [
            'name' => $customerName,
            'email' => $customerEmail,
            'phone' => $customerPhone
        ], now()->addHours(24));

        Log::info('Customer data saved to cache', [
            'order_token' => $orderToken,
            'customer_name' => $customerName
        ]);

        // Data signature
        $merchantCode = env('TRIPAY_MERCHANT_CODE');
        $merchantRef  = $order->order_token;
        $amount = (int) $order->total_harga;
        $privateKey   = env('TRIPAY_PRIVATE_KEY');
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);
        
        // Format order_items
        $orderItems = [[
            'name'     => 'Pembayaran Pesanan ' . $order->order_token,
            'price'    => (int) $order->total_harga,
            'quantity' => 1
        ]];
        
        // Tambahkan return URL untuk redirect setelah pembayaran selesai
        $returnUrl = route('tripay.return', ['order_token' => $orderToken]);
        
        Log::info('Sending request to Tripay', [
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'return_url' => $returnUrl
        ]);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TRIPAY_API_KEY'),
        ])->post('https://tripay.co.id/api-sandbox/transaction/create', [
            'method'         => 'QRIS',
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'order_items'    => $orderItems,
            'callback_url'   => route('tripay.callback'),
            'return_url'     => $returnUrl, // URL untuk redirect setelah pembayaran selesai
            'signature'      => $signature,
        ]);

        if ($response->successful()) {
            Log::info('Tripay response successful', [
                'payment_url' => $response['data']['checkout_url']
            ]);
            
            return response()->json([
                'success' => true,
                'payment_url' => $response['data']['checkout_url'],
            ]);
        } else {
            Log::error('Failed to create payment', [
                'order_token' => $orderToken,
                'tripay_response' => $response->json()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran.',
                'tripay_error' => $response->json(),
            ]);
        }
    }

    public function handleCallback(Request $request)
    {
        Log::info('Callback received', ['payload' => $request->all()]);
        
        $callbackSignature = $request->server('HTTP_X_CALLBACK_SIGNATURE');
        $json = $request->getContent();

        // Verifikasi signature
        $privateKey = env('TRIPAY_PRIVATE_KEY');
        $validSignature = hash_hmac('sha256', $json, $privateKey);

        if ($callbackSignature !== $validSignature) {
            Log::error('Invalid signature', [
                'callback_signature' => $callbackSignature,
                'valid_signature' => $validSignature
            ]);
            
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $data = json_decode($json, true);

        // Ambil order berdasarkan merchant_ref dari data yang diterima
        $orderToken = $data['merchant_ref'] ?? '';
        
        Log::info('Processing payment callback', [
            'order_token' => $orderToken,
            'status' => $data['status'] ?? 'unknown'
        ]);

        $order = Pesanan::where('order_token', $orderToken)->first();
        if (!$order) {
            Log::error('Order not found in callback', ['order_token' => $orderToken]);
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Jika pembayaran sukses, update status jadi aktif dan simpan pemesan_info
        if (($data['status'] ?? '') === 'PAID') {
            $order->status = 'aktif';
            
            // Ambil data pelanggan dari cache
            $customerData = Cache::get('customer_data_' . $orderToken);
            
            if ($customerData) {
                Log::info('Customer data found in cache', [
                    'order_token' => $orderToken,
                    'customer_name' => $customerData['name']
                ]);
                
                // Simpan informasi pemesan di PemesanInfo
                $pemesanInfo = PemesanInfo::create([
                    'pesanan_id'     => $order->id,
                    'nama_pemesan'   => $customerData['name'],
                    'email_pemesan'  => $customerData['email'],
                    'no_hp'          => $customerData['phone'],
                ]);
                
                $order->pemesan_info_id = $pemesanInfo->id;
                
                // Cek jika email pemesan terdaftar di users
                if (!empty($customerData['email'])) {
                    $user = \App\Models\User::where('email', $customerData['email'])->first();
                    if ($user) {
                        $order->user_id = $user->id;
                        Log::info('User found and associated with order', ['user_id' => $user->id]);
                    }
                }
                
                // Hapus dari cache setelah digunakan
                Cache::forget('customer_data_' . $orderToken);
            } else {
                Log::warning('Customer data not found in cache', ['order_token' => $orderToken]);
            }
            
            $order->save();
            
            Log::info('Order status updated to active', [
                'order_id' => $order->id,
                'order_token' => $orderToken
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Callback handled successfully']);
    }

    public function orderSummary(Request $request)
    {
        $request->validate([
            'order_token' => 'required|string'
        ]);

        $pesanan = Pesanan::with(['items.menu'])->where('order_token', $request->order_token)->first();

        if (!$pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $summary = $pesanan->items->map(function ($item) {
            return [
                'nama_menu' => $item->menu->nama ?? 'Menu tidak ditemukan',
                'harga'     => $item->menu->harga ?? 0,
                'jumlah'    => $item->jumlah,
                'subtotal'  => ($item->menu->harga ?? 0) * $item->jumlah,
                'image_path' => $item->menu->image_path ?? ''  // Menambahkan path gambar
            ];
        });

        $total = $summary->sum('subtotal');

        return response()->json([
            'order_token' => $pesanan->order_token,
            'items'       => $summary,
            'total'       => $total,
        ]);
    }

    // Metode baru untuk menerima redirect dari Tripay dan mengarahkan ke halaman meja
    public function paymentReturn(Request $request)
{
    $orderToken = $request->input('order_token');
    
    Log::info('Payment return received', ['order_token' => $orderToken]);
    
    $order = Pesanan::where('order_token', $orderToken)->first();
    if (!$order) {
        Log::error('Order not found in payment return', ['order_token' => $orderToken]);
        return redirect()->route('home')->with('error', 'Pesanan tidak ditemukan');
    }
    
    // Ambil ID meja
    $tableId = $order->meja_id;
    
    // Ambil kode barcode dari tabel Meja berdasarkan ID meja
    $table = Meja::find($tableId); // Asumsikan model Meja
    
    if (!$table) {
        Log::error('Table not found', ['table_id' => $tableId]);
        return redirect()->route('home')->with('error', 'Meja tidak ditemukan');
    }
    
    $barcodeCode = $table->kode_barcode; // Asumsikan kolom kode_barcode
    
    Log::info('Redirecting to table page', [
        'order_token' => $orderToken,
        'table_id' => $tableId,
        'barcode_code' => $barcodeCode
    ]);
    
    // Redirect dengan format yang benar (?= bukan ?id=)
    return redirect("/ui/menu.html?=" . $barcodeCode);
}
}