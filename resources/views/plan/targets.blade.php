@extends('layouts.app')
@section('title', 'ዓመታዊ ዕቅድ')
@section('content')
<h1>ዓመታዊ ዕቅድ — ዞን ወደ ወረዳ ማከፋፈል</h1>
<p class="muted">የዞኑን ዕቅድ ያስገቡ፤ የወረዳ ዕቅዶችን በእጅ ይሙሉ ወይም «ከዞን ዕቅድ እኩል አከፋፍል» ይምረጡ። ባዶ = ዕቅድ የለም።</p>

<form method="GET" class="card filters">
    <div>
        <label for="year">ዓ/ም</label>
        <select id="year" name="year" onchange="this.form.submit()">
            @foreach($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
        </select>
    </div>
    <noscript><div><button class="btn" type="submit">አሳይ</button></div></noscript>
</form>

<form method="POST" action="{{ route('plan.update') }}">
    @csrf @method('PUT')
    <input type="hidden" name="year" value="{{ $year }}">
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ተግባር</th><th>መለኪያ</th>
                <th class="num">ዞናዊ ዕቅድ</th>
                @foreach($woredas as $w)<th class="num">{{ $w->name_am }}</th>@endforeach
                <th class="num">ያልተከፋፈለ</th>
            </tr>
            </thead>
            <tbody>
            @foreach($goals as $goal)
                <tr><td colspan="{{ 4 + $woredas->count() }}" class="goal-head"><strong>{{ $goal->number }}. {{ $goal->name_am }}</strong></td></tr>
                @foreach($goal->activities as $a)
                    @php
                        $t = $targets[$a->id] ?? [];
                        $allocated = collect($woredas)->sum(fn ($w) => $t[$w->id] ?? 0);
                        $zoneTarget = $t[$zone->id] ?? null;
                        $left = $zoneTarget === null ? null : round($zoneTarget - $allocated, 3);
                    @endphp
                    <tr>
                        <td>{{ $a->name_am }}</td>
                        <td>{{ $a->unit->label() }}</td>
                        <td style="min-width:8rem"><input type="number" step="any" min="0" name="zone[{{ $a->id }}]" value="{{ old('zone.'.$a->id, isset($t[$zone->id]) ? qty($t[$zone->id], '') : '') }}"></td>
                        @foreach($woredas as $w)
                            <td style="min-width:8rem"><input type="number" step="any" min="0" name="woreda[{{ $a->id }}][{{ $w->id }}]" value="{{ old('woreda.'.$a->id.'.'.$w->id, isset($t[$w->id]) ? qty($t[$w->id], '') : '') }}"></td>
                        @endforeach
                        <td class="num">
                            @if($left === null) —
                            @else <span class="badge {{ abs($left) < 0.0005 ? 'green' : ($left < 0 ? 'red' : 'amber') }}">{{ qty($left) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card">
        <label style="font-weight:400"><input type="checkbox" name="distribute" value="1"> ከዞን ዕቅድ እኩል አከፋፍል (ለወረዳ ዕቅድ ባዶ ለሆኑ ተግባራት ብቻ)</label>
        <div class="actions"><button class="btn" type="submit">አስቀምጥ</button>
            <a href="{{ route('matrix.index', ['year' => $year, 'detail' => 1]) }}">ወደ ማትሪክስ →</a>
            <a href="{{ route('plan.structure') }}">ግቦችና ተግባራት</a></div>
    </div>
</form>
@endsection
