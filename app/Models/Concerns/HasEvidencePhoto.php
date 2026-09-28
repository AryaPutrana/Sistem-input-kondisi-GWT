<?php

namespace App\Models\Concerns;

use App\Support\PhotoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

trait HasEvidencePhoto
{
    /**
     * Nama disk tempat foto record ini tersimpan.
     */
    public function fotoDiskName(): string
    {
        return PhotoStorage::diskName($this->foto_disk);
    }

    /**
     * Instance filesystem untuk foto record ini.
     */
    public function fotoDisk(): Filesystem
    {
        return PhotoStorage::disk($this->foto_disk);
    }

    /**
     * Path foto yang sudah dibersihkan, atau null bila tidak usable.
     */
    public function fotoPath(): ?string
    {
        return PhotoStorage::safePath($this->foto);
    }

    /**
     * Apakah file foto benar-benar ada di disk yang sesuai.
     */
    public function fotoExists(): bool
    {
        $path = $this->fotoPath();

        if ($path === null) {
            return false;
        }

        return $this->fotoDisk()->exists($path);
    }

    /**
     * Hapus file foto bila ada. Aman dipanggil berkali-kali.
     */
    public function deleteFotoFile(): bool
    {
        $path = $this->fotoPath();

        if ($path === null) {
            return false;
        }

        $disk = $this->fotoDisk();

        if (! $disk->exists($path)) {
            return false;
        }

        return $disk->delete($path);
    }
}
