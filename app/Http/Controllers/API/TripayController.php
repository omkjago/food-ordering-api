<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Pesanan;
use App\Models\PemesanInfo;



class TripayController extends Controller
{
    public function checkout(Request $request)
{
    $orderToken = $request->order_token;
    $customerName = $request->customer_name;
    $customerPhone = $request->customer_phone;
    $customerEmail = $request->customer_email;
    


    // Validasi jika data customer kosong
    if (!$customerName || !$customerPhone || !$customerEmail) {
        return response()->json(['success' => false, 'message' => 'Nama ,Email, dan nomor telepon harus diisi.'], 400);
    }

    $order = Pesanan::where('order_token', $orderToken)->first();
    if (!$order) {
        return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
    }


    if (auth()->check()) {
        $order->user_id = auth()->id(); // ubah dari $pesanan ke $order
    } else {
        $pemesanInfo = PemesanInfo::create([
            'pesanan_id'     => $order->id,
            'nama_pemesan'   => $customerName,
            'email_pemesan'  => $customerEmail,
            'no_hp'          => $customerPhone,
        ]);
    
        $order->pemesan_info_id = $pemesanInfo->id; // ubah dari $pesanan ke $order
    }
    
    $order->save();

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
    'signature'      => $signature,
]);


if ($response->successful()) {
    return response()->json([
        'success' => true,
        'payment_url' => $response['data']['checkout_url'],
    ]);
} else {
    return response()->json([
        'success' => false,
        'message' => 'Gagal membuat pembayaran.',
        'tripay_error' => $response->json(), // Tambahkan detail error Tripay
    ]);
}

}



    public function handleCallback(Request $request)
    {
        $callbackSignature = $request->server('HTTP_X_CALLBACK_SIGNATURE');
    $json = $request->getContent();

    // Verifikasi signature
    $privateKey = env('TRIPAY_PRIVATE_KEY');
    $validSignature = hash_hmac('sha256', $json, $privateKey);

    if ($callbackSignature !== $validSignature) {
        return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
    }

    $data = json_decode($json, true);

    // Ambil order berdasarkan merchant_ref
    $orderToken = $request->merchant_ref;  // Karena kita sebelumnya mengisi merchant_ref dengan order_token

    $order = Pesanan::where('order_token', $orderToken)->first();
    if (!$order) {
        return response()->json(['success' => false, 'message' => 'Order not found'], 404);
    }

    // Jika pembayaran sukses, update status jadi aktif
    if ($request->status === 'PAID') {
        $order->status = 'aktif';
        
        // Cek jika email pemesan terdaftar di users
        if (!empty($order->email_pemesan)) {
            $user = \App\Models\User::where('email', $order->email_pemesan)->first();
            if ($user) {
                $order->user_id = $user->id;
            }
        }
        
        $order->save();
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
}
