<?php

namespace App\Observers;

use App\Models\AktivitasLog;
use App\Models\Sekolah;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class SekolahObserver
{
    public function created(Sekolah $sekolah): void
    {
        $this->catat('tambah', $sekolah);
    }

    public function updated(Sekolah $sekolah): void
    {
        $baru = Arr::except($sekolah->getChanges(), ['updated_at']);

        if ($baru === []) {
            return;
        }

        $lama = Arr::only($sekolah->getOriginal(), array_keys($baru));

        $this->catat('ubah', $sekolah, ['lama' => $lama, 'baru' => $baru]);
    }

    public function deleted(Sekolah $sekolah): void
    {
        $this->catat('hapus', $sekolah);
    }

    private function catat(string $aksi, Sekolah $sekolah, ?array $perubahan = null): void
    {
        AktivitasLog::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'sekolah_id' => $sekolah->id,
            'sekolah_nama' => $sekolah->nama,
            'sekolah_npsn' => $sekolah->npsn,
            'perubahan' => $perubahan,
        ]);
    }
}