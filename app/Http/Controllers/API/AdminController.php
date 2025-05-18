<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\Menu;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function aktif()
    {
        return response()->json(Pesanan::where('status', 'aktif')->with('items.menu')->get());
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

    $pesanan = Pesanan::where('order_token', $request->order_token)->first();

    if (!$pesanan) {
        return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
    }

    // Misalnya status 'aktif' = sukses divalidasi
    $pesanan->status = 'aktif';
    $pesanan->save();

    return response()->json(['message' => 'Pesanan berhasil divalidasi']);

}

    public function menu()
    {
        return response()->json(Menu::all());
    }

    public function storeMenu(Request $request)
{
    $request->validate([
        'nama' => 'required|string',
        'harga' => 'required|numeric',
        'diskripsi' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    // Menyimpan gambar (jika ada)
    $imagePath = null;
    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $fileName = 'menu_images/' . str_replace(' ', '_', strtolower($request->nama)) .'.' . $image->getClientOriginalExtension();
        $image->storeAs('public', $fileName);
        $imagePath = $fileName;
    }

    // Menyimpan menu baru
    $menu = Menu::create([
        'nama' => $request->nama,
        'harga' => $request->harga,
        'diskripsi' => $request->diskripsi,
        'image_path' => $imagePath,
    ]);

    return response()->json(['message' => 'Menu berhasil ditambahkan', 'data' => $menu]);
}



    public function updateMenu(Request $request, $id)
    {
        $menu = Menu::findOrFail($id);
        $menu->update($request->only(['nama', 'harga','diskripsi', 'image']));

        return response()->json(['message' => 'Menu diperbarui', 'data' => $menu]);
    }

    public function deleteMenu($id)
    {
        $menu = Menu::findOrFail($id);
        $menu->delete();

        return response()->json(['message' => 'Menu dihapus']);
    }
}
