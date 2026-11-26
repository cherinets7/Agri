@extends('layouts.app')
@section('title', 'ሪፖርቶች #2–#8')
@section('content')
<h1>የግብርና ሪፖርቶች (#2–#8)</h1>
<p class="muted">ቀበሌ → ወረዳ → ዞን የማረጋገጫ ፍሰት፣ ማጠናቀር እና Excel ኤክስፖርት (ጊዜ፦ {{ $period->label() }})</p>

<div class="grid cols-2">
    @foreach($cards as $c)
        @php($type = $c['type'])
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <div>
                    <strong>#{{ $type->number() }} · {{ $type->label() }}</strong>
                    <div class="muted" style="font-size:.88rem">{{ $type->unit() }}</div>
                </div>
                @if($c['pending'] > 0)
                    <span class="badge amber">የሚጠብቅ፦ {{ $c['pending'] }}</span>
                @endif
            </div>
            <p class="muted" style="margin:.4rem 0 .8rem;font-size:.9rem">{{ $type->description() }}</p>
            <div class="actions" style="margin-top:0">
                <a class="btn sm secondary" href="{{ route('periodic.index', $type->value) }}">ዝርዝርና ማጠናቀር ({{ $c['count_period'] }})</a>
                @can('create', App\Models\PeriodicReport::class)
                    <a class="btn sm" href="{{ route('periodic.create', $type->value) }}">+ አዲስ ሪፖርት</a>
                @endcan
                <a class="btn sm secondary" href="{{ route('exports.periodic', $type->value) }}">⬇ Excel</a>
            </div>
        </div>
    @endforeach
</div>
@endsection
