@extends('layouts.public')

@section('title', 'Lapisan')

@push('styles')
<link rel="stylesheet"
      href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
<link rel="stylesheet"
      href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
@endpush

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
    <span style="color: #6b7280;">
        Hidupkan atau matikan lapisan lewat kontrol di kanan atas.
    </span>
</div>

<div id="peta" class="peta"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
    const map = Peta.buat('peta');

    // Tiga lapisan, semuanya dibuat kosong dulu
    const lapMarker = L.layerGroup();

    const lapKlaster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 60,
        disableClusteringAtZoom: 17,
        chunkedLoading: true,
    });

    const lapPanas = L.heatLayer([], {
        radius: 30,
        blur: 25,
        maxZoom: 17,
        max: 1.0,
        gradient: {
            0.2: '#2563eb',
            0.4: '#06b6d4',
            0.6: '#84cc16',
            0.8: '#facc15',
            1.0: '#dc2626',
        },
    });

    // Saat dibuka, hanya klaster yang menyala
    lapKlaster.addTo(map);

    // null = tanpa pilihan peta dasar; objek kedua = lapisan yang bisa dihidupkan/dimatikan
    L.control.layers(null, {
        'Marker': lapMarker,
        'Klaster': lapKlaster,
        'Peta panas': lapPanas,
    }, { collapsed: false }).addTo(map);

    async function muat() {
        const jenjang = document.getElementById('filter-jenjang').value;
        const status = document.getElementById('filter-status').value;
        const jumlah = document.getElementById('jumlah');

        try {
            const fitur = await Peta.ambilSekolah(jenjang, status);

            lapMarker.clearLayers();
            lapKlaster.clearLayers();

            // Satu marker hanya boleh berada di satu lapisan,
            // jadi marker dibuat dua kali: satu untuk tiap lapisan
            fitur.forEach((f) => {
                L.marker(Peta.latlng(f))
                    .bindPopup(Peta.popup(f))
                    .addTo(lapMarker);
            });

            lapKlaster.addLayers(fitur.map((f) =>
                L.marker(Peta.latlng(f)).bindPopup(Peta.popup(f))
            ));

            lapPanas.setLatLngs(fitur.map((f) => [...Peta.latlng(f), 0.35]));

            jumlah.textContent = fitur.length + ' sekolah';

            if (fitur.length > 0) {
                map.fitBounds(L.latLngBounds(fitur.map(Peta.latlng)).pad(0.1));
            }
        } catch (e) {
            jumlah.textContent = 'Gagal memuat data';
        }
    }

    document.getElementById('filter-jenjang').addEventListener('change', muat);
    document.getElementById('filter-status').addEventListener('change', muat);

    muat();
</script>
@endpush