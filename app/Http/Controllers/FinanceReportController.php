<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceReportRequest;
use App\Http\Requests\UpdateFinanceReportRequest;
use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FinanceReportController extends Controller
{
    /**
     * Tampilkan laporan bulanan input keuangan.
     *
     * Khusus admin (dibatasi di rute). Data difilter berdasarkan bulan
     * (format Y-m), default bulan berjalan.
     */
    public function index(Request $request): View
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->whereBetween('tanggal', [$from, $to])
            ->latest('tanggal')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('finance_reports.index', [
            'reports' => $reports,
            'bulan' => $bulan,
            'bulanLabel' => $this->bulanLabel($bulan),
            'conditionLabels' => FinanceReport::KONDISI,
            'total' => FinanceReport::whereBetween('tanggal', [$from, $to])->count(),
            'summary' => $this->summaryKondisi($from, $to),
        ]);
    }

    /**
     * Unduh laporan bulanan dalam bentuk PDF.
     *
     * Khusus admin (dibatasi di rute). Bulan mengikuti filter yang
     * dipilih di halaman laporan (default bulan berjalan).
     */
    public function exportPdf(Request $request): Response
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->whereBetween('tanggal', [$from, $to])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $rows = $reports->values()->map(fn (FinanceReport $report, int $i) => [
            'no' => $i + 1,
            'tanggal' => $report->tanggal->format('d-m-Y'),
            'kondisi' => FinanceReport::KONDISI[$report->kondisi] ?? ucfirst($report->kondisi),
            'lokasi' => $report->location?->nama_lokasi ?? '—',
            'keterangan' => $report->keterangan,
            'foto_src' => $this->fotoToDataUri($report->foto),
        ]);

        $pdf = Pdf::loadView('finance_reports.pdf', [
            'rows' => $rows,
            'bulanLabel' => mb_strtoupper($this->bulanLabel($bulan)),
            'total' => $rows->count(),
            'summary' => $this->summaryKondisi($from, $to),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-kondisi-air-'.$bulan.'.pdf');
    }

    /**
     * Tampilkan form input keuangan.
     */
    public function create(): View
    {
        return view('finance_reports.create', [
            'kondisiOptions' => FinanceReport::KONDISI,
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    /**
     * Simpan data input keuangan.
     */
    public function store(StoreFinanceReportRequest $request): RedirectResponse
    {
        $fotoPath = $request->file('foto')->store('keuangan', 'public');

        FinanceReport::create([
            'user_id' => Auth::id(),
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
            'foto' => $fotoPath,
        ]);

        return redirect()->route('keuangan.riwayat', ['bulan' => Carbon::parse($request->tanggal)->format('Y-m')])
            ->with('success', 'Input keuangan berhasil disimpan.');
    }

    /**
     * Tampilkan form edit input keuangan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     */
    public function edit(FinanceReport $report): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        return view('finance_reports.edit', [
            'report' => $report,
            'kondisiOptions' => FinanceReport::KONDISI,
            'locationOptions' => $this->locationOptions($report),
        ]);
    }

    /**
     * Perbarui data input keuangan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     * Foto bersifat opsional: bila tidak diganti, foto lama tetap dipakai.
     */
    public function update(UpdateFinanceReportRequest $request, FinanceReport $report): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $data = [
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
        ];

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('keuangan', 'public');

            if ($report->foto) {
                Storage::disk('public')->delete($report->foto);
            }
        }

        $report->update($data);

        $bulan = Carbon::parse($request->tanggal)->format('Y-m');

        if (Auth::user()->isAdmin()) {
            return redirect()->route('keuangan.index', ['bulan' => $bulan])
                ->with('success', 'Input keuangan berhasil diperbarui.');
        }

        return redirect()->route('keuangan.riwayat', ['bulan' => $bulan])
            ->with('success', 'Input keuangan berhasil diperbarui.');
    }

    /**
     * Hapus data input keuangan.
     *
     * Petugas hanya dapat menghapus data miliknya sendiri.
     * Admin dapat menghapus data siapa saja.
     * File foto ikut dihapus dari storage.
     */
    public function destroy(FinanceReport $report): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus data ini.');
        }

        if ($report->foto) {
            Storage::disk('public')->delete($report->foto);
        }

        $report->delete();

        return back()->with('success', 'Data input keuangan berhasil dihapus.');
    }

    /**
     * Tampilkan riwayat input keuangan milik petugas yang sedang login.
     *
     * Khusus petugas (dibatasi di rute). Data difilter berdasarkan bulan
     * (format Y-m), default bulan berjalan, diurutkan dari yang terbaru.
     */
    public function riwayat(Request $request): View
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->where('user_id', Auth::id())
            ->whereBetween('tanggal', [$from, $to])
            ->latest('tanggal')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('finance_reports.riwayat', [
            'reports' => $reports,
            'bulan' => $bulan,
            'bulanLabel' => $this->bulanLabel($bulan),
            'conditionLabels' => FinanceReport::KONDISI,
            'total' => $reports->total(),
            'summary' => $this->summaryKondisi($from, $to, Auth::id()),
        ]);
    }

    /**
     * Daftar lokasi bulanan untuk pilihan pada form input/edit.
     *
     * Menampilkan lokasi berstatus aktif. Saat mengedit, lokasi milik
     * laporan tetap disertakan meskipun statusnya nonaktif.
     */
    protected function locationOptions(?FinanceReport $report = null): Collection
    {
        return FinanceLocation::query()
            ->where(function ($query) use ($report) {
                $query->where('status', 'aktif');

                if ($report && $report->location_id) {
                    $query->orWhere('id', $report->location_id);
                }
            })
            ->orderBy('nama_lokasi')
            ->get();
    }

    /**
     * Tentukan bulan (format Y-m) berdasarkan request.
     *
     * Prioritas: parameter bulan -> bulan berjalan.
     */
    protected function resolveBulan(Request $request): string
    {
        $bulan = $request->string('bulan')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return $bulan;
        }

        return now()->format('Y-m');
    }

    /**
     * Label bulan untuk tampilan (contoh: September 2026).
     */
    protected function bulanLabel(string $bulan): string
    {
        return Carbon::createFromFormat('Y-m', $bulan)->locale('id')->translatedFormat('F Y');
    }

    /**
     * Ringkasan jumlah data per kondisi dalam rentang tanggal tertentu.
     *
     * Bila $userId diberikan, hanya menghitung data milik user tersebut.
     * Mengembalikan array ber-key kondisi dengan nilai 0 bila tidak ada data.
     */
    protected function summaryKondisi(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $query = FinanceReport::whereBetween('tanggal', [$from, $to]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $counts = $query->selectRaw('kondisi, count(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi')
            ->toArray();

        $summary = [];

        foreach (array_keys(FinanceReport::KONDISI) as $kondisi) {
            $summary[$kondisi] = $counts[$kondisi] ?? 0;
        }

        return $summary;
    }

    /**
     * Ubah file foto di storage public menjadi data URI untuk di-embed di PDF.
     *
     * Mengembalikan null bila path kosong atau file tidak ditemukan.
     */
    protected function fotoToDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
