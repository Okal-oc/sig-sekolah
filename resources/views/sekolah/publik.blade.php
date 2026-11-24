@extends('layouts.app')
@section('title', $sekolah->nama)
@section('content')
@php $p = $sekolah->profil; @endphp

<a href="{{ url('/') }}" class="text-blue-700 text-sm">&larr; Kembali ke peta</a>

<div class="bg-white rounded shadow p-6 mt-3">
    <div class="flex items-center gap-4">
        @if ($p?->logo)
            <img src="{{ asset('storage/' . $p->logo) }}"
                 alt="Logo {{ $sekolah->nama }}"
                 style="height: 72px;" class="rounded border">
        @endif
        <div>
            <h1 class="text-2xl font-bold">{{ $sekolah->nama }}</h1>
            <p class="text-gray-600">
                NPSN {{ $sekolah->npsn }} - {{ $sekolah->jenjang }} {{ $sekolah->status }}
            </p>
        </div>
    </div>

    <dl class="grid md:grid-cols-2 gap-4 mt-6 text-sm">
        <div><dt class="font-medium">Alamat</dt>
            <dd>{{ $sekolah->alamat }}, {{ $sekolah->kecamatan }}</dd></div>
        <div><dt class="font-medium">Telepon</dt>
            <dd>{{ $sekolah->telepon ?: '-' }}</dd></div>
        <div><dt class="font-medium">Kepala Sekolah</dt>
            <dd>{{ $p?->kepala_sekolah ?: '-' }}</dd></div>
        <div><dt class="font-medium">Akreditasi</dt>
            <dd>{{ $p?->akreditasi ?: '-' }}</dd></div>
        <div><dt class="font-medium">Tahun Berdiri</dt>
            <dd>{{ $p?->tahun_berdiri ?: '-' }}</dd></div>
        <div><dt class="font-medium">Jumlah Siswa / Guru</dt>
            <dd>{{ $p?->jumlah_siswa ?? '-' }} / {{ $p?->jumlah_guru ?? '-' }}</dd></div>
    </dl>

    @if ($p?->deskripsi)
        <div class="mt-6">
            <h2 class="font-medium">Deskripsi</h2>
            <p class="text-sm mt-1">{!! nl2br(e($p->deskripsi)) !!}</p>
        </div>
    @endif

    <div id="peta-lokasi" class="rounded border mt-6" style="height: 280px;"></div>
</div>
@endsection

@push('scripts')
<script>
    const pos = [{{ (float) $sekolah->latitude }}, {{ (float) $sekolah->longitude }}];
    const m = L.map('peta-lokasi').setView(pos, 16); 
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(m);
    L.marker(pos).addTo(m);
</script>
@endpush