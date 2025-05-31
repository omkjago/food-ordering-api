<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pesanan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $sales = DB::table('pesanans')
                    ->select(
                        'pesanans.id',
                        'pesanans.created_at',
                        'pesanans.total_harga',
                        'pesanans.meja_id',
                        DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, CONCAT("Meja ", pesanans.meja_id), "Tamu") as pelanggan_nama')
                    )
                    ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
                    ->leftJoin('pemesan_infos', 'pesanans.pemesan_info_id', '=', 'pemesan_infos.id')
                    ->whereBetween('pesanans.created_at', [$startDate, $endDate])
                    ->where('pesanans.status', 'selesai') // Hanya pesanan yang completed/selesai
                    ->orderBy('pesanans.created_at', 'asc')
                    ->get();

        // Re-hydrate to use relationships for 'items.menu'
        $salesCollection = Pesanan::hydrate($sales->toArray());
        $salesCollection->load('items.menu');

        // Hitung total penjualan dan jumlah transaksi
        $totalSales = $salesCollection->sum('total_harga');
        $totalTransactions = $salesCollection->count();

        // Format data untuk respons API
        $formattedSales = $salesCollection->map(function($sale) {
            return [
                'id' => $sale->id,
                'created_at' => $sale->created_at,
                'pelanggan_nama' => $sale->pelanggan_nama,
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
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'sales' => $formattedSales,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
        ]);
    }

    // New method to get pending sales data
    public function getPendingSales(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            return response()->json(['message' => 'Start date and end date are required.'], 400);
        }

        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format.'], 400);
        }

        // Ambil data pesanan yang masih pending (sesuaikan dengan status 'pending' di DB Anda)
        $pendingSales = DB::table('pesanans')
                    ->select(
                        'pesanans.id',
                        'pesanans.created_at',
                        'pesanans.total_harga',
                        'pesanans.meja_id',
                        DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, CONCAT("Meja ", pesanans.meja_id), "Tamu") as pelanggan_nama')
                    )
                    ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
                    ->leftJoin('pemesan_infos', 'pesanans.pemesan_info_id', '=', 'pemesan_infos.id')
                    ->whereBetween('pesanans.created_at', [$startDate, $endDate])
                    ->whereIn('pesanans.status', ['pending', 'preparing', 'waiting_payment']) // Sesuaikan status pending Anda
                    ->orderBy('pesanans.created_at', 'asc')
                    ->get();

        // Re-hydrate to use relationships for 'items.menu'
        $pendingSalesCollection = Pesanan::hydrate($pendingSales->toArray());
        $pendingSalesCollection->load('items.menu');

        $formattedPendingSales = $pendingSalesCollection->map(function($sale) {
            return [
                'id' => $sale->id,
                'created_at' => $sale->created_at,
                'pelanggan_nama' => $sale->pelanggan_nama,
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
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'sales' => $formattedPendingSales,
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
                            'pesanans.meja_id',
                            DB::raw('COALESCE(users.name, pemesan_infos.nama_pemesan, CONCAT("Meja ", pesanans.meja_id), "Tamu") as pelanggan_nama')
                        )
                        ->leftJoin('users', 'pesanans.user_id', '=', 'users.id')
                        ->leftJoin('pemesan_infos', 'pesanans.pemesan_info_id', '=', 'pemesan_infos.id')
                        ->whereBetween('pesanans.created_at', [$startDate, $endDate])
                        ->where('pesanans.status', 'selesai')
                        ->orderBy('pesanans.created_at', 'asc')
                        ->get();

        // Re-hydrate the collection to use relationships for 'items.menu'
        $salesCollection = Pesanan::hydrate($sales->toArray());
        $salesCollection->load('items.menu');

        $filename = "laporan_penjualan_{$reportType}_" . Carbon::now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($salesCollection) {
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
                    $sale->pelanggan_nama,
                    $orderItems,
                    $sale->total_harga,
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function getTopSellingItems($startDate, $endDate)
    {
        return DB::table('order_items')
            ->select('menus.nama as menu_name', DB::raw('SUM(order_items.jumlah) as total_quantity_sold'))
            ->join('pesanans', 'order_items.order_id', '=', 'pesanans.id')
            ->join('menus', 'order_items.menu_id', '=', 'menus.id')
            ->whereBetween('pesanans.created_at', [$startDate, $endDate])
            ->where('pesanans.status', 'selesai')
            ->groupBy('menus.nama')
            ->orderByDesc('total_quantity_sold')
            ->limit(5)
            ->get();
    }
}