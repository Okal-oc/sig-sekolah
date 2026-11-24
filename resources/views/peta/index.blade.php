@extends('layouts.public')
@section('title', 'Peta Sekolah')
 
@section('content')
<div class="toolbar">
    <label>Jenjang
        <select id="filter-jenjang">
            <option value="">Semua jenjang</option>
            <option value="SD">SD</option>
            <option value="SMP">SMP</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
        </select>
    </label>
    <label>Status
        <select id="filter-status">
            <option value="">Semua status</option>
            <option value="Negeri">Negeri</option>
            <option value="Swasta">Swasta</option>
        </select>
    </label>
    <span id="jumlah"></span>
    <button type="button" id="btn-unduh"
        style="margin-left: 8px; padding: 4px 10px;">
        Unduh GeoJSON
    </button>
</div>
 
<div id="peta" class="peta"></div>
@endsection
 
@push('scripts')
<script>
    const map = Peta.buat('peta');
    const lapisan = L.layerGroup().addTo(map);

    let nomorPermintaan = 0;
    let tunda = null;

    async function muat() {
        const jenjang = document.getElementById('filter-jenjang').value;
        const status = document.getElementById('filter-status').value;
        const jumlah = document.getElementById('jumlah');
        const nomor = ++nomorPermintaan;

        try {
            const fitur = await Peta.ambilSekolah(jenjang, status, map.getBounds().pad(0.2));

            if (nomor !== nomorPermintaan) return;

            lapisan.clearLayers();
            fitur.forEach((f) => {
                L.marker(Peta.latlng(f))
                    .bindPopup(Peta.popup(f))
                    .addTo(lapisan);
            });

            jumlah.textContent = fitur.length + ' sekolah di area peta';
        } catch (e) {
            if (nomor === nomorPermintaan) {
                jumlah.textContent = 'Gagal memuat data';
            }
        }
    }

    function muatTertunda() {
        clearTimeout(tunda);
        tunda = setTimeout(muat, 300);
    }

    document.getElementById('filter-jenjang').addEventListener('change', muat);
    document.getElementById('filter-status').addEventListener('change', muat);
    map.on('moveend', muatTertunda);

    muat();

    document.getElementById('btn-unduh').addEventListener('click', async () => {
        const jenjang = document.getElementById('filter-jenjang').value;
        const elStatus = document.getElementById('filter-status'); 
        const status = elStatus ? elStatus.value : '';

        try {
            const fitur = await Peta.ambilSekolah(jenjang, status);
            const geojson = { type: 'FeatureCollection', features: fitur };

            const blob = new Blob([JSON.stringify(geojson, null, 2)],
                { type: 'application/geo+json' });
            const url = URL.createObjectURL(blob);

            const nama = ['sekolah', jenjang, status]
                .filter(Boolean).join('-').toLowerCase();
            const a = document.createElement('a');
            a.href = url;
            a.download = nama + '.geojson';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            console.error(e);
            alert('Gagal mengunduh data.');
        }
    });
</script>
@endpush