<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use Illuminate\Http\Request;

class ArtikelController extends Controller
{
    public function index()
    {
        $artikels = Artikel::orderBy('tanggal', 'desc')->get()->map(function ($item) {
            return [
                'id_artikel' => $item->id_artikel,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'tanggal' => $item->tanggal ? $item->tanggal->format('Y-m-d') : null,
                'foto' => $item->foto ? $this->getUrlFoto($item->foto) : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $artikels
        ]);
    }

    private function getUrlFoto($path)
    {
        return self::uploadUrl($path);
    }
}