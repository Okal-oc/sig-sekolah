<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasLog extends Model
{
    protected $table = 'aktivitas_logs';

    protected $fillable = [
        'user_id', 'aksi', 'sekolah_id',
        'sekolah_nama', 'sekolah_npsn', 'perubahan',
    ];

    protected function casts(): array
    {
        return ['perubahan' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}