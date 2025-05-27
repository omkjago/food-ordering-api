<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pesanan; // Still useful for model-based logic, though we'll use DB::table for the joins
use Carbon\Carbon; // Untuk memudahkan manipulasi tanggal
use Illuminate\Support\Facades\DB; // Untuk agregasi data
use Illuminate\Support\Str; // Untuk sanitasi nama file

class SalesReportController extends Controller
{
    public function generateReport(Request $request)
    {
        // Pastikan pengguna sudah terautentikasi dan memiliki peran 'admin'
        // Ini adalah contoh, Anda harus menerapkan middleware autentikasi/otorisasi yang sesuai (misal: Sanctum, Spatie Permission)
        // if (!auth()->check() || auth()->user()->role !== 'admin') {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Validasi tanggal
        if (!$startDate || !$endDate) {
            return response()->json(['message' => 'Start date and end date are required.'], 400);
        }

        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format.'], 400);
        }

        // Ambil data pesanan yang sudah selesai (atau status lain yang Anda anggap sebagai 'terjual')
        // Menggunakan DB::table untuk LEFT JOIN dan COALESCE
        $sales = DB::table('pesanans')
                    ->select(
                        'pesanans.id',
                        'pesanans.created_at',
                        'pesanans.total_harga',
                        'pesanans.meja_id', // Keep meja_id for fallback or direct use
                        DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, CONCAT("Meja ", pesanans.meja_id), "Tamu") as pelanggan_nama')
                    )
                    ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
                    // Assuming pemesan_infos has a direct link to pesanan_id
                    ->leftJoin('pemesan_infos', 'pesanans.pemesan_info_id', '=', 'pemesan_infos.id') // Assuming pemesan_info_id foreign key on pesanans table
                    ->whereBetween('pesanans.created_at', [$startDate, $endDate])
                    ->where('pesanans.status', 'selesai') // Hanya pesanan yang completed/selesai
                    ->orderBy('pesanans.created_at', 'asc')
                    ->get();

        // Untuk mendapatkan item, kita perlu memuatnya secara terpisah atau bergabung dengan cara berbeda.
        // Jika Anda ingin detail item per pesanan, cara terbaik adalah tetap dengan Eager Loading,
        // atau melakukan loop terpisah untuk setiap pesanan.
        // Untuk kesederhanaan respons API ini, saya akan memuat item secara terpisah jika diperlukan.
        // Jika Anda perlu item detail di sini, Anda harus menggunakan model Pesanan dengan with(['items.menu'])
        // dan kemudian memproses customer_name seperti di atas secara manual di map().

        // Untuk saat ini, kita akan mendapatkan data item untuk setiap pesanan.
        // Ini mungkin kurang efisien jika ada banyak pesanan,
        // namun konsisten dengan kebutuhan data 'items' di frontend.
        $salesCollection = Pesanan::hydrate($sales->toArray()); // Re-hydrate to use relationships
        $salesCollection->load('items.menu'); // Eager load items and menus for the re-hydrated collection


        // Hitung total penjualan dan jumlah transaksi
        $totalSales = $salesCollection->sum('total_harga');
        $totalTransactions = $salesCollection->count();

        // Format data untuk respons API
        $formattedSales = $salesCollection->map(function($sale) {
            // pelanggan_nama sudah diformat oleh query DB, langsung gunakan
            return [
                'id' => $sale->id,
                'created_at' => $sale->created_at,
                'pelanggan_nama' => $sale->pelanggan_nama, // Langsung pakai hasil COALESCE dari DB
                'meja_id' => $sale->meja_id,
                'total_harga' => $sale->total_harga,
                'items' => $sale->items->map(function($item) {
                    return [
                        'menu_id' => $item->menu_id,
                        'jumlah' => $item->jumlah,
                        'harga_per_item' => $item->harga_per_item,
                        'menu' => $item->menu ? [
                            'nama' => $item->menu->nama,
                            'kategori' => $item->menu->kategori,
                        ] : null, // Pastikan menu ada
                    ];
                }),
            ];
        });

        return response()->json([
            'sales' => $formattedSales,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            // Anda bisa menambahkan data analitik lainnya di sini, contoh:
            // 'top_selling_items' => $this->getTopSellingItems($startDate, $endDate),
        ]);
    }

    public function downloadReport(Request $request)
    {
        // Pastikan pengguna sudah terautentikasi dan memiliki peran 'admin'
        // if (!auth()->check() || auth()->user()->role !== 'admin') {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $reportType = $request->input('type', 'daily');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Jika tanggal tidak disediakan untuk custom, gunakan default daily
        if ($reportType !== 'custom' && (!$startDate || !$endDate)) {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        } else if ($reportType === 'custom' && ($startDate && $endDate)) {
             try {
                $startDate = Carbon::parse($startDate)->startOfDay();
                $endDate = Carbon::parse($endDate)->endOfDay();
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid date format.'], 400);
            }
        } else {
             return response()->json(['message' => 'Start date and end date are required for custom report.'], 400);
        }

        // Ambil data pesanan menggunakan DB::table untuk join dan COALESCE
        $sales = DB::table('pesanans')
                        ->select(
                            'pesanans.id',
                            'pesanans.created_at',
                            'pesanans.total_harga',
                            'pesanans.meja_id', // Keep meja_id for fallback or direct use
                            DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, CONCAT("Meja ", pesanans.meja_id), "Tamu") as pelanggan_nama')
                        )
                        ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
                        ->leftJoin('pemesan_infos', 'pesanans.pemesan_info_id', '=', 'pemesan_infos.id') // Assuming pemesan_info_id foreign key on pesanans table
                        ->whereBetween('pesanans.created_at', [$startDate, $endDate])
                        ->where('pesanans.status', 'selesai')
                        ->orderBy('pesanans.created_at', 'asc')
                        ->get();

        // Re-hydrate the collection to use relationships for 'items.menu'
        $salesCollection = Pesanan::hydrate($sales->toArray());
        $salesCollection->load('items.menu'); // Eager load items and menus for the re-hydrated collection

        $filename = "laporan_penjualan_{$reportType}_" . Carbon::now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($salesCollection) { // Use $salesCollection here
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Tanggal', 'ID Pesanan', 'Customer/Meja', 'Item (Jumlah)', 'Total Harga']); // CSV Headers

            foreach ($salesCollection as $sale) {
                $orderItems = $sale->items->map(function($item) {
                    $itemName = $item->menu ? $item->menu->nama : 'Unknown Item';
                    return "{$itemName} ({$item->jumlah})";
                })->join('; ');

                $row = [
                    Carbon::parse($sale->created_at)->format('Y-m-d H:i:s'),
                    '#' . $sale->id,
                    $sale->pelanggan_nama, // Directly use the 'pelanggan_nama' from the DB result
                    $orderItems,
                    $sale->total_harga,
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Contoh fungsi untuk mendapatkan item terlaris (bisa diintegrasikan ke generateReport)
    protected function getTopSellingItems($startDate, $endDate)
    {
        return DB::table('order_items')
            ->select('menus.nama as menu_name', DB::raw('SUM(order_items.jumlah) as total_quantity_sold'))
            ->join('pesanans', 'order_items.order_id', '=', 'pesanans.id') // Changed 'orders' to 'pesanans'
            ->join('menus', 'order_items.menu_id', '=', 'menus.id')
            ->whereBetween('pesanans.created_at', [$startDate, $endDate])
            ->where('pesanans.status', 'selesai')
            ->groupBy('menus.nama')
            ->orderByDesc('total_quantity_sold')
            ->limit(5)
            ->get();
    }
}