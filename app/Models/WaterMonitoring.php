<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterMonitoring extends Model
{
    use HasFactory;

    public const KONDISI = [
        'normal' => 'Normal',
        'debit_turun' => 'Debit Turun',
        'tekanan_air_kecil' => 'Tekanan Air Kecil',
    ];

    public const SESI = [
        'pagi' => 'Pagi',
        'siang' => 'Siang',
        'sore' => 'Sore',
    ];

    public const SESI_WAKTU = [
        'pagi' => '08:00',
        'siang' => '12:00',
        'sore' => '16:00',
    ];

    public const STATUS_PERINGATAN = [
        'normal' => 'NORMAL',
        'debit_turun' => 'PERLU PERHATIAN',
        'tekanan_air_kecil' => 'PERLU PERHATIAN',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'location_id',
        'tanggal',
        'sesi',
        'waktu',
        'kondisi',
        'keterangan',
        'foto',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date',
        'waktu' => 'datetime:H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MonitoringLocation::class, 'location_id');
    }
}
