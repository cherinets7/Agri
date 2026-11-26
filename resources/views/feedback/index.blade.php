@extends('layouts.app')
@section('title', 'የአርሶ አደር አስተያየት (QR)')
@section('content')
<h1>የአርሶ አደር አስተያየት (QR / ዲጂታል ፊርማ)</h1>
<p class="muted">ጊዜ፦ {{ $period->label() }}</p>

@include('partials.period-filter', ['woredas' => $woredas])

<div class="grid cols-4">
    <div class="card stat"><div class="n">{{ $totalCount }}</div><div class="l">ጠቅላላ አስተያየቶች</div></div>
    <div class="card stat"><div class="n">{{ $avgRating !== null ? "{$avgRating} / 5" : '—' }}</div><div class="l">አማካይ ደረጃ</div></div>
</div>

<div class="card">
    <div class="actions" style="margin-top:0">
        @if(auth()->user()->isKebele())
            <a class="btn" href="{{ route('feedback.create') }}">+ የአርሶ አደር አስተያየት መዝግብ (ሞባይል ፊርማ)</a>
            <a class="btn secondary" href="{{ route('feedback.qr-card') }}">የእኔን QR ኮድ ካርድ አሳይ</a>
        @else
            <strong>የባለሙያ QR ካርዶች፦</strong>
            @foreach($experts->take(8) as $exp)
                <a class="btn sm secondary" href="{{ route('feedback.qr-card', $exp) }}">{{ $exp->location?->name_am }}</a>
            @endforeach
        @endif
    </div>
</div>

<div class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>ቀን</th>
            <th>ባለሙያ / ቀበሌ</th>
            <th>አርሶ አደር</th>
            <th>አገልግሎት</th>
            <th>ደረጃ (1–5)</th>
            <th>አስተያየት</th>
            <th>ፊርማ / መንገድ</th>
        </tr>
        </thead>
        <tbody>
        @forelse($feedbacks as $fb)
            <tr>
                <td>{{ et_date($fb->feedback_date) }}</td>
                <td>{{ $fb->expert?->name }} <div class="muted" style="font-size:.82rem">{{ $fb->location?->name_am }} / {{ $fb->location?->parent?->name_am }}</div></td>
                <td>{{ $fb->farmer_name ?: 'ስም አልተገለጸም' }}@if($fb->farmer_phone)<div class="muted" style="font-size:.82rem">{{ $fb->farmer_phone }}</div>@endif</td>
                <td>{{ $fb->serviceLabel() }}</td>
                <td><span class="stars">{{ str_repeat('★', $fb->rating) }}{{ str_repeat('☆', 5 - $fb->rating) }}</span> ({{ $fb->rating }}/5)</td>
                <td>{{ $fb->comment ?: '—' }}</td>
                <td>
                    <span class="badge gray">{{ $fb->channel === 'qr' ? 'QR ኮድ' : 'ሞባይል' }}</span>
                    @if($fb->signature_data)
                        <div><img src="{{ $fb->signature_data }}" alt="ፊርማ" style="max-height:36px;border:1px solid #dfe5df;border-radius:4px;margin-top:.2rem"></div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">ምንም አስተያየት አልተመዘገበም።</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pager">{{ $feedbacks->links() }}</div>
</div>
@endsection
