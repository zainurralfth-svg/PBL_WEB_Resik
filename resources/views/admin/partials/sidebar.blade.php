@php
    // ✅ CEK ROLE ADMIN
    $admin = auth()->guard('admin')->user();
    $isSubAdmin = $admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa();
@endphp

<aside class="sidebar" id="sidebar">
    <!-- Fungsi: Header sidebar berisi logo dan tombol close untuk mobile -->
    <div class="sidebar-header">
        <!-- Fungsi: Menampilkan logo aplikasi RESIK -->
        <img src="{{ asset('assets/logo-resik.png') }}" alt="RESIK Logo" class="logo">
        <!-- Fungsi: Tombol close sidebar untuk tampilan mobile dengan accessibility label -->
        <button class="sidebar-close" aria-label="Tutup Menu" onclick="toggleSidebar()">&times;</button>
    </div>

    <!-- Fungsi: Container navigasi menu sidebar -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            
            {{-- ✅ BERANDA - UNTUK SEMUA ROLE --}}
            <li class="nav-item {{ request()->routeIs('admin.dashboard') || request()->routeIs('admin.sub-admin.dashboard') ? 'active' : '' }}">
                <a href="{{ $isSubAdmin ? route('admin.sub-admin.dashboard') : route('admin.dashboard') }}">
                    <img src="{{ asset('assets/icons/beranda.png') }}" alt="Beranda" class="custom-icon">
                    <span>{{ $isSubAdmin ? 'Beranda' : 'Beranda' }}</span>
                </a>
            </li>

            {{-- ✅ MENU HANYA UNTUK SUPER ADMIN --}}
            @if(!$isSubAdmin)
                <!-- Fungsi: Menu Laporan Sampah Ilegal dengan wildcard route matching -->
                <li class="nav-item {{ request()->routeIs('admin.laporan*') ? 'active' : '' }}">
                    <a href="{{ route('admin.laporan.index') }}">
                        <img src="{{ asset('assets/icons/laporan_sampah.png') }}" alt="Laporan" class="custom-icon">
                        <span>Laporan Sampah Ilegal</span>
                    </a>
                    {{-- ✅ Badge DIPINDAH ke DALAM <li> --}}
                    <span class="notif-badge-wrapper" onclick="openNotifModal('laporan')" title="Lihat notifikasi">
                        <span id="badge-laporan" class="notif-badge" style="display:none;">0</span>
                    </span>
                </li>
            @endif

            {{-- ✅ BANK SAMPAH - UNTUK SEMUA ROLE --}}
            <li class="nav-item has-dropdown {{ request()->routeIs('admin.bank-sampah*') ? 'active open' : '' }}">
                <a href="javascript:void(0)" class="dropdown-toggle" aria-expanded="false"
                    onclick="toggleDropdown(this)">
                    <div class="nav-link-text">
                        <img src="{{ asset('assets/icons/bank_sampah.png') }}" alt="Bank-Sampah" class="custom-icon">
                        <span>Bank Sampah</span>
                    </div>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <ul class="sub-menu">
                    <li>
                        <a href="{{ route('admin.bank-sampah.setor.index') }}"
                            class="{{ request()->routeIs('admin.bank-sampah.setor.index') ? 'active' : '' }}">
                            Data Setor
                        </a>
                    </li>
                    {{-- Sub admin desa hanya melihat Data Setor dan B3 --}}
                    @if(!$isSubAdmin)
                        <li>
                            <a href="{{ route('admin.bank-sampah.penarikan.index') }}"
                                class="{{ request()->routeIs('admin.bank-sampah.penarikan.index') ? 'active' : '' }}">
                                Data Penarikan
                            </a>
                            <span class="notif-badge-wrapper" onclick="openNotifModal('penarikan')" title="Lihat notifikasi">
                                <span id="badge-penarikan" class="notif-badge" style="display:none;">0</span>
                            </span>
                        </li>
                        <li>
                            <a href="{{ route('admin.bank-sampah.jenis-sampah.index') }}"
                                class="{{ request()->routeIs('admin.bank-sampah.jenis-sampah.index') ? 'active' : '' }}">
                                Jenis & Harga Sampah
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.bank-sampah.penjemputan.index') }}"
                                class="{{ request()->routeIs('admin.bank-sampah.penjemputan*') ? 'active' : '' }}">
                                Penjemputan
                            </a>
                            <span class="notif-badge-wrapper"
                                onclick="openNotifModal('penjemputan'); event.stopPropagation(); return false;"
                                title="Lihat notifikasi">
                                <span id="badge-penjemputan" class="notif-badge" style="display:none;">0</span>
                            </span>
                        </li>
                    @endif
                    <li>
                        <a href="{{ route('admin.bank-sampah.b3.pendaftaran.index') }}"
                            class="{{ request()->routeIs('admin.bank-sampah.b3*') ? 'active' : '' }}">
                            B3
                        </a>
                    </li>
                </ul>
            </li>

            {{-- ✅ MENU HANYA UNTUK SUPER ADMIN --}}
            @if(!$isSubAdmin)
                <!-- Fungsi: Menu Artikel Edukasi dengan wildcard route matching -->
                <li class="nav-item {{ request()->routeIs('admin.artikel*') ? 'active' : '' }}">
                    <a href="{{ route('admin.artikel.index') }}">
                        <img src="{{ asset('assets/icons/artikel.png') }}" alt="Artikel" class="custom-icon">
                        <span>Artikel Edukasi</span>
                    </a>
                </li>

                <!-- Fungsi: Menu Informasi TPS dengan wildcard route matching -->
                <li class="nav-item {{ request()->routeIs('admin.tps*') ? 'active' : '' }}">
                    <a href="{{ route('admin.tps.index') }}">
                        <img src="{{ asset('assets/icons/tps2.png') }}" alt="TPS" class="custom-icon">
                        <span>Informasi TPS</span>
                    </a>
                </li>

                <!-- Fungsi: Menu Data Pengguna dengan wildcard route matching -->
                <li class="nav-item {{ request()->routeIs('admin.data-pengguna*') ? 'active' : '' }}">
                    <a href="{{ route('admin.data-pengguna.index') }}">
                        <img src="{{ asset('assets/icons/data_pengguna.png') }}" alt="Data-Pengguna" class="custom-icon">
                        <span>Data Pengguna</span>
                    </a>
                </li>

                {{-- Fungsi: Menu dropdown Kelola Akun dengan toggle expand/collapse --}}
                <li class="nav-item has-dropdown {{ request()->routeIs('admin.akun*') || request()->routeIs('admin.sub-admin*') ? 'active open' : '' }}">
                    <a href="javascript:void(0)" class="dropdown-toggle" aria-expanded="false"
                        onclick="toggleDropdown(this)">
                        <div class="nav-link-text">
                            <img src="{{ asset('assets/icons/kelola_akun.png') }}" alt="Kelola-Akun" class="custom-icon">
                            <span>Kelola Akun</span>
                        </div>
                        <i class="fas fa-chevron-down arrow"></i>
                    </a>
                    <ul class="sub-menu">
                        <li>
                            <a href="{{ route('admin.akun.index') }}"
                                class="{{ request()->routeIs('admin.akun.index') ? 'active' : '' }}">
                                Super Admin & Petugas
                            </a>
                        </li>
                        {{-- ✅ SUB ADMIN DESA - Link aktif (bukan coming soon) --}}
                        <li>
                            <a href="{{ route('admin.sub-admin.index') }}"
                                class="{{ request()->routeIs('admin.sub-admin*') ? 'active' : '' }}">
                                Sub Admin Desa
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
        </ul>

        <!-- Fungsi: Footer sidebar berisi tombol logout -->
        <div class="sidebar-footer">
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="logout-btn">
                    <img src="{{ asset('assets/icons/keluar.png') }}" alt="Logout" class="custom-icon">
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </nav>
</aside>