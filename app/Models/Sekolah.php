<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Observers\SekolahObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([SekolahObserver::class])]
class Sekolah extends Model
{
    public function profil(): HasOne
    {
        return $this->hasOne(ProfilSekolah::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected $fillable = [
    'npsn', 'nama', 'jenjang', 'status', 'alamat',
    'kecamatan', 'telepon', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        
    return [
    'latitude' => 'float',
    'longitude' => 'float',
    ];
    }

    public function jarakKeMeter(float $lat, float $lng): float
    {
    $r = 6371000; 
    $p1 = deg2rad((float) $this->latitude);
    $p2 = deg2rad($lat);
    $dLat = $p2 - $p1;
    $dLng = deg2rad($lng - (float) $this->longitude);

    $a = sin($dLat / 2) ** 2
       + cos($p1) * cos($p2) * sin($dLng / 2) ** 2;

    return 2 * $r * asin(min(1, sqrt($a)));
    }
}