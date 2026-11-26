@extends('layouts.app')
@section('title', 'ሳምንታዊ ክንውን ሪፖርት')
@section('content')
<h1>ሳምንታዊ ክንውን — {{ $woreda?->name_am }}</h1>

<div class="grid cols-2">
    <div class="card">
        <dl class="kv">
            <dt>ሁኔታ</dt><dd><span class="badge {{ $report->status->badge() }}">{{ $report->status->label() }}</span></dd>
            <dt>ሳምንት</dt><dd>{{ et_date($report->week_start) }} – {{ et_date($report->weekEnd()) }} ({{ $report->week_start->toDateString() }})</dd>
            <dt>ዓ/ም</dt><dd>{{ $report->fiscal_year }}</dd>
            <dt>ወረዳ</dt><dd>{{ $woreda?->name_am }}</dd>
            <dt>ሪፖርት አድራጊ</dt><dd>{{ $report->user?->name }}</dd>
            <dt>ማስታወሻ</dt><dd>{{ $report->notes ?: '—' }}</dd>
            @if($report->declared_at)<dt>ማረጋገጫ</dt><dd>{{ $report->user?->name }} — {{ $report->declared_at->format('Y-m-d H:i') }}@if($report->signature_data)<img class="sig-img" src="{{ $report->signature_data }}" alt="የወረዳ ፊርማ">@endif</dd>@endif
            @if($report->approved_at)<dt>ያጸደቀ</dt><dd>{{ $report->approver?->name }} — {{ $report->approved_at->format('Y-m-d H:i') }}@if($report->approved_signature_data)<img class="sig-img" src="{{ $report->approved_signature_data }}" alt="የዞን ፊርማ">@endif</dd>@endif
            @if($report->status === App\Enums\WeeklyPlanStatus::Returned)<dt>የመመለስ ምክንያት</dt><dd>{{ $report->return_reason }}</dd>@endif
        </dl>
    </div>
    <div class="card">
        <strong>የሁኔታ ታሪክ</strong>
        <ul class="timeline" style="margin-top:.6rem">
            @foreach($report->actions as $a)
                <li>
                    <strong>{{ $a->actionLabel() }}</strong> — {{ $a->user?->name }}
                    <div class="muted">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
                    @if($a->note)<div>{{ $a->note }}</div>@endif
                    @if($a->signature_data)<img class="sig-img" src="{{ $a->signature_data }}" alt="ፊርማ">@endif
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="card table-wrap">
    <table>
        <thead><tr><th>ተግባር</th><th>መለኪያ</th><th class="num">የወረዳ ዕቅድ</th><th class="num">እስከ ቀደመው ሳምንት</th><th class="num">የዚህ ሳምንት</th><th class="num">ድምር</th><th class="num">%</th></tr></thead>
        <tbody>
        @foreach($goals as $goal)
            <tr><td colspan="7" class="goal-head"><strong>{{ $goal->number }}. {{ $goal->name_am }}</strong></td></tr>
            @foreach($goal->activities as $a)
                @php
                    $now = isset($current[$a->id]) ? (float) $current[$a->id] : null;
                    $prev = $before[$a->id] ?? null;
                    $total = ($now === null && $prev === null) ? null : ($now ?? 0) + ($prev ?? 0);
                    $pct = App\Support\Progress::percent($total, $targets[$a->id] ?? null);
                @endphp
                <tr>
                    <td>{{ $a->name_am }}</td>
                    <td>{{ $a->unit->label() }}</td>
                    <td class="num">{{ qty($targets[$a->id] ?? null) }}</td>
                    <td class="num">{{ qty($prev) }}</td>
                    <td class="num"><strong>{{ qty($now) }}</strong></td>
                    <td class="num">{{ qty($total) }}</td>
                    <td class="num">@if($pct !== null)<span class="badge {{ App\Support\Progress::badge($pct) }}">{{ $pct }}%</span>@else — @endif</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <div class="actions" style="margin-top:0">
        @can('update', $report)<a class="btn secondary" href="{{ route('weekly.edit', $report) }}">አስተካክል</a>@endcan
        @can('submit', $report)
            <form method="POST" action="{{ route('weekly.submit', $report) }}" onsubmit="return confirm('መረጃው ትክክል መሆኑን አረጋግጠዋል? ለዞን ይላካል።')">@csrf<button class="btn" type="submit">አረጋግጥና ለዞን ላክ</button></form>
        @endcan
        @can('delete', $report)
            <form method="POST" action="{{ route('weekly.destroy', $report) }}" onsubmit="return confirm('ረቂቁ ይሰረዝ?')">@csrf @method('DELETE')<button class="btn danger" type="submit">ሰርዝ</button></form>
        @endcan
        <a href="{{ route('weekly.index') }}">← ወደ ዝርዝር</a>
    </div>

    @can('approve', $report)
        <h2>የዞን ማጽደቅ</h2>
        <form method="POST" action="{{ route('weekly.approve', $report) }}">
            @csrf
            <label for="note-a">ማስታወሻ (አማራጭ)</label>
            <input id="note-a" type="text" name="note">
            <x-signature-pad name="signature_data" label="የዞን ማጽደቂያ ፊርማ (አማራጭ)" />
            <div class="actions"><button class="btn" type="submit">✔ አጽድቅ</button></div>
        </form>
    @endcan
    @can('return', $report)
        <h2>ወደ ወረዳ መልስ</h2>
        <form method="POST" action="{{ route('weekly.return', $report) }}">
            @csrf
            <label for="reason">የመመለስ ምክንያት (ግዴታ)</label>
            <textarea id="reason" name="reason" required></textarea>
            <div class="actions"><button class="btn warn" type="submit">↩ መልስ</button></div>
        </form>
    @endcan
</div>
@endsection
