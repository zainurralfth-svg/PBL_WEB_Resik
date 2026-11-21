<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArtikelController extends Controller
{
    /**
     * Tampilkan daftar artikel
     */
    public function index(Request $request)
    {
        $query = Artikel::query();

        // Search berdasarkan judul
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('judul', 'LIKE', "%{$search}%");
        }

        $artikelList = $query->orderBy('tanggal', 'desc')->get();

        return view('admin.artikel.index', compact('artikelList'));
    }

    /**
     * Tampilkan form tambah artikel
     */
    public function create()
    {
        return view('admin.artikel.form');
    }

    /**
     * Tampilkan form edit artikel
     */
    public function edit($id)
    {
        $artikel = Artikel::findOrFail($id);
        return view('admin.artikel.form', compact('artikel'));
    }

    /**
     * Simpan artikel baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|date',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ], [
            'judul.required' => 'Judul Artikel wajib diisi.',
            'deskripsi.required' => 'Deskripsi Artikel wajib diisi.',
            'tanggal.required' => 'Tanggal Publikasi wajib diisi.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau GIF.',
            'foto.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        // Handle upload foto
        $fotoPath = null;

        if ($request->hasFile('foto')) {

            $file = $request->file('foto');

            $fileName = 'artikel_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $destination = $this->uploadDir('artikel');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $fileName);

            $fotoPath = 'artikel/' . $fileName;
        }

        Artikel::create([
            'judul' => $validated['judul'],
            'deskripsi' => $validated['deskripsi'],
            'tanggal' => $validated['tanggal'],
            'foto' => $fotoPath,
        ]);

        return redirect()->route('admin.artikel.index')
            ->with('success', 'Artikel berhasil dipublikasikan!');
    }

    /**
     * Update artikel
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|date',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ], [
            'judul.required' => 'Judul Artikel wajib diisi.',
            'deskripsi.required' => 'Deskripsi Artikel wajib diisi.',
            'tanggal.required' => 'Tanggal Publikasi wajib diisi.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau GIF.',
            'foto.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        $artikel = Artikel::findOrFail($id);

        // Handle upload foto baru
        if ($request->hasFile('foto')) {

            $file = $request->file('foto');

            $fileName = 'artikel_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $destination = $this->uploadDir('artikel');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            // Hapus foto lama
            if ($artikel->foto) {

                $old = $destination . '/' . basename($artikel->foto);

                if (file_exists($old)) {
                    unlink($old);
                }
            }

            $file->move($destination, $fileName);

            $artikel->foto = 'artikel/' . $fileName;

            $artikel->save();
        }

        $artikel->update([
            'judul' => $validated['judul'],
            'deskripsi' => $validated['deskripsi'],
            'tanggal' => $validated['tanggal'],
        ]);

        return redirect()->route('admin.artikel.index')
            ->with('success', 'Artikel berhasil diperbarui!');
    }

    /**
     * Hapus artikel
     */
    public function destroy($id)
    {
        try {
            $artikel = Artikel::findOrFail($id);

            if ($artikel->foto) {

                $destination = $this->uploadDir('artikel');

                $old = $destination . '/' . basename($artikel->foto);

                if (file_exists($old)) {
                    unlink($old);
                }
            }

            $artikel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Artikel berhasil dihapus!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus artikel.'
            ], 500);
        }
    }
}