@extends('layouts.app')
@section('title', 'ሪፖርቶች')
@section('content')
<h1>የዕለታዊ ኤክስቴንሽን ሪፖርቶች</h1>
<p class="muted">ጊዜ፦ {{ $period->label() }}</p>

@include('partials.period-filter', ['statuses' => $statuses])

<div class="card table-wrap">
    <table>
        <thead><tr><th>ቀን</th><th>ቀበሌ / ወረዳ</th><th>ባለሙያ</th><th class="num">አርሶ አደር</th><th class="num">ሴት</th><th class="num">ወጣት</th><th>ባንድ</th><th>ሁኔታ</th><th></th></tr></thead>
        <tbody>
        @forelse($reports as $r)
            <tr>
                <td>{{ et_date($r->report_date) }}<div class="muted" style="font-size:.8rem">{{ $r->report_date->toDateString() }}</div></td>
                <td>{{ $r->location?->name_am }} <span class="muted">/ {{ $r->location?->parent?->name_am }}</span></td>
                <td>{{ $r->user?->name }}</td>
                <td class="num">{{ $r->farmers_total }}</td>
                <td class="num">{{ $r->female_farmers }}</td>
                <td class="num">{{ $r->youth_farmers }}</td>
                <td><span class="badge {{ $r->band()->badge() }}">{{ $r->band()->label() }}</span></td>
                <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                <td><a class="btn sm secondary" href="{{ route('reports.show', $r) }}">ክፈት</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">ምንም ሪፖርት አልተገኘም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $reports->links() }}</div>
</div>

<div class="actions">
    <a class="btn secondary" href="{{ route('exports.reports', request()->query()) }}">⬇ Excel አውርድ</a>
    @can('create', App\Models\ExtensionReport::class)
        <a class="btn" href="{{ route('reports.create') }}">+ አዲስ ዕለታዊ ሪፖርት</a>
    @endcan
</div>
@endsection
