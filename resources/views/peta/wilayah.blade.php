@extends('layouts.public')
@section('title', 'Wilayah')
 
@section('content')
<div class="toolbar">
    <label>Jenis wilayah
        <select id="mode">
            <option value="resmi">Batas resmi kecamatan</option>
            <option value="sebaran">Poligon sebaran sekolah</option>
        </select>
    </label>
    <label>Jenjang
        <select id="filter-jenjang">
            <option value="">Semua jenjang</option>
            <option value="SD">SD</option>
            <option value="SMP">SMP</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
        </select>
    </label>
    <span id="jumlah"></span>
</div>
 
<div id="peta" class="peta"></div>
 
<div class="panel" style="right: 12px; bottom: 24px; font-size: 13px;">
    <strong>Jumlah sekolah</strong>
    <div><span style="color: #dc2626;">&#9632;</span> Terbanyak</div>
    <div><span style="color: #ea580c;">&#9632;</span> Banyak</div>
    <div><span style="color: #f59e0b;">&#9632;</span> Sedang</div>
    <div><span style="color: #3b82f6;">&#9632;</span> Sedikit</div>
    <small style="color: #6b7280;">Relatif terhadap kecamatan terbanyak</small>
</div>
@endsection
 
@push('scripts')
<script>
    const map = Peta.buat('peta');
    let lapisan = null;
    let batas = null; 

    function warna(rasio) {
        if (rasio >= 0.8) return '#dc2626';
        if (rasio >= 0.6) return '#ea580c';
        if (rasio >= 0.4) return '#f59e0b';
        return '#3b82f6';
    }

    async function dataSebaran(jenjang) {
        const url = `${window.PETA_URL.wilayah}?jenjang=${encodeURIComponent(jenjang)}`;
        return (await fetch(url)).json();
    }

    async function ambilBatas() {
        if (batas === null) {
            const respon = await fetch(window.PETA_URL.batas);
            if (!respon.ok) throw new Error('Berkas batas kecamatan tidak ditemukan');
            batas = await respon.json();
        }
        return batas;
    }

    async function dataResmi(jenjang) {
        const [geo, sekolah] = await Promise.all([
            ambilBatas(),
            Peta.ambilSekolah(jenjang),
        ]);

        const hitung = {};
        sekolah.forEach((f) => {
            const k = f.properties.kecamatan;
            if (!hitung[k]) hitung[k] = { jumlah_sekolah: 0, jumlah_siswa: 0 };
            hitung[k].jumlah_sekolah += 1;
            hitung[k].jumlah_siswa += f.properties.jumlah_siswa ?? 0;
        });

        return {
            type: 'FeatureCollection',
            features: geo.features.map((f) => {
                const h = hitung[f.properties.kecamatan];
                return {
                    ...f,
                    properties: {
                        kecamatan: f.properties.kecamatan,
                        jumlah_sekolah: h ? h.jumlah_sekolah : 0,
                        jumlah_siswa: h ? h.jumlah_siswa : 0,
                    },
                };
            }),
        };
    }

    async function muat() {
        const jenjang = document.getElementById('filter-jenjang').value;
        const mode = document.getElementById('mode').value;
        const jumlah = document.getElementById('jumlah');

        try {
            const data = mode === 'resmi'
                ? await dataResmi(jenjang)
                : await dataSebaran(jenjang);

            if (lapisan) {
                map.removeLayer(lapisan);
            }

            const maks = Math.max(1, ...data.features.map((f) => f.properties.jumlah_sekolah));
            const keterangan = mode === 'resmi'
                ? 'Batas kecamatan (disederhanakan dari data BIG)'
                : 'Poligon sebaran (perkiraan)';

            lapisan = L.geoJSON(data, {
                style: (f) => {
                    const w = warna(f.properties.jumlah_sekolah / maks);
                    return { color: w, fillColor: w, fillOpacity: 0.3, weight: 3 };
                },
                onEachFeature: (f, layer) => {
                    const p = f.properties;
                    layer.bindTooltip(Peta.esc(p.kecamatan), { sticky: true });
                    layer.bindPopup(`
                        <strong>${Peta.esc(p.kecamatan)}</strong><br>
                        Jumlah sekolah: ${p.jumlah_sekolah}<br>
                        Jumlah siswa: ${p.jumlah_siswa}<br>
                        <small>${keterangan}</small>
                    `);
                },
            }).addTo(map);

            jumlah.textContent = data.features.length + ' wilayah';

            if (data.features.length > 0) {
                map.fitBounds(lapisan.getBounds().pad(0.1));
            }
        } catch (e) {
            jumlah.textContent = 'Gagal memuat data';
            console.error(e);
        }
    }

    document.getElementById('filter-jenjang').addEventListener('change', muat);
    document.getElementById('mode').addEventListener('change', muat);

    muat();
</script>
@endpush