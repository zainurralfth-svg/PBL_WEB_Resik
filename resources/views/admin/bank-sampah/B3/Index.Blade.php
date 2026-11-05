{{--
    B3: Penjemputan Berperiode - KERANGKA BERSAMA semua tab.
    Isi tiap tab ada di Tabs/{Periode,Pendaftaran,Verifikasi,Rekap}.blade.php
    Variabel $tab dikirim oleh controller/route: periode | pendaftaran | verifikasi | rekap
--}}
@extends('layouts.admin')

@section('title', 'Bank Sampah - B3')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/b3.css?v=' . time()) }}">
    <link rel="stylesheet" href="{{ asset('css/b3-pendaftaran.css?v=' . time()) }}">
@endpush

@section('content')
    @php
        $tab = $tab ?? 'pendaftaran';
        $href = fn (string $name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';

        $adminB3 = auth()->guard('admin')->user();
        $desaB3 = ($adminB3 && method_exists($adminB3, 'isSubAdminDesa') && $adminB3->isSubAdminDesa() && $adminB3->id_desa)
            ? $adminB3->id_desa : null;
        $jumlahBaru = \App\Models\B3Pendaftaran::jumlahBaru($desaB3);

        $daftarTab = [
            'periode'     => ['Periode',     'admin.bank-sampah.b3.periode.index'],
            'pendaftaran' => ['Pendaftaran', 'admin.bank-sampah.b3.pendaftaran.index'],
            'verifikasi'  => ['Verifikasi',  'admin.bank-sampah.b3.verifikasi.index'],
            'rekap'       => ['Rekap',       'admin.bank-sampah.b3.rekap.index'],
        ];

        // Tab milik orang lain yang filenya masih kosong -> tampilkan penanda
        $berkasTab = resource_path('views/admin/bank-sampah/B3/Tabs/' . ucfirst($tab) . '.blade.php');
        $tabKosong = $tab !== 'pendaftaran' && (! is_file($berkasTab) || filesize($berkasTab) === 0);
    @endphp

    <div class="b3-top">
        <form class="b3-search" method="GET" action="{{ url()->current() }}" role="search">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari data berdasarkan nama atau lokasi" aria-label="Cari data">
            <button type="submit" aria-label="Cari"><img src="{{ asset('assets/icons/search.png') }}" alt=""></button>
        </form>
    </div>

    <div class="b3-card">
        <h1 class="b3-title">B3: Penjemputan Berperiode</h1>

        <nav class="b3-tabs" aria-label="Menu B3">
            @foreach ($daftarTab as $kunci => [$label, $rute])
                <a href="{{ $href($rute) }}" class="b3-tab {{ $tab === $kunci ? 'b3-tab--aktif' : '' }}"
                    @if ($tab === $kunci) aria-current="page" @endif>
                    {{ $label }}
                    @if ($kunci === 'pendaftaran' && $jumlahBaru > 0)
                        <span class="b3-badge" aria-label="{{ $jumlahBaru }} pendaftaran baru">{{ $jumlahBaru }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @if (session('success'))
            <div class="b3-alert b3-alert--ok" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="b3-alert b3-alert--err" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="b3-alert b3-alert--err" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($tabKosong)
            <p style="margin-top:28px;color:#555">Tab <strong>{{ ucfirst($tab) }}</strong> sedang dikerjakan.</p>
        @else
            @include('admin.bank-sampah.B3.Tabs.' . ucfirst($tab))
        @endif
    </div>
@endsection