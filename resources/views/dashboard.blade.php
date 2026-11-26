@extends('layouts.app')
@section('title', 'ዳሽቦርድ')
@section('content')
@php
    $user = auth()->user();
    $scopeLabel = $user->isZone() ? 'የዞን' : ($user->isWoreda() ? 'የወረዳ' : 'የቀበሌ');
    $queueLabel = $user->isWoreda() ? 'ለማረጋገጥ የሚጠብቁ' : ($user->isZone() ? 'ለማጽደቅ የሚጠብቁ' : 'የተመለሱ (ማስተካከል ያስፈልጋል)');
@endphp
<h1>{{ $scopeLabel }} ዳሽቦርድ</h1>
<p class="muted">ጊዜ፦ {{ $period->label() }}</p>

@include('partials.period-filter', ['woredas' => $woredas])

@if($user->isKebele())
    <div class="card">
        @if($todayReport)
            <strong>የዛሬ ሪፖርት፦</strong>
            <span class="badge {{ $todayReport->status->badge() }}">{{ $todayReport->status->label() }}</span>
            <a href="{{ route('reports.show', $todayReport) }}">ክፈት</a>
        @else
            <strong>የዛሬ ሪፖርት ገና አልተፈጠረም።</strong>
            <a class="btn sm" href="{{ route('reports.create') }}">+ ዕለታዊ ሪፖርት ፍጠር</a>
        @endif
    </div>
@endif

<div class="grid cols-4">
    <div class="card stat"><div class="n">{{ $totalReports }}</div><div class="l">የተላኩ ሪፖርቶች</div></div>
    <div class="card stat"><div class="n">{{ number_format($totalFarmers) }}</div><div class="l">የተገለገሉ አርሶ አደሮች</div></div>
    <div class="card stat"><div class="n">{{ $queueCount }}</div><div class="l">{{ $queueLabel }}</div></div>
    @unless($user->isKebele())
        <div class="card stat"><div class="n">{{ $missing->count() }}</div><div class="l">{{ et_date($lastWorkDay) }} ሪፖርት ያልላኩ</div></div>
    @endunless
</div>

<div class="card">
    <strong>የአፈጻጸም ባንድ (በቀን አርሶ አደር ብዛት)፦</strong>
    <span class="badge red">ከ{{ config('extension.low_below') }} በታች — ማስጠንቀቂያ</span>
    <span class="badge amber">{{ config('extension.low_below') }}–{{ config('extension.target_from') - 1 }} ተቀባይነት ያለው</span>
    <span class="badge green">{{ config('extension.target_from') }}–{{ config('extension.target_to') }} ግብ ላይ ደርሷል</span>
    <span class="badge blue">ከ{{ config('extension.target_to') }} በላይ — ማረጋገጥ ይገባል</span>
    <div class="muted" style="margin-top:.4rem">ሳምንታዊ/ወርሃዊ እይታ፦ ጠቅላላ አርሶ አደር ÷ ሪፖርት የተላከባቸው ቀናት።</div>
</div>

@if($queueItems->isNotEmpty())
<h2>{{ $queueLabel }} ሪፖርቶች</h2>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ቀን</th><th>ቀበሌ</th><th>ባለሙያ</th><th class="num">አርሶ አደር</th><th></th></tr></thead>
        <tbody>
        @foreach($queueItems as $r)
            <tr>
                <td>{{ et_date($r->report_date) }}</td>
                <td>{{ $r->location?->name_am }} <span class="muted">({{ $r->location?->parent?->name_am }})</span></td>
                <td>{{ $r->user?->name }}</td>
                <td class="num"><span class="badge {{ $r->band()->badge() }}">{{ $r->farmers_total }}</span></td>
                <td><a class="btn sm" href="{{ route('reports.show', $r) }}">ክፈት</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if($queueCount > $queueItems->count())<p class="muted">… እና ሌሎች {{ $queueCount - $queueItems->count() }}። <a href="{{ route('reports.index', ['status' => $user->isWoreda() ? 'submitted' : ($user->isZone() ? 'woreda_verified' : 'returned'), 'range' => 'last30']) }}">ሁሉንም ተመልከት</a></p>@endif
</div>
@endif

@unless($user->isKebele())
    <h2>⚠ ማስጠንቀቂያዎች</h2>
    <div class="grid cols-2">
        <div class="card">
            <strong>የአፈጻጸም ማስጠንቀቂያ (ዝቅተኛ / ከመጠን በላይ)</strong>
            @forelse($alerts as $a)
                <div style="margin-top:.4rem">
                    <span class="badge {{ $a['band']->badge() }}">{{ $a['band']->label() }}</span>
                    {{ $a['user']->name }} · {{ $a['kebele']?->name_am }} ({{ $a['woreda']?->name_am }}) — አማካይ <strong>{{ $a['avg'] }}</strong>/ቀን
                </div>
            @empty
                <p class="muted">በዚህ ጊዜ ምንም ማስጠንቀቂያ የለም።</p>
            @endforelse
        </div>
        <div class="card">
            <strong>ባለፈው የሥራ ቀን ({{ et_date($lastWorkDay) }}) ሪፖርት ያልላኩ</strong>
            @forelse($missing as $m)
                <div style="margin-top:.4rem"><span class="badge gray">ያልተላከ</span> {{ $m->name }} · {{ $m->location?->name_am }} ({{ $m->location?->parent?->name_am }})</div>
            @empty
                <p class="muted">ሁሉም ባለሙያዎች ሪፖርት ልከዋል።</p>
            @endforelse
        </div>
    </div>
@endunless

@if($weekly)
    <h2>ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ</h2>
    <div class="grid cols-2">
        <div class="card">
            <strong>{{ $user->isZone() ? 'ለማጽደቅ የሚጠብቁ የወረዳ ሳምንታዊ ሪፖርቶች' : 'የተመለሱ ሳምንታዊ ሪፖርቶች' }}፦ {{ $weekly['pending'] }}</strong>
            <div class="actions"><a class="btn sm" href="{{ route('weekly.index', ['status' => $user->isZone() ? 'submitted' : 'returned']) }}">ክፈት</a>
                <a class="btn sm secondary" href="{{ route('matrix.index') }}">ማትሪክስ</a></div>
        </div>
        <div class="card">
            <strong>ባለፈው ሳምንት ({{ et_date($weekly['lastWeek']) }}) ሳምንታዊ ክንውን ያልላኩ ወረዳዎች</strong>
            @forelse($weekly['missing'] as $w)
                <div style="margin-top:.4rem"><span class="badge gray">ያልተላከ</span> {{ $w->name_am }}</div>
            @empty
                <p class="muted">ሁሉም ወረዳዎች ልከዋል።</p>
            @endforelse
        </div>
    </div>
@endif

@if($woredaRows->isNotEmpty())
    <h2>የወረዳዎች አፈጻጸም</h2>
    <div class="card table-wrap">
        <table>
            <thead><tr><th>ወረዳ</th><th class="num">ባለሙያዎች</th><th class="num">ሪፖርት የተላከባቸው ቀናት</th><th class="num">ያልተላኩ ቀናት</th><th class="num">ጠቅላላ አርሶ አደር</th><th class="num">አማካይ/ቀን</th><th>ባንድ</th><th class="num">ዝቅተኛ ባለሙያዎች</th></tr></thead>
            <tbody>
            @foreach($woredaRows as $w)
                <tr>
                    <td><a href="{{ route('dashboard', ['woreda' => $w['woreda']?->id] + $period->query()) }}">{{ $w['woreda']?->name_am }}</a></td>
                    <td class="num">{{ $w['experts'] }}</td>
                    <td class="num">{{ $w['days_reported'] }}</td>
                    <td class="num">{{ $w['missing_days'] }}</td>
                    <td class="num">{{ number_format($w['farmers']) }}</td>
                    <td class="num">{{ $w['avg'] ?? '—' }}</td>
                    <td><span class="badge {{ $w['band']->badge() }}">{{ $w['band']->label() }}</span></td>
                    <td class="num">{{ $w['low_experts'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2>{{ $user->isKebele() ? 'የእኔ አፈጻጸም' : 'የባለሙያዎች አፈጻጸም' }}</h2>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ባለሙያ</th><th>ቀበሌ</th><th>ወረዳ</th><th class="num">የተላኩ ቀናት / የሚጠበቁ</th><th class="num">ጠቅላላ አርሶ አደር</th><th class="num">ሴት</th><th class="num">ወጣት</th><th class="num">አማካይ/ቀን</th><th>ባንድ</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ $r['user']->name }}</td>
                <td>{{ $r['kebele']?->name_am }}</td>
                <td>{{ $r['woreda']?->name_am }}</td>
                <td class="num">{{ $r['days_reported'] }} / {{ $r['expected_days'] }}</td>
                <td class="num">{{ number_format($r['farmers']) }}</td>
                <td class="num">{{ $r['female'] }}</td>
                <td class="num">{{ $r['youth'] }}</td>
                <td class="num">{{ $r['avg'] ?? '—' }}</td>
                <td><span class="badge {{ $r['band']->badge() }}">{{ $r['band']->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">ምንም ባለሙያ አልተገኘም።</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="actions">
    <a class="btn secondary" href="{{ route('exports.performance', request()->query()) }}">⬇ አፈጻጸም Excel</a>
    <a class="btn secondary" href="{{ route('exports.reports', request()->query()) }}">⬇ ሪፖርቶች Excel</a>
</div>
@endsection
