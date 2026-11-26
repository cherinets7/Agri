@extends('layouts.app')
@section('title', 'የዘርፍ ቅጾች')
@section('content')
<h1>የባለሙያ ተግባር ክፍሎች (የዘርፍ ቅጾች)</h1>
<p class="muted">መስኖ፣ በልግ፣ እንስሳት፣ ዓሳ፣ ተፈጥሮ ሀብት፣ ቡናና ቅመማቅመም፣ ድለባና ሙከታ (ጊዜ፦ {{ $period->label() }})</p>

<div class="grid cols-2">
    @foreach($cards as $c)
        @php($sector = $c['sector'])
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <strong>{{ $sector->label() }}</strong>
                @if($c['pending'] > 0)
                    <span class="badge amber">የሚጠብቅ፦ {{ $c['pending'] }}</span>
                @endif
            </div>
            <p class="muted" style="margin:.4rem 0 .8rem;font-size:.9rem">{{ $sector->description() }}</p>
            <div class="actions" style="margin-top:0">
                <a class="btn sm secondary" href="{{ route('sectors.index', $sector->value) }}">ዝርዝርና ማጠናቀር ({{ $c['count_period'] }})</a>
                @can('create', App\Models\SectorReport::class)
                    <a class="btn sm" href="{{ route('sectors.create', $sector->value) }}">+ አዲስ ቅጽ</a>
                @endcan
                <a class="btn sm secondary" href="{{ route('exports.sectors', $sector->value) }}">⬇ Excel</a>
            </div>
        </div>
    @endforeach
</div>
@endsection
