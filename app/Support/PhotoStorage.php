<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Helper tunggal untuk seluruh akses file foto bukti.
 *
 * Foto baru disimpan pada disk privat 'evidence' yang tidak tershuaikan
 * lewat symlink public/storage. Foto lama masih berada di disk 'public'
 * sampai dipindahkan dengan `php artisan evidence:rehome`.
 */
class PhotoStorage
{
    /**
     * Disk privat untuk foto baru.
     */
    public const EVIDENCE = 'evidence';

    /**
     * Disk lama yang masih dapat diakses publik.
     */
    public const LEGACY_PUBLIC = 'public';

    /**
     * Folder tempat foto disimpan per jenis data.
     */
    public const DIR_MONITORING = 'monitoring';

    public const DIR_FINANCE = 'keuangan';

    /**
     * Tentukan nama disk secara aman.
     *
     * Hanya nilai 'evidence' yang dianggap privat. Nilai lain — termasuk
     * null, kosong, atau isian yang tidak dikenal — dipetakan ke disk
     * 'public' agar foto lama tetap terbaca dan tidak terjadi error 500.
     */
    public static function diskName(mixed $fotoDisk): string
    {
        return $fotoDisk === self::EVIDENCE ? self::EVIDENCE : self::LEGACY_PUBLIC;
    }

    /**
     * Instance filesystem sesuai nilai foto_disk.
     */
    public static function disk(mixed $fotoDisk): Filesystem
    {
        return Storage::disk(self::diskName($fotoDisk));
    }

    /**
     * Bersihkan nilai path foto sebelum dipakai.
     *
     * Mengembalikan null bila kosong atau berpotensi keluar dari folder
     * penyimpanan (path traversal). Nilai foto berasal dari UploadedFile
     * sehingga normalnya sudah aman, tetapi.database bisa saja dikoreksi
     * manual oleh operator.
     */
    public static function safePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, "\0")) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return null;
        }

        return $path;
    }

    /**
     * Content-Type untuk path foto.
     *
     * Validasi upload hanya mengizinkan jpeg/jpg/png. Ekstensi lain
     * sengaja dilayani sebagai application/octet-stream agar browser
     * memaksa mengunduh, bukan merender sebagai HTML. Pemanggil yang
     * memerlukan nilai lain (misalnya penyematan gambar ke PDF) dapat
     * menentukan sendiri nilai cadangan melalui $fallback.
     */
    public static function mimeFor(string $path, string $fallback = 'application/octet-stream'): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $fallback,
        };
    }
}
