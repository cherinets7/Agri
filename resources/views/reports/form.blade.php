@extends('layouts.app')
@section('title', $report->exists ? 'ሪፖርት አስተካክል' : 'አዲስ ዕለታዊ ሪፖርት')
@section('content')
@php
    $services = config('extension.service_types');
    $techniques = config('extension.techniques');
    $selectedServices = old('service_types', $report->service_types ?? []);
    $selectedTechniques = old('techniques', $report->techniques ?? []);
    $user = auth()->user();
@endphp
<h1>{{ $report->exists ? 'ዕለታዊ ሪፖርት አስተካክል' : 'አዲስ ዕለታዊ የኤክስቴንሽን ሪፖርት' }}</h1>

@if($report->exists && $report->status === App\Enums\ReportStatus::Returned)
    <div class="errors"><strong>ሪፖርቱ ተመልሷል።</strong> ምክንያት፦ {{ $report->return_reason }}</div>
@endif

<form method="POST" action="{{ $report->exists ? route('reports.update', $report) : route('reports.store') }}" class="card" data-offline-draft>
    @csrf
    @if($report->exists) @method('PUT') @endif

    <div class="row">
        <div>
            <label>ቀበሌ</label>
            <input type="text" value="{{ ($report->location ?? $user->location)?->name_am }}" disabled>
        </div>
        <div>
            <label>ወረዳ</label>
            <input type="text" value="{{ ($report->location ?? $user->location)?->parent?->name_am }}" disabled>
        </div>
        <div>
            <label>ሪፖርት አድራጊ</label>
            <input type="text" value="{{ $report->user?->name ?? $user->name }}" disabled>
        </div>
        <x-ethiopian-date-input name="report_date" id="report_date" label="ቀን" :max="today()->toDateString()" :value="old('report_date', $report->report_date?->toDateString())" :required="true" />
    </div>

    <h2>የተገለገሉ አርሶ አደሮች</h2>
    <div class="row">
        <div>
            <label for="farmers_total">ጠቅላላ የተገለገሉ አርሶ አደሮች</label>
            <input id="farmers_total" type="number" min="0" name="farmers_total" value="{{ old('farmers_total', $report->farmers_total ?? 0) }}" required>
            <span class="muted">ግብ፦ በቀን {{ config('extension.low_below') }}–{{ config('extension.target_to') }}</span>
            <div id="band-hint"></div>
        </div>
        <div>
            <label for="new_farmers">አዲስ</label>
            <input id="new_farmers" type="number" min="0" name="new_farmers" value="{{ old('new_farmers', $report->new_farmers ?? 0) }}" required>
        </div>
        <div>
            <label for="repeat_farmers">ተደጋጋሚ</label>
            <input id="repeat_farmers" type="number" min="0" name="repeat_farmers" value="{{ old('repeat_farmers', $report->repeat_farmers ?? 0) }}" required>
        </div>
        <div>
            <label for="female_farmers">ከነሱ ሴት</label>
            <input id="female_farmers" type="number" min="0" name="female_farmers" value="{{ old('female_farmers', $report->female_farmers ?? 0) }}" required>
        </div>
        <div>
            <label for="youth_farmers">ከነሱ ወጣት (18–35)</label>
            <input id="youth_farmers" type="number" min="0" name="youth_farmers" value="{{ old('youth_farmers', $report->youth_farmers ?? 0) }}" required>
        </div>
    </div>

    <label>የአገልግሎት ዓይነት</label>
    <div class="checks">
        @foreach($services as $key => $label)
            <label><input type="checkbox" name="service_types[]" value="{{ $key }}" @checked(in_array($key, $selectedServices))> {{ $label }}</label>
        @endforeach
    </div>

    <label>ቴክኒክ / የአገልግሎት መንገድ</label>
    <div class="checks">
        @foreach($techniques as $key => $label)
            <label><input type="checkbox" name="techniques[]" value="{{ $key }}" @checked(in_array($key, $selectedTechniques))> {{ $label }}</label>
        @endforeach
    </div>

    <div class="row">
        <div>
            <label for="duration_minutes">ጠቅላላ ቆይታ (ደቂቃ)</label>
            <input id="duration_minutes" type="number" min="0" max="1440" name="duration_minutes" value="{{ old('duration_minutes', $report->duration_minutes ?? 0) }}" required>
        </div>
    </div>

    <label for="notes">ማስታወሻ</label>
    <textarea id="notes" name="notes">{{ old('notes', $report->notes) }}</textarea>

    <x-signature-pad name="signature_data" label="የባለሙያ ዲጂታል ፊርማ (አማራጭ)" :existing="$report->signature_data" />

    <p class="muted">«ላክ» ሲጫኑ መረጃው ትክክል መሆኑን እንዳረጋገጡ ይመዘገባል (ዲጂታል ማረጋገጫ)።</p>
    <div class="actions">
        <button class="btn secondary" type="submit" name="action" value="save">ረቂቅ አስቀምጥ</button>
        <button class="btn" type="submit" name="action" value="submit" onclick="return confirm('መረጃው ትክክል መሆኑን አረጋግጠዋል? ለወረዳ ይላካል።')">አረጋግጥና ላክ</button>
        <a href="{{ route('reports.index') }}">ተመለስ</a>
    </div>
</form>

<script>
(function () {
    const low = {{ (float) config('extension.low_below') }}, from = {{ (float) config('extension.target_from') }}, to = {{ (float) config('extension.target_to') }};
    const total = document.getElementById('farmers_total'), hint = document.getElementById('band-hint');
    const nw = document.getElementById('new_farmers'), rep = document.getElementById('repeat_farmers');
    function band() {
        const v = Number(total.value || 0);
        let cls = 'green', txt = 'ግብ ላይ ደርሷል';
        if (v < low) { cls = 'red'; txt = 'ዝቅተኛ — ማስጠንቀቂያ'; }
        else if (v < from) { cls = 'amber'; txt = 'ተቀባይነት ያለው'; }
        else if (v > to) { cls = 'blue'; txt = 'ከመጠን በላይ — ወረዳ ያረጋግጣል'; }
        hint.innerHTML = '<span class="badge ' + cls + '">' + txt + '</span>';
    }
    total.addEventListener('input', band); band();
    let touched = false;
    rep.addEventListener('input', () => touched = true);
    nw.addEventListener('input', () => { if (!touched) rep.value = Math.max(0, Number(total.value || 0) - Number(nw.value || 0)); });
})();
</script>
@endsection
