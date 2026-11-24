@php
    $teks = [
        'npsn' => 'NPSN (8 digit)',
        'nama' => 'Nama Sekolah',
        'kecamatan' => 'Kecamatan',
        'telepon' => 'Telepon',
    ];
    $pilihan = [
        'jenjang' => ['SD', 'SMP', 'SMA', 'SMK'],
        'status' => ['Negeri', 'Swasta'],
    ];
    $cls = 'w-full border rounded px-3 py-2';
@endphp

<div class="bg-white rounded shadow p-6 grid md:grid-cols-2 gap-6">
    <div class="space-y-4">
        @foreach ($teks as $name => $label)
            <div>
                <label for="{{ $name }}" class="block font-medium">
                    {{ $label }}
                </label>
                <input type="text" id="{{ $name }}" name="{{ $name }}"
                        value="{{ old($name, $sekolah->$name) }}"
                        @readonly($name === 'npsn' && auth()->user()->isSekolah())
                        class="{{ $cls }}">
                @error($name)
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        @foreach ($pilihan as $name => $opsi)
            <div>
                <label for="{{ $name }}" class="block font-medium">
                    {{ ucfirst($name) }}
                </label>
                <select id="{{ $name }}" name="{{ $name }}"
                        class="{{ $cls }}">
                    <option value="">-- Pilih --</option>
                    @foreach ($opsi as $o)
                        <option value="{{ $o }}"
                            @selected(old($name, $sekolah->$name) === $o)>
                            {{ $o }}
                        </option>
                    @endforeach
                </select>
                @error($name)
                <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

            <div>
                <label for="alamat" class="block font-medium">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3"
                        class="{{ $cls }}">{{ old('alamat', $sekolah->alamat) }}</textarea>
                <button type="button" id="btn-isi-alamat"
                        class="mt-2 px-3 py-1 bg-gray-700 text-white rounded text-sm">
                    Isi alamat dari peta
                </button>
                <p id="pesan-alamat" class="text-sm text-gray-600 mt-1"></p>
                @error('alamat')
                <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="space-y-4">
        <p class="font-medium">
            Klik peta atau geser marker untuk menentukan lokasi sekolah
            @if (auth()->user()->isSekolah())
            <p class="text-sm text-gray-600">
                Lokasi hanya dapat digeser maksimal
                {{ config('sig.maks_geser_meter') }} m dari titik semula.
            </p>
        @endif
        </p>
        <div id="map-picker" class="h-80 rounded border"></div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="latitude" class="block font-medium">Latitude</label>
                <input type="number" step="any" id="latitude" name="latitude"
                        value="{{ old('latitude', $sekolah->latitude) }}"
                        class="{{ $cls }}">
                @error('latitude')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="longitude" class="block font-medium">Longitude</label>
                <input type="number" step="any" id="longitude" name="longitude"
                        value="{{ old('longitude', $sekolah->longitude) }}"
                        class="{{ $cls }}">
                @error('longitude')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <button type="button" id="btn-lokasi-saya"
                    class="px-4 py-2 bg-gray-700 text-white rounded">
                Gunakan lokasi saya
            </button>
            <p id="pesan-lokasi" class="text-sm text-gray-600 mt-1"></p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-5 py-2 bg-blue-700 text-white rounded">
                Simpan
            </button>
            <a href="{{ auth()->user()->isDinas()
                    ? route('dinas.sekolah.index')
                    : route('dashboard') }}"
                class="px-5 py-2 bg-gray-300 rounded">Batal</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const sudahAda = latInput.value !== '' && lngInput.value !== '';
    const pusat = sudahAda
        ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
        : [-3.3194, 114.5908]; // pusat Kota Banjarmasin

    const map = L.map('map-picker').setView(pusat, sudahAda ? 16 : 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const marker = L.marker(pusat, { draggable: true }).addTo(map);

    function isiKoordinat(lat, lng) {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
    }

    map.on('click', (e) => {
        marker.setLatLng(e.latlng);
        isiKoordinat(e.latlng.lat, e.latlng.lng);
    });

    marker.on('dragend', () => {
        const p = marker.getLatLng();
        isiKoordinat(p.lat, p.lng);
    });

    [latInput, lngInput].forEach((el) => {
        el.addEventListener('change', () => {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);
            }
        });
    });

    document.getElementById('btn-lokasi-saya').addEventListener('click', () => {
        const pesan = document.getElementById('pesan-lokasi');

        if (!navigator.geolocation) {
            pesan.textContent = 'Browser tidak mendukung geolocation.';
            return;
        }

        pesan.textContent = 'Mencari lokasi...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude } = pos.coords;
                marker.setLatLng([latitude, longitude]);
                map.setView([latitude, longitude], 17);
                isiKoordinat(latitude, longitude);
                pesan.textContent = 'Lokasi ditemukan (akurasi sekitar '
                    + Math.round(pos.coords.accuracy) + ' m).';
            },
            (err) => {
                pesan.textContent = 'Gagal mengambil lokasi: ' + err.message;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Latihan mandiri 6: isi alamat dari koordinat marker (reverse geocoding Nominatim)
    const tombolAlamat = document.getElementById('btn-isi-alamat');
    const pesanAlamat = document.getElementById('pesan-alamat');
    const alamatInput = document.getElementById('alamat');
    let terakhirAlamat = 0;

    tombolAlamat.addEventListener('click', async () => {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        if (isNaN(lat) || isNaN(lng)) {
            pesanAlamat.textContent = 'Tentukan titik marker pada peta terlebih dahulu.';
            return;
        }
                // Kebijakan Nominatim: maksimal 1 permintaan per detik
        if (Date.now() - terakhirAlamat < 1200) {
            pesanAlamat.textContent = 'Tunggu sebentar sebelum mencoba lagi.';
            return;
        }
        if (alamatInput.value.trim() !== '' &&
            !confirm('Alamat sudah terisi. Timpa dengan alamat dari peta?')) {
            return;
        }
        terakhirAlamat = Date.now();

        tombolAlamat.disabled = true;
        pesanAlamat.textContent = 'Mencari alamat...';

        try {
            const url = 'https://nominatim.openstreetmap.org/reverse'
                + '?format=jsonv2&addressdetails=1&accept-language=id'
                + `&lat=${lat}&lon=${lng}`;
            const respon = await fetch(url);
            if (!respon.ok) throw new Error('HTTP ' + respon.status);

            const data = await respon.json();
            if (data.error) throw new Error(data.error);

            const a = data.address || {};
            const ringkas = [
                a.road,
                a.suburb || a.neighbourhood || a.village,
                a.city || a.town || a.municipality || a.county,
            ].filter(Boolean).join(', ');

            alamatInput.value = ringkas || data.display_name || '';
            pesanAlamat.textContent = 'Alamat terisi. Periksa dan sunting bila perlu.';
        } catch (err) {
            console.error(err);
            pesanAlamat.textContent = 'Alamat tidak dapat dimuat. Isi manual.';
        } finally {
            tombolAlamat.disabled = false;
        }
    });
</script>
@endpush