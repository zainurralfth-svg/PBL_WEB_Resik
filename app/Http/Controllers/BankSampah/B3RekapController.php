<?php

namespace App\Http\Controllers\BankSampah;

use App\Exports\B3RekapExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class B3RekapController extends Controller
{
    /**
     * Download rekap B3 (Excel).
     * Admin utama: semua desa. Sub admin desa: otomatis hanya desanya.
     */
    public function export()
    {
        $admin = auth()->guard('admin')->user();

        $idDesa = ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa)
            ? (int) $admin->id_desa
            : null;

        $namaFile = 'Rekap_B3_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new B3RekapExport($idDesa), $namaFile);
    }
}