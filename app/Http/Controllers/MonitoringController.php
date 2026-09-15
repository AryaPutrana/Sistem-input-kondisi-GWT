<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaterMonitoringRequest;
use App\Http\Requests\UpdateWaterMonitoringRequest;
use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Tampilkan daftar hasil pemeriksaan.
     *
     * Admin melihat seluruh data, petugas hanya melihat data miliknya.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $filterTanggal = $request->input('tanggal');

        $monitorings = WaterMonitoring::with(['user', 'location'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when($filterTanggal, fn ($query) => $query->whereDate('tanggal', $filterTanggal))
            ->latest('tanggal')
            ->latest('waktu')
            ->paginate(10)
            ->withQueryString();

        return view('monitoring.index', [
            'monitorings' => $monitorings,
            'isAdmin' => $user->isAdmin(),
            'filterTanggal' => $filterTanggal,
        ]);
    }

    /**
     * Tampilkan form input pemeriksaan.
     */
    public function create(): View
    {
        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        return view('monitoring.create', [
            'locations' => $locations,
            'sesiOptions' => WaterMonitoring::SESI,
            'kondisiOptions' => WaterMonitoring::KONDISI,
        ]);
    }

    /**
     * Simpan hasil pemeriksaan.
     */
    public function store(StoreWaterMonitoringRequest $request): RedirectResponse
    {
        $fotoPath = $request->file('foto')->store('monitoring', 'public');

        WaterMonitoring::create([
            'user_id' => Auth::id(),
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'sesi' => $request->sesi,
            'waktu' => WaterMonitoring::SESI_WAKTU[$request->sesi],
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
            'foto' => $fotoPath,
        ]);

        if ($request->kondisi !== 'normal') {
            $label = WaterMonitoring::KONDISI[$request->kondisi];

            return redirect()->route('dashboard')
                ->with('error', "Pemeriksaan tersimpan. Kondisi \"{$label}\" terdeteksi — PERLU PERHATIAN.");
        }

        return redirect()->route('dashboard')
            ->with('success', 'Pemeriksaan berhasil disimpan.');
    }

    /**
     * Tampilkan form edit pemeriksaan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     */
    public function edit(WaterMonitoring $monitoring): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        return view('monitoring.edit', [
            'monitoring' => $monitoring,
            'locations' => $locations,
            'sesiOptions' => WaterMonitoring::SESI,
            'kondisiOptions' => WaterMonitoring::KONDISI,
        ]);
    }

    /**
     * Perbarui hasil pemeriksaan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     * Foto bersifat opsional: bila tidak diganti, foto lama tetap dipakai.
     */
    public function update(UpdateWaterMonitoringRequest $request, WaterMonitoring $monitoring): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $data = [
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'sesi' => $request->sesi,
            'waktu' => WaterMonitoring::SESI_WAKTU[$request->sesi],
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
        ];

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('monitoring', 'public');

            if ($monitoring->foto) {
                Storage::disk('public')->delete($monitoring->foto);
            }
        }

        $monitoring->update($data);

        if ($monitoring->kondisi !== 'normal') {
            $label = WaterMonitoring::KONDISI[$monitoring->kondisi];

            return redirect()->route('dashboard')
                ->with('error', "Pemeriksaan diperbarui. Kondisi \"{$label}\" terdeteksi — PERLU PERHATIAN.");
        }

        return redirect()->route('dashboard')
            ->with('success', 'Pemeriksaan berhasil diperbarui.');
    }

    /**
     * Tampilkan detail satu pemeriksaan (URL dapat dibagikan sesuai PRD pasal 23).
     *
     * Petugas hanya dapat melihat data miliknya sendiri, admin dapat
     * melihat semua data (dicek di sini -> 403 bila bukan miliknya).
     */
    public function show(WaterMonitoring $monitoring): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data ini.');
        }

        $monitoring->load(['user', 'location']);

        return view('monitoring.show', [
            'monitoring' => $monitoring,
            'isAdmin' => $user->isAdmin(),
        ]);
    }
    /**
     * Hapus data pemeriksaan.
     *
     * Petugas hanya dapat menghapus data miliknya sendiri.
     * Admin dapat menghapus data siapa saja.
     * File foto ikut dihapus dari storage.
     */
    public function destroy(WaterMonitoring $monitoring): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus data ini.');
        }

        if ($monitoring->foto) {
            Storage::disk('public')->delete($monitoring->foto);
        }

        $monitoring->delete();

        return back()->with('success', 'Data pemeriksaan berhasil dihapus.');
    }
}
