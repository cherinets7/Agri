@extends('layouts.app')
@section('title', 'ሳምንታዊ ክንውን ሪፖርቶች')
@section('content')
<h1>ሳምንታዊ የዕቅድ ክንውን ሪፖርቶች (በወረዳ)</h1>

<form method="GET" class="card filters">
    <div>
        <label for="year">ዓ/ም</label>
        <select id="year" name="year"><option value="">ሁሉም</option>
            @foreach($years as $y)<option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="status">ሁኔታ</label>
        <select id="status" name="status"><option value="">ሁሉም</option>
            @foreach($statuses as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
        </select>
    </div>
    @if($woredas->isNotEmpty())
        <div>
            <label for="woreda">ወረዳ</label>
            <select id="woreda" name="woreda"><option value="">ሁሉም</option>
                @foreach($woredas as $w)<option value="{{ $w->id }}" @selected((string) request('woreda') === (string) $w->id)>{{ $w->name_am }}</option>@endforeach
            </select>
        </div>
    @endif
    <div><button class="btn" type="submit">አጣራ</button></div>
</form>

<div class="card table-wrap">
    <table>
        <thead><tr><th>ሳምንት</th><th>ወረዳ</th><th>ዓ/ም</th><th class="num">የተሞሉ ተግባራት</th><th>ሁኔታ</th><th></th></tr></thead>
        <tbody>
        @forelse($reports as $r)
            <tr>
                <td>{{ et_date($r->week_start) }} – {{ et_date($r->weekEnd()) }}<div class="muted" style="font-size:.8rem">{{ $r->week_start->toDateString() }}</div></td>
                <td>{{ $r->woreda?->name_am }}</td>
                <td>{{ $r->fiscal_year }}</td>
                <td class="num">{{ $r->actuals()->count() }}</td>
                <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                <td><a class="btn sm secondary" href="{{ route('weekly.show', $r) }}">ክፈት</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">ምንም ሪፖርት አልተገኘም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $reports->links() }}</div>
</div>

<div class="actions">
    @can('create', App\Models\WeeklyPlanReport::class)<a class="btn" href="{{ route('weekly.create') }}">+ አዲስ ሳምንታዊ ክንውን</a>@endcan
    <a class="btn secondary" href="{{ route('matrix.index') }}">ወደ ማትሪክስ</a>
</div>
@endsection
