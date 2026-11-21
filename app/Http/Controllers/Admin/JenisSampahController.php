<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JenisSampahController extends Controller
{
    public function index()
    {
        // Ganti order by created_at dengan id
        $jenisSampah = JenisSampah::orderBy('id_jenis_sampah', 'desc')->paginate(10);
        return view('admin.bank-sampah.jenis-sampah.index', compact('jenisSampah'));
    }

    public function create()
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->role !== 'super_admin') {
            return redirect()->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk menambah jenis sampah!');
        }

        return view('admin.bank-sampah.jenis-sampah.create');
    }

    public function store(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->role !== 'super_admin') {
            return redirect()->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk menambah jenis sampah!');
        }

        $validated = $request->validate([
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
            'jenis' => 'required|string|max:100',
            'satuan' => 'required|in:Kg,Lt,Pcs,Pack,Lusin',
            'harga' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('gambar')) {

            $file = $request->file('gambar');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $destination = $this->uploadDir('jenis-sampah');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $fileName);

            $validated['gambar'] = 'jenis-sampah/' . $fileName;
        }

        JenisSampah::create($validated);

        return redirect()->route('admin.bank-sampah.jenis-sampah.index')
            ->with('success', 'Jenis sampah berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->role !== 'super_admin') {
            return redirect()->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk mengedit jenis sampah!');
        }

        $jenisSampah = JenisSampah::findOrFail($id);
        return view('admin.bank-sampah.jenis-sampah.edit', compact('jenisSampah'));
    }

    public function update(Request $request, $id)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->role !== 'super_admin') {
            return redirect()->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk mengupdate jenis sampah!');
        }

        $jenisSampah = JenisSampah::findOrFail($id);

        $validated = $request->validate([
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
            'jenis' => 'required|string|max:100',
            'satuan' => 'required|in:Kg,Lt,Pcs,Pack,Lusin',
            'harga' => 'required|numeric|min:0',
        ]);

        if ($request->input('remove_current_image') == '1') {

            if ($jenisSampah->gambar) {

                $oldFile = $this->uploadDir($jenisSampah->gambar);

                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $validated['gambar'] = null;
        }

        if ($request->hasFile('gambar')) {

            if ($jenisSampah->gambar) {

                $oldFile = $this->uploadDir($jenisSampah->gambar);

                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $file = $request->file('gambar');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $destination = $this->uploadDir('jenis-sampah');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $fileName);

            $validated['gambar'] = 'jenis-sampah/' . $fileName;
        }

        $jenisSampah->update($validated);

        return redirect()->route('admin.bank-sampah.jenis-sampah.index')
            ->with('success', 'Jenis sampah berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->role !== 'super_admin') {
            return redirect()->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk menghapus jenis sampah!');
        }

        $jenisSampah = JenisSampah::findOrFail($id);

        if ($jenisSampah->gambar) {

            $file = $this->uploadDir($jenisSampah->gambar);

            if (file_exists($file)) {
                unlink($file);
            }
        }

        $jenisSampah->delete();

        return redirect()->back()->with('success', 'Jenis sampah berhasil dihapus!');
    }
}