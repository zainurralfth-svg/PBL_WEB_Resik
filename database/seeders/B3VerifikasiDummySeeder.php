<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\B3Pendaftaran;
use App\Models\B3PendaftaranItem;
use App\Models\Desa;
use App\Models\Masyarakat;
use App\Models\Petugas;
use Illuminate\Support\Facades\Hash;

class B3VerifikasiDummySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Desa tersedia
        $desaSawahan = Desa::firstOrCreate(['nama_desa' => 'Sawahan'], ['id_kecamatan' => 1]);
        $desaGebangayu = Desa::firstOrCreate(['nama_desa' => 'Gebangayu'], ['id_kecamatan' => 1]);
        $desaPayaman = Desa::firstOrCreate(['nama_desa' => 'Payaman'], ['id_kecamatan' => 1]);

        // 2. Pastikan Petugas Dika dan Rofik tersedia
        $petugasDika = Petugas::firstOrCreate(
            ['nama_lengkap' => 'Dika'],
            [
                'username' => 'petugas_dika',
                'password' => Hash::make('password'),
                'level' => 'petugas_dlh',
                'no_hp' => '081234567891',
            ]
        );

        $petugasRofik = Petugas::firstOrCreate(
            ['nama_lengkap' => 'Rofik'],
            [
                'username' => 'petugas_rofik',
                'password' => Hash::make('password'),
                'level' => 'petugas_dlh',
                'no_hp' => '081234567892',
            ]
        );

        // 3. Pastikan Warga / Masyarakat tersedia
        $wargaMahardika = Masyarakat::firstOrCreate(
            ['nama' => 'Mahardika Lucky P.'],
            [
                'email' => 'mahardika@example.com',
                'password' => Hash::make('password'),
                'nik' => '3518000000000001',
                'no_telepon' => '082111111111',
                'alamat' => 'Sawahan',
                'id_desa' => $desaSawahan->id_desa,
            ]
        );

        $wargaMuzza = Masyarakat::firstOrCreate(
            ['nama' => 'Muzza'],
            [
                'email' => 'muzza@example.com',
                'password' => Hash::make('password'),
                'nik' => '3518000000000002',
                'no_telepon' => '082111111112',
                'alamat' => 'Gebangayu',
                'id_desa' => $desaGebangayu->id_desa,
            ]
        );

        $wargaAgus = Masyarakat::firstOrCreate(
            ['nama' => 'Agus Salim'],
            [
                'email' => 'agussalim@example.com',
                'password' => Hash::make('password'),
                'nik' => '3518000000000003',
                'no_telepon' => '082111111113',
                'alamat' => 'Payaman',
                'id_desa' => $desaPayaman->id_desa,
            ]
        );

        // Hapus data lama B3 jika ingin fresh
        B3PendaftaranItem::query()->delete();
        B3Pendaftaran::query()->delete();

        // 4. Baris 1 Figma: Mahardika Lucky P. | Sawahan | Kaleng cat x2 | - | Dika | Dijadwalkan | Verifikasi
        $p1 = B3Pendaftaran::create([
            'id_masyarakat' => $wargaMahardika->id_masyarakat,
            'id_desa' => $desaSawahan->id_desa,
            'id_petugas' => $petugasDika->id_petugas,
            'status' => B3Pendaftaran::DIJADWALKAN,
            'est_berat' => 2.0,
            'total_imbalan' => 0,
        ]);
        B3PendaftaranItem::create([
            'id_pendaftaran' => $p1->id,
            'jenis_b3' => 'Kaleng cat',
            'jumlah' => 2,
            'jumlah_petugas' => null, // belum dijemput (-)
            'harga_satuan' => 2000,
        ]);

        // 5. Baris 2 Figma: Muzza | Gebangayu | Elektronik kecil x1 | Elektronik kecil x1 | Dika | Menunggu verifikasi | Verifikasi
        $p2 = B3Pendaftaran::create([
            'id_masyarakat' => $wargaMuzza->id_masyarakat,
            'id_desa' => $desaGebangayu->id_desa,
            'id_petugas' => $petugasDika->id_petugas,
            'status' => B3Pendaftaran::MENUNGGU_VERIFIKASI,
            'est_berat' => 1.5,
            'total_imbalan' => 0,
        ]);
        B3PendaftaranItem::create([
            'id_pendaftaran' => $p2->id,
            'jenis_b3' => 'Elektronik kecil',
            'jumlah' => 1,
            'jumlah_petugas' => 1,
            'jumlah_verifikasi' => 1,
            'harga_satuan' => 5000,
        ]);

        // 6. Baris 3 Figma: Agus Salim | Payaman | Lampu neon x6 | Lampu neon x6 | Rofik | Diverifikasi | Rp 9.000
        $p3 = B3Pendaftaran::create([
            'id_masyarakat' => $wargaAgus->id_masyarakat,
            'id_desa' => $desaPayaman->id_desa,
            'id_petugas' => $petugasRofik->id_petugas,
            'status' => B3Pendaftaran::DIVERIFIKASI,
            'est_berat' => 1.0,
            'total_imbalan' => 9000,
            'tgl_verifikasi' => now(),
        ]);
        B3PendaftaranItem::create([
            'id_pendaftaran' => $p3->id,
            'jenis_b3' => 'Lampu neon',
            'jumlah' => 6,
            'jumlah_petugas' => 6,
            'jumlah_verifikasi' => 6,
            'harga_satuan' => 1500,
            'subtotal' => 9000,
        ]);

        // 7. Tambahkan 4 pendaftaran status 'terdaftar' agar badge Pendaftaran bertuliskan 4 sesuai Figma
        for ($i = 1; $i <= 4; $i++) {
            $pBaru = B3Pendaftaran::create([
                'id_masyarakat' => $wargaMahardika->id_masyarakat,
                'id_desa' => $desaSawahan->id_desa,
                'status' => B3Pendaftaran::TERDAFTAR,
                'est_berat' => 1.0,
            ]);
            B3PendaftaranItem::create([
                'id_pendaftaran' => $pBaru->id,
                'jenis_b3' => 'Baterai bekas',
                'jumlah' => 5 * $i,
            ]);
        }
    }
}

