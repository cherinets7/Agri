@extends('layouts.app')
@section('title', "#{$reportType->number()} {$reportType->label()}")
@section('content')
<h1>#{{ $reportType->number() }} · {{ $reportType->label() }}</h1>
<p class="muted">{{ $reportType->description() }} · ጊዜ፦ {{ $period->label() }}</p>

@include('partials.period-filter', ['woredas' => $woredas, 'statuses' => $statuses])

@if($summary['cascade'])
    @php($cascade = $summary['cascade'])
    <h2>የግብአት ፍሰት (ዞን → ወረዳ → ቀበሌ → አርሶ አደር)</h2>
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ወረዳ</th>
                <th class="num">ከዞን የተላከ</th>
                <th class="num">ከወረዳ ለቀበሌ የተላከ</th>
                <th class="num">ቀበሌ የተቀበለው</th>
                <th class="num">ለአርሶ አደር የደረሰ</th>
                <th class="num">ተጠቃሚ አርሶ አደሮች</th>
            </tr>
            </thead>
            <tbody>
            @foreach($cascade['woredaRows'] as $cw)
                <tr>
                    <td>{{ $cw['woreda']->name_am }}</td>
                    <td class="num">{{ qty($cw['zone_dispatched']) }}</td>
                    <td class="num">{{ qty($cw['woreda_dispatched']) }}</td>
                    <td class="num">{{ qty($cw['kebele_received']) }}</td>
                    <td class="num"><strong>{{ qty($cw['farmer_distributed']) }}</strong></td>
                    <td class="num">{{ qty($cw['beneficiary_farmers']) }}</td>
                </tr>
            @endforeach
            </tbody>
            @if($cascade['woredaRows']->count() > 1)
                <tfoot>
                <tr>
                    <th>ዞናዊ ድምር</th>
                    <th class="num">{{ qty($cascade['totals']['zone_dispatched']) }}</th>
                    <th class="num">{{ qty($cascade['totals']['woreda_dispatched']) }}</th>
                    <th class="num">{{ qty($cascade['totals']['kebele_received']) }}</th>
                    <th class="num">{{ qty($cascade['totals']['farmer_distributed']) }}</th>
                    <th class="num">{{ qty($cascade['totals']['beneficiary_farmers']) }}</th>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if($dispatchTargets->isNotEmpty())
        <div class="card">
            <strong>{{ auth()->user()->isZone() ? 'ከዞን ወደ ወረዳ ግብአት መላክ' : 'ከወረዳ ወደ ቀበሌ ግብአት መላክ' }}</strong>
            <form method="POST" action="{{ route('dispatches.store') }}" class="filters" style="margin-top:.6rem">
                @csrf
                <div>
                    <label for="to_location_id">ተቀባይ ({{ auth()->user()->isZone() ? 'ወረዳ' : 'ቀበሌ' }})</label>
                    <select id="to_location_id" name="to_location_id" required>
                        @foreach($dispatchTargets as $t)
                            <option value="{{ $t->id }}">{{ $t->name_am }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dispatch_date">ቀን</label>
                    <input id="dispatch_date" type="date" name="dispatch_date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required>
                </div>
                <div>
                    <label for="input_type">የግብዓት ዓይነት</label>
                    <select id="input_type" name="input_type" required>
                        @foreach(App\Enums\PeriodicReportType::inputTypes() as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="unit">መለኪያ</label>
                    <select id="unit" name="unit" required>
                        <option value="quintal">ኩ/ል</option>
                        <option value="liter">ሊትር</option>
                        <option value="count">ቁጥር</option>
                    </select>
                </div>
                <div>
                    <label for="quantity">መጠን</label>
                    <input id="quantity" type="number" step="any" min="0.001" name="quantity" required>
                </div>
                <div><button class="btn sm" type="submit">+ ስርጭት መዝግብ</button></div>
            </form>
            @if($cascade['dispatches']->isNotEmpty())
                <div class="table-wrap" style="margin-top:.8rem">
                    <table>
                        <thead><tr><th>ቀን</th><th>ላኪ</th><th>ተቀባይ</th><th>ግብዓት</th><th class="num">መጠን</th><th></th></tr></thead>
                        <tbody>
                        @foreach($cascade['dispatches']->take(10) as $d)
                            <tr>
                                <td>{{ et_date($d->dispatch_date) }}</td>
                                <td>{{ $d->fromLocation?->name_am }}</td>
                                <td>{{ $d->toLocation?->name_am }}</td>
                                <td>{{ $d->inputTypeLabel() }}</td>
                                <td class="num">{{ qty($d->quantity) }} {{ $d->unitLabel() }}</td>
                                <td>
                                    @if(auth()->user()->isZone() || $d->user_id === auth()->id())
                                        <form method="POST" action="{{ route('dispatches.destroy', $d) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit">ሰርዝ</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endif

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

@if($summary['kebeleRows']->isNotEmpty() && ! auth()->user()->isKebele())
    <h2>ማጠናቀር በቀበሌ</h2>
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ወረዳ</th>
                <th>ቀበሌ</th>
                <th class="num">ሪፖርቶች</th>
                @foreach($summary['fields'] as $f)<th class="num">{{ $f['label'] }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($summary['kebeleRows'] as $r)
                <tr>
                    <td>{{ $r['woreda']?->name_am }}</td>
                    <td>{{ $r['location']?->name_am }}</td>
                    <td class="num">{{ $r['reports'] }}</td>
                    @foreach(array_keys($summary['fields']) as $key)
                        <td class="num">{{ qty($r['sums'][$key] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2>የተላኩ ሪፖርቶች ዝርዝር</h2>
<div class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>ቀን</th>
            <th>ጊዜ</th>
            <th>ቀበሌ / ወረዳ</th>
            <th>ባለሙያ</th>
            @foreach($reportType->fields() as $f)
                <th @class(['num' => in_array($f['type'], ['int', 'decimal'], true)])>{{ $f['label'] }}</th>
            @endforeach
            <th>ሁኔታ</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($reports as $r)
            <tr>
                <td>{{ et_date($r->report_date) }}<div class="muted" style="font-size:.8rem">{{ $r->report_date->toDateString() }}</div></td>
                <td>{{ $r->period_type->label() }}</td>
                <td>{{ $r->location?->name_am }} <span class="muted">/ {{ $r->location?->parent?->name_am }}</span></td>
                <td>{{ $r->user?->name }}</td>
                @foreach($reportType->fields() as $key => $f)
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
                <td><a class="btn sm secondary" href="{{ route('periodic.show', $r) }}">ክፈት</a></td>
            </tr>
        @empty
            <tr><td colspan="12" class="muted">ምንም ሪፖርት አልተገኘም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $reports->links() }}</div>
</div>

<div class="actions">
    <a class="btn secondary" href="{{ route('exports.periodic', [$reportType->value] + request()->query()) }}">⬇ Excel አውርድ</a>
    @can('create', App\Models\PeriodicReport::class)
        <a class="btn" href="{{ route('periodic.create', $reportType->value) }}">+ አዲስ ሪፖርት</a>
    @endcan
    <a href="{{ route('periodic.hub') }}">← ሁሉም ሪፖርቶች (#2–#8)</a>
</div>
@endsection
