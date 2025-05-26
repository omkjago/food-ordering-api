<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use Illuminate\Http\Request;


class PembayaranController extends Controller
{
    private $apiKey = 'YOUR_TRIPAY_API_KEY';
    private $privateKey = 'YOUR_TRIPAY_PRIVATE_KEY';
    private $merchantCode = 'YOUR_TRIPAY_MERCHANT_CODE';

    // 1. Checkout Transaksi Tripay
    public function checkout(Request $request)
    {
        $request->validate([
            'order_token' => 'required',
            'method' => 'required', // ex: 'QRIS', 'OVO', 'BCA'
            'items' => 'required|array',
            'total' => 'required|numeric',
            'customer_name' => 'required',
            'customer_email' => 'required|email',
            'customer_phone' => 'required',
        ]);

        $data = [
            'method' => $request->method,
            'merchant_ref' => $request->order_token,
            'amount' => $request->total,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'order_items' => $request->items,
            'callback_url' => url('/api/pembayaran/callback'),
            'return_url' => url('/success.html'),
            'expired_time' => now()->addHours(2)->timestamp,
            'signature' => hash_hmac('sha256', $this->merchantCode . $request->order_token . $request->total, $this->privateKey)
        ];

        $response = Http::withToken($this->apiKey)
            ->post('https://tripay.co.id/api/transaction/create', $data);

        return response()->json($response->json(), $response->status());
    }

    // 2. Ambil Detail Transaksi
    public function detail($reference)
    {
        $response = Http::withToken($this->apiKey)
            ->get('https://tripay.co.id/api/transaction/detail', [
                'reference' => $reference
            ]);

        return response()->json($response->json(), $response->status());
    }

    // 3. Ambil Metode Pembayaran
    public function metode()
    {
        $response = Http::withToken($this->apiKey)
            ->get('https://tripay.co.id/api/payment/channel');

        return response()->json($response->json(), $response->status());
    }
}