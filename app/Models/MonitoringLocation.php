<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringLocation extends Model
{
    use HasFactory;

    public const JENIS = [
        'gwt' => 'GWT',
        'kolam_air_bersih' => 'Kolam Air Bersih',
    ];

    public const STATUS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_lokasi',
        'jenis',
        'keterangan',
        'status',
    ];

    public function waterMonitorings(): HasMany
    {
        return $this->hasMany(WaterMonitoring::class, 'location_id');
    }
}
