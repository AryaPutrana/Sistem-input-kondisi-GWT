<?php

namespace App\Http\Controllers;

use App\Models\FinanceReport;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    /**
     * Tampilkan foto bukti pemeriksaan.
     *
     * Foto tidak lagi dilayani sebagai file statis. File dibaca dari disk
     * privat lalu dikirim ke pengguna yang sudah login dan berhak
     * melihat record tersebut.
     */
    public function monitoring(Request $request, WaterMonitoring $monitoring): BinaryFileResponse
    {
        $this->authorizePhoto($request, $monitoring->user_id);

        return $this->stream($monitoring);
    }

    /**
     * Tampilkan foto bukti input keuangan.
     */
    public function finance(Request $request, FinanceReport $report): BinaryFileResponse
    {
        $this->authorizePhoto($request, $report->user_id);

        return $this->stream($report);
    }

    /**
     * Admin dapat melihat semua foto, petugas hanya miliknya sendiri.
     */
    protected function authorizePhoto(Request $request, ?int $ownerId): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($ownerId === null || (int) $ownerId !== (int) $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat foto ini.');
        }
    }

    /**
     * Kirim file foto ke browser dengan Content-Type yang dikunci.
     */
    protected function stream(WaterMonitoring|FinanceReport $record): BinaryFileResponse
    {
        $path = $record->fotoPath();

        if ($path === null) {
            abort(404, 'Foto tidak ditemukan.');
        }

        $disk = $record->fotoDisk();

        if (! $disk->exists($path)) {
            abort(404, 'Foto tidak ditemukan.');
        }

        return response()->file($disk->path($path), [
            'Content-Type' => PhotoStorage::mimeFor($path),
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($path)).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
