@extends('layouts.public')
@section('title', 'Cek Lokasi')
 
@section('content')
<div class="toolbar">
    Klik di mana saja pada peta untuk melihat alamat dan sekolah terdekat.
    <button type="button" id="btn-lokasi">Lokasi saya</button>
    <span id="pesan-lokasi" style="color: #6b7280;"></span>
</div>
 
<div id="peta" class="peta"></div>
 
<div id="hasil" class="panel"
     style="top: 64px; right: 12px; width: 320px; max-height: 70%;
            overflow: auto; display: none;"></div>
@endsection
 
@push('scripts')
<script>
    const map = Peta.buat('peta');
    const lapisan = L.layerGroup().addTo(map);
    const panel = document.getElementById('hasil');

    let semua = [];
    let terakhir = 0;
    const RADIUS_KM = 1;

    Peta.ambilSekolah().then((fitur) => { semua = fitur; });

    // n sekolah terdekat dari sebuah titik [lat, lng]
    function terdekat(titik, n = 3) {
        return semua
            .map((f) => ({ fitur: f, jarak: Peta.jarakKm(titik, Peta.latlng(f)) }))
            .sort((a, b) => a.jarak - b.jarak)
            .slice(0, n);
    }

    async function ambilAlamat(lat, lng) {
        const url = 'https://nominatim.openstreetmap.org/reverse'
            + '?format=jsonv2&addressdetails=1&accept-language=id'
            + `&lat=${lat}&lon=${lng}`;

        const respon = await fetch(url);
        if (!respon.ok) throw new Error('HTTP ' + respon.status);
        return respon.json();
    }

    // Kebijakan Nominatim: maksimal 1 permintaan per detik
    function bolehMeminta() {
        const sekarang = Date.now();
        if (sekarang - terakhir < 1200) return false;
        terakhir = sekarang;
        return true;
    }

    // Seluruh proses untuk satu titik: tanda, garis, alamat, dan daftar sekolah
    async function periksa(lat, lng) {
        const titik = [lat, lng];

        lapisan.clearLayers();

        L.circleMarker(titik, {
            radius: 9, color: '#fff', weight: 3,
            fillColor: '#ef4444', fillOpacity: 1,
        }).addTo(lapisan);

        L.circle(titik, {
            radius: RADIUS_KM * 1000, 
            color: '#2563eb', weight: 2,
            fillColor: '#3b82f6', fillOpacity: 0.12,
        }).addTo(lapisan);

        const dalam = semua.filter(
            (f) => Peta.jarakKm(titik, Peta.latlng(f)) <= RADIUS_KM
        );

        const perJenjang = ['SD', 'SMP', 'SMA', 'SMK']
            .map((j) => j + ': ' + dalam.filter((f) => f.properties.jenjang === j).length)
            .join(' | ');

        const dekat = terdekat(titik);

        dekat.forEach((d) => {
            L.polyline([titik, Peta.latlng(d.fitur)], {
                color: '#2563eb', weight: 2, dashArray: '6 6',
            }).addTo(lapisan);
        });

        panel.style.display = 'block';
        panel.textContent = 'Mencari alamat...';

        let bagianAlamat = '<em>Alamat tidak dapat dimuat.</em>';

        try {
            const data = await ambilAlamat(lat, lng);
            const a = data.address || {};

            const baris = [
                ['Jalan', a.road],
                ['Kelurahan/Desa', a.suburb || a.neighbourhood || a.village],
                ['Kota/Kabupaten', a.city || a.town || a.municipality || a.county],
                ['Provinsi', a.state],
                ['Kode pos', a.postcode],
            ].map(([k, v]) => `
                <tr>
                    <td style="padding: 2px 8px 2px 0; color: #6b7280;">${k}</td>
                    <td>${Peta.esc(v) || '-'}</td>
                </tr>`).join('');

            bagianAlamat = `
                <p style="margin: 6px 0;">${Peta.esc(data.display_name || '-')}</p>
                <table style="font-size: 13px;">${baris}</table>`;
        } catch (err) {
            console.error(err);
        }

        const daftar = dekat.map((d) => `
            <li data-npsn="${Peta.esc(d.fitur.properties.npsn)}"
                style="padding: 6px 0; border-top: 1px solid #e5e7eb; cursor: pointer;">
                <strong>${Peta.esc(d.fitur.properties.nama)}</strong><br>
                <small>${d.jarak.toFixed(2)} km (garis lurus)</small>
            </li>`).join('');

        panel.innerHTML = `
            <strong>Koordinat</strong>
            <div style="font-family: monospace;">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
            <strong style="display: block; margin-top: 8px;">Alamat</strong>
            ${bagianAlamat}
            <strong style="display: block; margin-top: 8px;">Jangkauan ${RADIUS_KM} km</strong>
            <div>
                <span style="font-size: 20px; font-weight: bold;">${dalam.length}</span> sekolah
            </div>
            <small>${perJenjang}</small>
            <strong style="display: block; margin-top: 8px;">Sekolah terdekat</strong>
            <ul style="list-style: none; margin: 4px 0 0; padding: 0;">${daftar}</ul>`;
    }

    // Klik pada peta
    map.on('click', (e) => {
        if (!bolehMeminta()) return;
        periksa(e.latlng.lat, e.latlng.lng);
    });

    // Latihan 3: tombol Lokasi saya
    document.getElementById('btn-lokasi').addEventListener('click', () => {
        const pesan = document.getElementById('pesan-lokasi');

        if (!navigator.geolocation) {
            pesan.textContent = 'Browser tidak mendukung geolocation.';
            return;
        }

        pesan.textContent = 'Mencari lokasi...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude } = pos.coords;

                pesan.textContent = 'Lokasi ditemukan (akurasi sekitar '
                    + Math.round(pos.coords.accuracy) + ' m).';

                terakhir = Date.now(); // hitung sebagai satu permintaan Nominatim
                map.setView([latitude, longitude], 15);
                periksa(latitude, longitude);
            },
            (err) => {
                pesan.textContent = 'Gagal mengambil lokasi: ' + err.message;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Klik pada daftar sekolah terdekat: terbang ke sekolah tersebut
    panel.addEventListener('click', (e) => {
        const li = e.target.closest('[data-npsn]');
        if (!li) return;

        const f = semua.find((x) => x.properties.npsn === li.dataset.npsn);
        if (f) {
            L.popup().setLatLng(Peta.latlng(f)).setContent(Peta.popup(f)).openOn(map);
            map.flyTo(Peta.latlng(f), 17);
        }
    });
</script>
@endpush