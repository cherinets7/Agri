@extends('layouts.app')
@section('title', "ዓመታዊ ሪፖርት {$fiscalYear}")
@section('content')
<h1>ዓመታዊ የግብርና ክትትል ሪፖርት — {{ $fiscalYear }} ዓ/ም</h1>
<p class="muted">ጊዜ፦ {{ et_date($from) }} – {{ et_date($to) }}</p>

<form method="GET" class="card filters">
    <div>
        <label for="year">በጀት ዓመት</label>
        <select id="year" name="year">
            @foreach($years as $y)<option value="{{ $y }}" @selected($fiscalYear === $y)>{{ $y }} ዓ/ም</option>@endforeach
        </select>
    </div>
    @if($woredas->isNotEmpty())
        <div>
            <label for="woreda">ወረዳ</label>
            <select id="woreda" name="woreda">
                <option value="">ሁሉም ወረዳዎች</option>
                @foreach($woredas as $w)<option value="{{ $w->id }}" @selected($woredaId === $w->id)>{{ $w->name_am }}</option>@endforeach
            </select>
        </div>
    @endif
    <div><button class="btn" type="submit">አሳይ</button></div>
    <div class="actions" style="margin-top:0;margin-left:auto">
        <a class="btn secondary" href="{{ route('exports.annual.excel', request()->query()) }}">⬇ ዓመታዊ Excel</a>
        <a class="btn" href="{{ route('exports.annual.pdf', request()->query()) }}">⬇ ዓመታዊ PDF</a>
    </div>
</form>

<div class="grid cols-4">
    <div class="card stat"><div class="n">{{ number_format($totalFarmers) }}</div><div class="l">የተገለገሉ አርሶ አደሮች</div></div>
    <div class="card stat"><div class="n">{{ $totalReportedDays }}</div><div class="l">ሪፖርት የተላከባቸው ቀናት</div></div>
    <div class="card stat"><div class="n">{{ $feedbackAvg ? "{$feedbackAvg} / 5" : '—' }}</div><div class="l">የአርሶ አደር አስተያየት ({{ $feedbackCount }})</div></div>
    <div class="card stat"><div class="n">{{ $weeklySummaryCount }}</div><div class="l">ሳምንታዊ የባለሙያ ማጠቃለያዎች</div></div>
</div>

@if($woredaExtension->isNotEmpty())
    <h2>የወረዳዎች ኤክስቴንሽን ማጠቃለያ</h2>
    <div class="card table-wrap">
        <table>
            <thead><tr><th>ወረዳ</th><th class="num">ባለሙያዎች</th><th class="num">የተላኩ ቀናት</th><th class="num">ጠቅላላ አርሶ አደር</th><th class="num">አማካይ/ቀን</th><th>ባንድ</th></tr></thead>
            <tbody>
            @foreach($woredaExtension as $w)
                <tr>
                    <td>{{ $w['woreda']?->name_am }}</td>
                    <td class="num">{{ $w['experts'] }}</td>
                    <td class="num">{{ $w['days_reported'] }}</td>
                    <td class="num">{{ number_format($w['farmers']) }}</td>
                    <td class="num">{{ $w['avg'] ?? '—' }}</td>
                    <td><span class="badge {{ $w['band']->badge() }}">{{ $w['band']->label() }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2>የሪፖርቶች #2–#8 ዓመታዊ ድምር</h2>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ሪፖርት</th><th class="num">የሪፖርት ብዛት</th><th>ዋና መለኪያዎችና ድምር</th></tr></thead>
        <tbody>
        @foreach($periodicSummaries as $entry)
            @php($t = $entry['type']) @php($s = $entry['summary'])
            <tr>
                <td><strong>#{{ $t->number() }} {{ $t->label() }}</strong></td>
                <td class="num">{{ $s['reportCount'] }}</td>
                <td>
                    @foreach($s['fields'] as $k => $f)
                        <span style="margin-right:1rem">{{ $f['label'] }}: <strong>{{ qty($s['totals'][$k] ?? 0) }}</strong></span>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<h2>የዘርፍ ቅጾች ዓመታዊ ድምር</h2>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ዘርፍ</th><th class="num">የሪፖርት ብዛት</th><th>ዋና መለኪያዎችና ድምር</th></tr></thead>
        <tbody>
        @foreach($sectorSummaries as $entry)
            @php($sec = $entry['sector']) @php($s = $entry['summary'])
            <tr>
                <td><strong>{{ $sec->label() }}</strong></td>
                <td class="num">{{ $s['reportCount'] }}</td>
                <td>
                    @foreach($s['fields'] as $k => $f)
                        <span style="margin-right:1rem">{{ $f['label'] }}: <strong>{{ qty($s['totals'][$k] ?? 0) }}</strong></span>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
