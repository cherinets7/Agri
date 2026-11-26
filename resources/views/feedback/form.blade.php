<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>የአርሶ አደር አስተያየት — {{ $expert->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main style="max-width:640px">
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="errors"><strong>እባክዎ ስህተቶቹን ያስተካክሉ፦</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <h1>🌾 የአርሶ አደር አስተያየት (1–5)</h1>
        <p class="muted">ባለሙያ፦ <strong>{{ $expert->name }}</strong> · {{ $expert->location?->name_am }} ({{ $expert->location?->parent?->name_am }})</p>

        <form method="POST" action="{{ $actionUrl }}" id="fb-form">
            @csrf

            <label for="rating">የአገልግሎት እርካታ ደረጃ (1 = ዝቅተኛ፣ 5 = በጣም ጥሩ)</label>
            <select id="rating" name="rating" required>
                <option value="5" @selected(old('rating', 5) == 5)>★★★★★ (5) — በጣም ጥሩ</option>
                <option value="4" @selected(old('rating') == 4)>★★★★☆ (4) — ጥሩ</option>
                <option value="3" @selected(old('rating') == 3)>★★★☆☆ (3) — መካከለኛ</option>
                <option value="2" @selected(old('rating') == 2)>★★☆☆☆ (2) — ደካማ</option>
                <option value="1" @selected(old('rating') == 1)>★☆☆☆☆ (1) — በጣም ዝቅተኛ</option>
            </select>

            <label for="service_type">የተገኘው አገልግሎት (አማራጭ)</label>
            <select id="service_type" name="service_type">
                <option value="">— ይምረጡ —</option>
                @foreach($services as $k => $label)
                    <option value="{{ $k }}" @selected(old('service_type') === $k)>{{ $label }}</option>
                @endforeach
            </select>

            <div class="row">
                <div>
                    <label for="farmer_name">የአርሶ አደር ስም (አማራጭ)</label>
                    <input id="farmer_name" type="text" name="farmer_name" value="{{ old('farmer_name') }}">
                </div>
                <div>
                    <label for="farmer_phone">ስልክ ቁጥር (አማራጭ)</label>
                    <input id="farmer_phone" type="text" name="farmer_phone" value="{{ old('farmer_phone') }}">
                </div>
            </div>

            <label for="comment">አስተያየት / ጥቆማ (አማራጭ)</label>
            <textarea id="comment" name="comment">{{ old('comment') }}</textarea>

            <label>የአርሶ አደር ፊርማ (በጣት ወይም በመዳፊት ይፈርሙ — አማራጭ)</label>
            <canvas id="fb-sig" class="sig-pad" width="460" height="140"></canvas>
            <input type="hidden" name="signature_data" id="fb-sig-data">
            <div class="actions" style="margin-top:.4rem">
                <button type="button" class="btn sm secondary" id="fb-sig-clear">ፊርማ አጽዳ</button>
            </div>

            <div class="actions">
                <button class="btn" type="submit">አስተያየቱን ላክ</button>
                @unless($publicMode)
                    <a href="{{ route('feedback.index') }}">← ወደ ዝርዝር</a>
                @endunless
            </div>
        </form>
    </div>
</main>
<script>
(function(){
    const canvas = document.getElementById('fb-sig');
    const hidden = document.getElementById('fb-sig-data');
    if (!canvas || !canvas.getContext) return;
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1c2a22';
    let drawing = false, dirty = false;
    function pos(e){
        const r = canvas.getBoundingClientRect();
        const p = e.touches ? e.touches[0] : e;
        return { x: (p.clientX - r.left) * (canvas.width / r.width), y: (p.clientY - r.top) * (canvas.height / r.height) };
    }
    function start(e){ drawing = true; dirty = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function move(e){ if(!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function stop(){ drawing = false; if (dirty) hidden.value = canvas.toDataURL('image/png'); }
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
    canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false}); window.addEventListener('touchend', stop);
    document.getElementById('fb-sig-clear').addEventListener('click', function(){
        ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; hidden.value = '';
    });
})();
</script>
</body>
</html>
