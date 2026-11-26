@extends('layouts.app')
@section('title', 'ወር / ሩብ / ዓመት ንጽጽር')
@section('content')
<h1>የወር / ሩብ ዓመት / ዓመት ንጽጽር ዳሽቦርድ</h1>
<p class="muted">{{ $currentLabel }} ከ {{ $previousLabel }} ጋር ሲነጻጸር</p>

<form method="GET" class="card filters">
    <div>
        <label for="mode">የንጽጽር ዓይነት</label>
        <select id="mode" name="mode" onchange="this.form.submit()">
            <option value="month" @selected($mode === 'month')>ወርሃዊ ንጽጽር</option>
            <option value="quarter" @selected($mode === 'quarter')>የሩብ ዓመት ንጽጽር</option>
            <option value="year" @selected($mode === 'year')>ዓመታዊ ንጽጽር</option>
        </select>
    </div>
    <div>
        <label for="year">በጀት ዓመት (ዓ/ም)</label>
        <select id="year" name="year">
            @foreach($years as $y)<option value="{{ $y }}" @selected($fiscalYear === $y)>{{ $y }} ዓ/ም</option>@endforeach
        </select>
    </div>
    @if($mode === 'month')
        <div>
            <label for="month">ወር</label>
            <select id="month" name="month">
                @foreach($months as $mNum => $mName)<option value="{{ $mNum }}" @selected($month === $mNum)>{{ $mName }}</option>@endforeach
            </select>
        </div>
    @elseif($mode === 'quarter')
        <div>
            <label for="quarter">ሩብ ዓመት</label>
            <select id="quarter" name="quarter">
                <option value="1" @selected($quarter === 1)>1ኛ ሩብ (ሐምሌ–መስከረም)</option>
                <option value="2" @selected($quarter === 2)>2ኛ ሩብ (ጥቅምት–ታህሳስ)</option>
                <option value="3" @selected($quarter === 3)>3ኛ ሩብ (ጥር–መጋቢት)</option>
                <option value="4" @selected($quarter === 4)>4ኛ ሩብ (ሚያዚያ–ሰኔ)</option>
            </select>
        </div>
    @endif
    @if($woredas->isNotEmpty())
        <div>
            <label for="woreda">ወረዳ</label>
            <select id="woreda" name="woreda">
                <option value="">ሁሉም ወረዳዎች</option>
                @foreach($woredas as $w)<option value="{{ $w->id }}" @selected($woredaId === $w->id)>{{ $w->name_am }}</option>@endforeach
            </select>
        </div>
    @endif
    <div><button class="btn" type="submit">አወዳድር</button></div>
</form>

@php
    $delta = function ($cur, $prev) {
        if ($prev == 0) return $cur > 0 ? '+100%' : '0%';
        $pct = round((($cur - $prev) / $prev) * 100, 1);
        return ($pct > 0 ? '+' : '').$pct.'%';
    };
    $metrics = [
        ['label' => 'የተገለገሉ አርሶ አደሮች', 'cur' => $current['farmers'], 'prev' => $previous['farmers']],
        ['label' => 'የተላኩ ዕለታዊ ሪፖርቶች', 'cur' => $current['reports'], 'prev' => $previous['reports']],
        ['label' => 'በሰብል የተሸፈነ መሬት (ሄ/ር)', 'cur' => $current['crop_ha'], 'prev' => $previous['crop_ha']],
        ['label' => 'የተከፋፈለ ማዳበሪያ (ኩ/ል)', 'cur' => $current['fertilizer_quintal'], 'prev' => $previous['fertilizer_quintal']],
        ['label' => 'የቀረበ ምርጥ ዘር (ኩ/ል)', 'cur' => $current['seed_quintal'], 'prev' => $previous['seed_quintal']],
        ['label' => 'የሰለጠኑ አርሶ አደሮች', 'cur' => $current['trained_farmers'], 'prev' => $previous['trained_farmers']],
    ];
@endphp

<div class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>አመልካች</th>
            <th class="num">{{ $currentLabel }}</th>
            <th class="num">{{ $previousLabel }}</th>
            <th class="num">ለውጥ</th>
            <th>ንጽጽር</th>
        </tr>
        </thead>
        <tbody>
        @foreach($metrics as $m)
            @php($max = max(1, (float) $m['cur'], (float) $m['prev']))
            <tr>
                <td><strong>{{ $m['label'] }}</strong></td>
                <td class="num">{{ qty($m['cur']) }}</td>
                <td class="num muted">{{ qty($m['prev']) }}</td>
                <td class="num"><span class="badge {{ $m['cur'] >= $m['prev'] ? 'green' : 'amber' }}">{{ $delta($m['cur'], $m['prev']) }}</span></td>
                <td>
                    <div class="bar-track"><div class="bar-fill" style="width:{{ round(($m['cur'] / $max) * 100) }}%"></div></div>
                    <div class="bar-track" style="margin-top:3px"><div class="bar-fill prev" style="width:{{ round(($m['prev'] / $max) * 100) }}%"></div></div>
                </td>
            </tr>
        @endforeach
        <tr>
            <td><strong>የአርሶ አደር አስተያየት (ከ5)</strong></td>
            <td class="num">{{ $current['feedback_avg'] ? "{$current['feedback_avg']} ({$current['feedback_count']})" : '—' }}</td>
            <td class="num muted">{{ $previous['feedback_avg'] ? "{$previous['feedback_avg']} ({$previous['feedback_count']})" : '—' }}</td>
            <td class="num">—</td>
            <td>—</td>
        </tr>
        @if($planSummary)
            <tr>
                <td><strong>ሳምንታዊ ማትሪክስ ከዕቅድ ጋር (አማካይ %)</strong></td>
                <td class="num">{{ $planSummary['avg_pct'] !== null ? $planSummary['avg_pct'].'%' : '—' }}</td>
                <td class="num muted">{{ ($prevPlanSummary && $prevPlanSummary['avg_pct'] !== null) ? $prevPlanSummary['avg_pct'].'%' : '—' }}</td>
                <td class="num">—</td>
                <td>
                    @if($planSummary['avg_pct'] !== null)
                        <div class="bar-track"><div class="bar-fill" style="width:{{ min(100, round($planSummary['avg_pct'])) }}%"></div></div>
                    @endif
                </td>
            </tr>
        @endif
        </tbody>
    </table>
</div>

@if($woredaRows->isNotEmpty())
    <h2>በወረዳ ንጽጽር ({{ $currentLabel }} vs {{ $previousLabel }})</h2>
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ወረዳ</th>
                <th class="num">አርሶ አደር (አሁን)</th>
                <th class="num">አርሶ አደር (ቀደም)</th>
                <th class="num">ማዳበሪያ (አሁን)</th>
                <th class="num">ዘር (አሁን)</th>
                <th class="num">አስተያየት (ከ5)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($woredaRows as $wr)
                <tr>
                    <td>{{ $wr['woreda']->name_am }}</td>
                    <td class="num"><strong>{{ qty($wr['current']['farmers']) }}</strong></td>
                    <td class="num muted">{{ qty($wr['previous']['farmers']) }}</td>
                    <td class="num">{{ qty($wr['current']['fertilizer_quintal']) }}</td>
                    <td class="num">{{ qty($wr['current']['seed_quintal']) }}</td>
                    <td class="num">{{ $wr['current']['feedback_avg'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
