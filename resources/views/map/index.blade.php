@extends('layouts.app')
@section('title', 'የዞን ካርታ (GIS)')
@section('content')
<h1>🗺 የየም ዞን የወረዳዎች አፈጻጸም ካርታ (GIS)</h1>
<p class="muted">
    ጊዜ፦ {{ $period->label() }} · ዓ/ም፦ {{ $year }}።
    በኤክስቴንሽን አፈጻጸም (5–10 አርሶ አደር/ቀን) ወይም በዕቅድ/ክንውን (%) ቀለም ይመልከቱ።
</p>

<form method="GET" class="card filters">
    <x-ethiopian-date-input name="from" id="map-from" label="ከ" :value="$period->from->toDateString()" />
    <x-ethiopian-date-input name="to" id="map-to" label="እስከ" :value="$period->to->toDateString()" />
    <div>
        <label for="map-year">የዕቅድ ዓ/ም</label>
        <select id="map-year" name="year">
            @foreach($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="map-mode">የካርታ መለኪያ</label>
        <select id="map-mode" name="mode">
            <option value="extension" @selected($mode === 'extension')>ዕለታዊ ኤክስቴንሽን (5–10/ቀን)</option>
            <option value="matrix" @selected($mode === 'matrix')>ሳምንታዊ ዕቅድ/ክንውን (%)</option>
        </select>
    </div>
    <div><button class="btn" type="submit">አሳይ</button></div>
</form>

<div class="grid cols-2">
    <div class="card">
        <div id="yem-leaflet-map" style="height:400px;border-radius:8px;border:1px solid var(--line);background:#eef3ee;position:relative"></div>
        <div id="yem-svg-fallback" style="display:none"></div>
        <div class="actions" style="margin-top:.6rem">
            @if($mode === 'extension')
                <span class="badge red">&lt;5 ዝቅተኛ</span>
                <span class="badge amber">5–7 ተቀባይነት ያለው</span>
                <span class="badge green">8–10 ግብ</span>
                <span class="badge blue">&gt;10 ማረጋገጥ</span>
                <span class="badge gray">መረጃ የለም</span>
            @else
                <span class="badge red">&lt;50%</span>
                <span class="badge amber">50–79%</span>
                <span class="badge green">80–100%</span>
                <span class="badge blue">&gt;100%</span>
                <span class="badge gray">መረጃ የለም</span>
            @endif
        </div>
        <p class="muted" style="font-size:.82rem;margin:.5rem 0 0">
            <strong>ማሳሰቢያ (TODO)፦</strong> የዞኑ ውጫዊ ወሰን እውነተኛ ነው (geoBoundaries / Open Africa, ODbL፤ "Yem SP Woreda")፤ ነገር ግን የአራቱ ወረዳዎች ውስጣዊ ክፍፍል <strong>ናሙና (schematic)</strong> ነው፤ ምክንያቱም የፎፋ፣ ቶባ እና ሌሎቹ ወረዳዎች ወቅታዊ ወሰን በይፋ አልተለቀቀም።
            ትክክለኛ ወረዳ ወሰን (CSA/OCHA ወይም የዞኑ GIS) ወደ <code>public/geo/yem-woredas.geojson</code> ሲገባ (<code>properties.code</code> ሳይቀየር) ካርታው በቀጥታ ይዘምናል።
        </p>
    </div>

    <div class="card table-wrap">
        <h2 style="margin-top:0">የወረዳዎች ማጠቃለያ</h2>
        <table>
            <thead>
            <tr>
                <th>ወረዳ</th>
                <th class="num">ቀበሌ</th>
                <th class="num">ባለሙያ</th>
                <th class="num">ሪፖርት</th>
                <th class="num">አርሶ አደር</th>
                <th class="num">አማካይ/ቀን</th>
                <th class="num">ዕቅድ %</th>
            </tr>
            </thead>
            <tbody>
            @forelse($metrics as $m)
                <tr>
                    <td><a href="{{ $m['dashboard_url'] }}"><strong>{{ $m['name_am'] }}</strong></a></td>
                    <td class="num">{{ $m['kebeles'] }}</td>
                    <td class="num">{{ $m['experts'] }}</td>
                    <td class="num">{{ $m['reports'] }}</td>
                    <td class="num">{{ number_format($m['farmers']) }}</td>
                    <td class="num"><span class="badge" style="background:{{ $m['band_color'] }};color:#fff">{{ $m['avg'] ?? '—' }}</span></td>
                    <td class="num"><span class="badge" style="background:{{ $m['matrix_color'] }};color:#fff">{{ $m['matrix_pct'] !== null ? $m['matrix_pct'].'%' : '—' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">ምንም ወረዳ የለም።</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const metrics = @json($metrics);
    const mode = @json($mode);

    function colorFor(code) {
        const m = metrics[code];
        if (!m) return '#d7ded7';
        return mode === 'matrix' ? m.matrix_color : m.band_color;
    }

    function popupHtml(code, fallbackName) {
        const m = metrics[code];
        if (!m) return `<strong>${fallbackName}</strong><br>መረጃ የተከለለ ወይም የለም`;
        return `<strong>${m.name_am}</strong><br>ሪፖርቶች: ${m.reports}<br>አርሶ አደር: ${m.farmers}<br>አማካይ/ቀን: ${m.avg ?? '—'} (${m.band_label})<br>ዕቅድ አፈጻጸም: ${m.matrix_pct !== null ? m.matrix_pct + '%' : '—'}<br><a href="${m.dashboard_url}">ዳሽቦርድ ክፈት</a>`;
    }

    function renderSvgFallback(geo) {
        const mapEl = document.getElementById('yem-leaflet-map');
        const svgEl = document.getElementById('yem-svg-fallback');
        mapEl.style.display = 'none';
        svgEl.style.display = 'block';

        const pts = [];
        geo.features.forEach(f => f.geometry.coordinates[0].forEach(c => pts.push(c)));
        const minX = Math.min(...pts.map(p => p[0])), maxX = Math.max(...pts.map(p => p[0]));
        const minY = Math.min(...pts.map(p => p[1])), maxY = Math.max(...pts.map(p => p[1]));
        const w = 460, h = 340, pad = 20;
        const sx = (x) => pad + ((x - minX) / (maxX - minX || 1)) * (w - 2 * pad);
        const sy = (y) => h - pad - ((y - minY) / (maxY - minY || 1)) * (h - 2 * pad);

        let paths = '';
        geo.features.forEach((f) => {
            const code = f.properties.code;
            const ring = f.geometry.coordinates[0];
            const d = ring.map((c, i) => `${i === 0 ? 'M' : 'L'}${sx(c[0]).toFixed(1)},${sy(c[1]).toFixed(1)}`).join(' ') + ' Z';
            const cx = ring.reduce((s, c) => s + sx(c[0]), 0) / ring.length;
            const cy = ring.reduce((s, c) => s + sy(c[1]), 0) / ring.length;
            const m = metrics[code];
            const label = m ? `${f.properties.name_am} (${mode === 'matrix' ? (m.matrix_pct !== null ? m.matrix_pct + '%' : '—') : (m.avg ?? '—')})` : f.properties.name_am;
            paths += `<path d="${d}" fill="${colorFor(code)}" fill-opacity="0.78" stroke="#1c2a22" stroke-width="1.5"><title>${label}</title></path>`;
            paths += `<text x="${cx.toFixed(1)}" y="${cy.toFixed(1)}" text-anchor="middle" font-size="11" font-weight="700" fill="#1c2a22">${f.properties.name_am}</text>`;
        });

        svgEl.innerHTML = `<svg viewBox="0 0 ${w} ${h}" style="width:100%;height:auto;border:1px solid var(--line);border-radius:8px;background:#eef3ee">${paths}</svg>`;
    }

    fetch('/geo/yem-woredas.geojson')
        .then((r) => r.json())
        .then((geo) => {
            if (typeof window.L === 'undefined') {
                renderSvgFallback(geo);
                return;
            }
            const map = L.map('yem-leaflet-map').setView([7.83, 37.49], 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap · ወሰን: geoBoundaries (ODbL)'
            }).addTo(map);
            const layer = L.geoJSON(geo, {
                style: (f) => ({
                    color: '#1c2a22',
                    weight: 1.5,
                    fillColor: colorFor(f.properties.code),
                    fillOpacity: metrics[f.properties.code] ? 0.65 : 0.2,
                }),
                onEachFeature: (f, l) => {
                    l.bindPopup(popupHtml(f.properties.code, f.properties.name_am));
                    l.bindTooltip(f.properties.name_am, { permanent: true, direction: 'center' });
                }
            }).addTo(map);
            map.fitBounds(layer.getBounds(), { padding: [15, 15] });
        })
        .catch(() => {});
})();
</script>
@endsection
