@extends('layouts.app')
@section('title', 'ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ')
@section('content')
@php
    $user = auth()->user();
    $query = request()->query();
    $colsPerWoreda = $detail ? 3 : 1;
@endphp
<h1>ሳምንታዊ ተግባር አፈፃፀም ማትሪክስ <span class="muted" style="font-weight:400">(ዕቅድ / ክንውን)</span></h1>
<p class="muted">
    {{ $year }} ዓ/ም ·
    @if($through)
        {{ $weekOnly ? 'የሳምንቱ ክንውን' : 'ድምር ክንውን እስከ' }} {{ et_date($through) }} ({{ $through->toDateString() }}) ሳምንት
    @else
        እስካሁን የተላከ ሳምንታዊ ሪፖርት የለም
    @endif
    · {{ $approvedOnly ? 'በዞን የጸደቁ ብቻ' : 'የተላኩና የጸደቁ ሪፖርቶች' }}
    @unless($showZone) · የ{{ $woredas->first()?->name_am }} ብቻ @endunless
</p>

<form method="GET" class="card filters">
    <div>
        <label for="year">ዓ/ም</label>
        <select id="year" name="year">
            @foreach($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
        </select>
    </div>
    <x-ethiopian-date-input name="week" id="week" label="እስከ ሳምንት (የሳምንቱ ማንኛውም ቀን)" :value="$through?->toDateString() ?? ''" />
    <div>
        <label for="mode">እይታ</label>
        <select id="mode" name="mode">
            <option value="cumulative" @selected(! $weekOnly)>ድምር (ከዓመቱ መጀመሪያ)</option>
            <option value="week" @selected($weekOnly)>የዚህ ሳምንት ብቻ</option>
        </select>
    </div>
    <div class="checks" style="padding-bottom:.5rem">
        <label><input type="checkbox" name="approved" value="1" @checked($approvedOnly)> የጸደቁ ብቻ</label>
        <label><input type="checkbox" name="detail" value="1" @checked($detail)> የወረዳ ዕቅድና %</label>
    </div>
    <div><button class="btn" type="submit">አሳይ</button></div>
</form>

<div class="card table-wrap matrix">
    <table>
        <thead>
        <tr>
            <th rowspan="2">ተ/ቁ</th>
            <th rowspan="2">የተቋሙ ዋና ዋና ስትራተጂክ ግቦች</th>
            <th rowspan="2">ግቦችን ለማሳካት የሚከናወኑ ዋና ዋና ተግባራት</th>
            <th rowspan="2">መለኪያ</th>
            @if($showZone)<th colspan="3" class="grp">ዞናዊ ድምር</th>@endif
            <th colspan="{{ max($woredas->count() * $colsPerWoreda, 1) }}" class="grp">የመዋቅሮች አፈፃፀም</th>
        </tr>
        <tr>
            @if($showZone)
                <th class="num">ዕቅድ</th><th class="num">ክንውን</th><th class="num">%</th>
            @endif
            @foreach($woredas as $w)
                @if($detail)
                    <th class="num">{{ $w->name_am }}<br><small>ዕቅድ</small></th>
                    <th class="num"><small>ክንውን</small></th>
                    <th class="num"><small>%</small></th>
                @else
                    <th class="num">{{ $w->name_am }}</th>
                @endif
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($goals as $block)
            @foreach($block['rows'] as $i => $row)
                <tr>
                    @if($i === 0)
                        <td rowspan="{{ count($block['rows']) }}" class="goal-n">{{ $block['goal']->number }}</td>
                        <td rowspan="{{ count($block['rows']) }}" class="goal">{{ $block['goal']->name_am }}</td>
                    @endif
                    <td>{{ $row['activity']->name_am }}</td>
                    <td>{{ $row['activity']->unit->label() }}</td>
                    @if($showZone)
                        <td class="num">{{ qty($row['zone_plan']) }}</td>
                        <td class="num"><strong>{{ qty($row['zone_actual']) }}</strong></td>
                        <td class="num">@if($row['zone_pct'] !== null)<span class="badge {{ App\Support\Progress::badge($row['zone_pct']) }}">{{ $row['zone_pct'] }}%</span>@else — @endif</td>
                    @endif
                    @foreach($woredas as $w)
                        @php($c = $row['cells'][$w->id])
                        @if($detail)
                            <td class="num">{{ qty($c['plan']) }}</td>
                            <td class="num">{{ qty($c['actual']) }}</td>
                            <td class="num">@if($c['pct'] !== null)<span class="badge {{ App\Support\Progress::badge($c['pct']) }}">{{ $c['pct'] }}%</span>@else — @endif</td>
                        @else
                            <td class="num">{{ qty($c['actual']) }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr><td colspan="12" class="muted">ምንም ስትራተጂክ ግብ አልተመዘገበም።@can('manage-plan') <a href="{{ route('plan.structure') }}">ግቦችንና ተግባራትን ያስገቡ</a>@endcan</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <strong>መግለጫ፦</strong>
    @if($showZone)ዞናዊ ክንውን = የወረዳዎች ክንውን ድምር (ራስ-ሰር)። ዞናዊ ዕቅድ = ዞኑ ያስገባው ዕቅድ (ከሌለ የወረዳ ዕቅዶች ድምር)። @endif
    ባዶ (—) = ምንም አልተመዘገበም።
    <span class="badge red">&lt;50%</span> <span class="badge amber">50–79%</span> <span class="badge green">80–100%</span> <span class="badge blue">&gt;100% — ማረጋገጥ ይገባል</span>
</div>

<div class="actions">
    <a class="btn secondary" href="{{ route('exports.matrix', $query) }}">⬇ ማትሪክስ Excel</a>
    @can('create', App\Models\WeeklyPlanReport::class)<a class="btn" href="{{ route('weekly.create') }}">+ ሳምንታዊ ክንውን አስገባ</a>@endcan
    @can('manage-plan')
        <a class="btn secondary" href="{{ route('plan.edit', ['year' => $year]) }}">ዕቅድ አስገባ / አከፋፍል</a>
        <a class="btn secondary" href="{{ route('plan.structure') }}">ግቦችና ተግባራት</a>
    @endcan
</div>
@endsection
