<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: abyssinicasil, sans-serif; font-size: 11px; color: #1c2a22; }
        h1 { font-size: 16px; color: #1f5a23; margin-bottom: 4px; }
        h2 { font-size: 13px; color: #1f5a23; margin-top: 14px; border-bottom: 1px solid #dfe5df; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cfd8cf; padding: 4px 6px; text-align: left; }
        th { background-color: #f0f4ef; }
        .num { text-align: right; }
        .muted { color: #5f6f66; }
    </style>
</head>
<body>
<h1>የየም ዞን ግብርና መምሪያ — ዓመታዊ ሪፖርት ({{ $fiscalYear }} ዓ/ም)</h1>
<p class="muted">ጊዜ፦ {{ et_date($from) }} – {{ et_date($to) }}</p>

<table>
    <tr>
        <th>የተገለገሉ አርሶ አደሮች</th>
        <th>ሪፖርት የተላከባቸው ቀናት</th>
        <th>የአርሶ አደር አስተያየት (ከ5)</th>
        <th>ሳምንታዊ ማጠቃለያዎች</th>
    </tr>
    <tr>
        <td>{{ number_format($totalFarmers) }}</td>
        <td>{{ $totalReportedDays }}</td>
        <td>{{ $feedbackAvg ? "{$feedbackAvg} / 5 ({$feedbackCount})" : '—' }}</td>
        <td>{{ $weeklySummaryCount }}</td>
    </tr>
</table>

@if($woredaExtension->isNotEmpty())
<h2>የወረዳዎች ኤክስቴንሽን አፈጻጸም</h2>
<table>
    <thead><tr><th>ወረዳ</th><th class="num">ባለሙያዎች</th><th class="num">ቀናት</th><th class="num">አርሶ አደር</th><th class="num">አማካይ/ቀን</th><th>ባንድ</th></tr></thead>
    <tbody>
    @foreach($woredaExtension as $w)
        <tr>
            <td>{{ $w['woreda']?->name_am }}</td>
            <td class="num">{{ $w['experts'] }}</td>
            <td class="num">{{ $w['days_reported'] }}</td>
            <td class="num">{{ number_format($w['farmers']) }}</td>
            <td class="num">{{ $w['avg'] ?? '—' }}</td>
            <td>{{ $w['band']->label() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<h2>ሪፖርቶች #2–#8 ዓመታዊ ድምር</h2>
<table>
    <thead><tr><th>ሪፖርት</th><th class="num">ብዛት</th><th>ማጠቃለያ</th></tr></thead>
    <tbody>
    @foreach($periodicSummaries as $entry)
        @php($t = $entry['type']) @php($s = $entry['summary'])
        <tr>
            <td>#{{ $t->number() }} {{ $t->label() }}</td>
            <td class="num">{{ $s['reportCount'] }}</td>
            <td>
                @foreach($s['fields'] as $k => $f)
                    {{ $f['label'] }}: {{ qty($s['totals'][$k] ?? 0) }} |
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>የዘርፍ ቅጾች ዓመታዊ ድምር</h2>
<table>
    <thead><tr><th>ዘርፍ</th><th class="num">ብዛት</th><th>ማጠቃለያ</th></tr></thead>
    <tbody>
    @foreach($sectorSummaries as $entry)
        @php($sec = $entry['sector']) @php($s = $entry['summary'])
        <tr>
            <td>{{ $sec->label() }}</td>
            <td class="num">{{ $s['reportCount'] }}</td>
            <td>
                @foreach($s['fields'] as $k => $f)
                    {{ $f['label'] }}: {{ qty($s['totals'][$k] ?? 0) }} |
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
