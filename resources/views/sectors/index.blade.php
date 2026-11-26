@extends('layouts.app')
@section('title', $sectorType->label())
@section('content')
<h1>የዘርፍ ቅጽ — {{ $sectorType->label() }}</h1>
<p class="muted">{{ $sectorType->description() }} · ጊዜ፦ {{ $period->label() }}</p>

@include('partials.period-filter', ['woredas' => $woredas, 'statuses' => $statuses])

@if($summary['woredaRows']->isNotEmpty())
    <h2>ማጠናቀር በወረዳ (ቀበሌ → ወረዳ → ዞን)</h2>
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ወረዳ</th>
                <th class="num">ሪፖርቶች</th>
                <th class="num">ቀበሌዎች</th>
                @foreach($summary['fields'] as $f)<th class="num">{{ $f['label'] }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($summary['woredaRows'] as $r)
                <tr>
                    <td>{{ $r['woreda']->name_am }}</td>
                    <td class="num">{{ $r['reports'] }}</td>
                    <td class="num">{{ $r['kebeles_reported'] }}</td>
                    @foreach(array_keys($summary['fields']) as $key)
                        <td class="num">{{ qty($r['sums'][$key] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <th>ዞናዊ ድምር</th>
                <th class="num">{{ $summary['reportCount'] }}</th>
                <th class="num">{{ $summary['kebeleRows']->count() }}</th>
                @foreach(array_keys($summary['fields']) as $key)
                    <th class="num">{{ qty($summary['totals'][$key] ?? 0) }}</th>
                @endforeach
            </tr>
            </tfoot>
        </table>
    </div>
@endif

<h2>የተላኩ ቅጾች ዝርዝር</h2>
<div class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>ቀን</th>
            <th>ጊዜ</th>
            <th>ቀበሌ / ወረዳ</th>
            <th>ባለሙያ</th>
            @foreach($sectorType->fields() as $f)
                <th @class(['num' => in_array($f['type'], ['int', 'decimal'], true)])>{{ $f['label'] }}</th>
            @endforeach
            <th>ሁኔታ</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($reports as $r)
            <tr>
                <td>{{ et_date($r->report_date) }}</td>
                <td>{{ $r->period_type->label() }}</td>
                <td>{{ $r->location?->name_am }} <span class="muted">/ {{ $r->location?->parent?->name_am }}</span></td>
                <td>{{ $r->user?->name }}</td>
                @foreach($sectorType->fields() as $key => $f)
                    @php($val = $r->payload[$key] ?? null)
                    @if($f['type'] === 'select' && $val !== null)
                        <td>{{ $f['options'][$val] ?? $val }}</td>
                    @elseif(in_array($f['type'], ['int', 'decimal'], true))
                        <td class="num">{{ qty($val) }}</td>
                    @else
                        <td>{{ $val ?? '—' }}</td>
                    @endif
                @endforeach
                <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                <td><a class="btn sm secondary" href="{{ route('sectors.show', $r) }}">ክፈት</a></td>
            </tr>
        @empty
            <tr><td colspan="12" class="muted">ምንም ሪፖርት አልተገኘም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $reports->links() }}</div>
</div>

<div class="actions">
    <a class="btn secondary" href="{{ route('exports.sectors', [$sectorType->value] + request()->query()) }}">⬇ Excel አውርድ</a>
    @can('create', App\Models\SectorReport::class)
        <a class="btn" href="{{ route('sectors.create', $sectorType->value) }}">+ አዲስ ቅጽ</a>
    @endcan
    <a href="{{ route('sectors.hub') }}">← ሁሉም ዘርፎች</a>
</div>
@endsection
