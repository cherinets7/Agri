@extends('layouts.app')
@section('title', 'ሳምንታዊ የባለሙያ ማጠቃለያ')
@section('content')
<h1>ሳምንታዊ የባለሙያ ማጠቃለያ ሪፖርት</h1>

<form method="GET" class="card filters">
    @if($woredas->isNotEmpty())
        <div>
            <label for="woreda">ወረዳ</label>
            <select id="woreda" name="woreda">
                <option value="">ሁሉም</option>
                @foreach($woredas as $w)
                    <option value="{{ $w->id }}" @selected((int) request('woreda') === $w->id)>{{ $w->name_am }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label for="status">ሁኔታ</label>
        <select id="status" name="status">
            <option value="">ሁሉም</option>
            @foreach($statuses as $k => $label)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div><button class="btn" type="submit">አጣራ</button></div>
    @can('create', App\Models\ExpertWeeklySummary::class)
        <div style="margin-left:auto"><a class="btn" href="{{ route('summaries.create') }}">+ አዲስ ሳምንታዊ ማጠቃለያ</a></div>
    @endcan
</form>

<div class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>ሳምንት (መጀመሪያ)</th>
            <th>ባለሙያ</th>
            <th>ቀበሌ / ወረዳ</th>
            <th class="num">ቀናት</th>
            <th class="num">አርሶ አደሮች</th>
            <th class="num">አማካይ/ቀን</th>
            <th class="num">አስተያየት (ከ5)</th>
            <th>ሁኔታ</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($summaries as $s)
            <tr>
                <td>{{ et_date($s->week_start) }}</td>
                <td>{{ $s->user?->name }}</td>
                <td>{{ $s->location?->name_am }} <span class="muted">/ {{ $s->location?->parent?->name_am }}</span></td>
                <td class="num">{{ $s->days_reported }}</td>
                <td class="num">{{ $s->farmers_total }}</td>
                <td class="num">{{ $s->avg_per_day }}</td>
                <td class="num">{{ $s->feedback_avg ? "{$s->feedback_avg} ({$s->feedback_count})" : '—' }}</td>
                <td><span class="badge {{ $s->status->badge() }}">{{ $s->status->label() }}</span></td>
                <td><a class="btn sm secondary" href="{{ route('summaries.show', $s) }}">ክፈት</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">ምንም ሳምንታዊ ማጠቃለያ የለም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $summaries->links() }}</div>
</div>
@endsection
