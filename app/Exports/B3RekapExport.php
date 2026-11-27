<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Rekap B3 (dua sheet: Rekap B3 dan Per Desa).
 * $idDesa diisi untuk sub admin desa; null = semua desa (admin utama).
 */
class B3RekapExport implements WithMultipleSheets
{
    protected ?int $idDesa;

    public function __construct(?int $idDesa = null)
    {
        $this->idDesa = $idDesa;
    }

    public function sheets(): array
    {
        return [
            new B3RekapJenisSheet($this->idDesa),
            new B3RekapDesaSheet($this->idDesa),
        ];
    }
}

/** Sheet 1: rekap per jenis B3 */
class B3RekapJenisSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected ?int $idDesa;

    public function __construct(?int $idDesa = null)
    {
        $this->idDesa = $idDesa;
    }

    public function collection()
    {
        $rows = DB::table('b3_pendaftaran_items as i')
            ->join('b3_pendaftarans as p', 'p.id', '=', 'i.id_pendaftaran')
            ->where('p.status', 'diverifikasi')
            ->when($this->idDesa, fn ($q) => $q->where('p.id_desa', $this->idDesa))
            ->groupBy('i.jenis_b3')
            ->orderBy('i.jenis_b3')
            ->selectRaw('i.jenis_b3, COALESCE(SUM(i.jumlah_verifikasi), 0) as jumlah, COALESCE(SUM(i.subtotal), 0) as nilai')
            ->get()
            ->map(fn ($r) => [$r->jenis_b3, (int) $r->jumlah, (float) $r->nilai]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                'Total',
                $rows->sum(fn ($r) => $r[1]),
                $rows->sum(fn ($r) => $r[2]),
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Jenis B3', 'Jumlah (item)', 'Nilai Saldo (Rp)'];
    }

    public function title(): string
    {
        return 'Rekap B3';
    }

    public function columnFormats(): array
    {
        return ['C' => '"Rp" #,##0'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1B2E22']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4FA574']],
        ]);

        $baris = $sheet->getHighestRow();
        if ($baris > 2) {
            $sheet->getStyle("A{$baris}:C{$baris}")->getFont()->setBold(true);
        }

        return [];
    }
}

/** Sheet 2: rekap per desa */
class B3RekapDesaSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected ?int $idDesa;

    public function __construct(?int $idDesa = null)
    {
        $this->idDesa = $idDesa;
    }

    public function collection()
    {
        $rows = DB::table('b3_pendaftarans as p')
            ->leftJoin('desa as d', 'd.id_desa', '=', 'p.id_desa')
            ->leftJoin('b3_pendaftaran_items as i', 'i.id_pendaftaran', '=', 'p.id')
            ->where('p.status', 'diverifikasi')
            ->when($this->idDesa, fn ($q) => $q->where('p.id_desa', $this->idDesa))
            ->groupBy('p.id_desa', 'd.nama_desa')
            ->orderBy('d.nama_desa')
            ->selectRaw("COALESCE(d.nama_desa, '-') as desa,
                COUNT(DISTINCT CONCAT(IFNULL(p.id_masyarakat, 0), '-', IFNULL(p.id_pns, 0))) as warga,
                COALESCE(SUM(i.jumlah_verifikasi), 0) as item,
                COALESCE(SUM(i.subtotal), 0) as nilai")
            ->get()
            ->map(fn ($r) => [$r->desa, (int) $r->warga, (int) $r->item, (float) $r->nilai]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                'Total',
                $rows->sum(fn ($r) => $r[1]),
                $rows->sum(fn ($r) => $r[2]),
                $rows->sum(fn ($r) => $r[3]),
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Desa', 'Jumlah Warga', 'Jumlah Item', 'Nilai Saldo (Rp)'];
    }

    public function title(): string
    {
        return 'Per Desa';
    }

    public function columnFormats(): array
    {
        return ['D' => '"Rp" #,##0'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1B2E22']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4FA574']],
        ]);

        $baris = $sheet->getHighestRow();
        if ($baris > 2) {
            $sheet->getStyle("A{$baris}:D{$baris}")->getFont()->setBold(true);
        }

        return [];
    }
}