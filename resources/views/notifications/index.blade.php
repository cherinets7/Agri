@extends('layouts.app')
@section('title', 'ማሳሰቢያዎች እና ማስታወቂያዎች')
@section('content')
@php($user = auth()->user())
<h1>🔔 ማሳሰቢያዎች <span class="muted" style="font-weight:400">({{ $unreadCount }} ያልተነበቡ)</span></h1>

<div class="card">
    <div class="actions" style="margin-top:0">
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn secondary" type="submit">ሁሉንም እንደተነበበ ምልክት አድርግ</button>
            </form>
        @endif

        @if($user->isZone() || $user->isWoreda())
            <form method="POST" action="{{ route('notifications.remind-missing') }}" class="actions" style="margin-top:0">
                @csrf
                <label style="margin:0;display:flex;align-items:center;gap:.35rem;font-weight:400">
                    <input type="checkbox" name="send_email" value="1" checked> ኢሜይል ማሳሰቢያም ላክ
                </label>
                <button class="btn" type="submit">ያልተላከ ሪፖርት ማሳሰቢያ ላክ ({{ et_date($lastWorkDay) }})</button>
            </form>
        @endif
    </div>
    <p class="muted" style="margin:.6rem 0 0">
        ያልተላኩ ዕለታዊ/ሳምንታዊ ሪፖርቶች እና የተመለሱ/የጸደቁ ሪፖርቶች ማሳሰቢያዎች እዚህ ይታያሉ።
        ኢሜይል ሲዋቀር (<code>MAIL_MAILER</code>) ወደ ተጠቃሚው ኢሜይልም ይላካል (ያለ ተጨማሪ የSMS ወጪ)።
    </p>
</div>

<div class="card">
    @forelse($notifications as $n)
        <div style="padding:.75rem 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:1rem;align-items:flex-start">
            <div>
                <div>
                    @if(! $n->read_at)<span class="badge blue">አዲስ</span>@endif
                    <strong>{{ $n->data['title'] ?? 'ማሳሰቢያ' }}</strong>
                </div>
                <div style="margin-top:.2rem">{{ $n->data['message'] ?? '' }}</div>
                <div class="muted" style="font-size:.85rem;margin-top:.2rem">{{ $n->created_at?->format('Y-m-d H:i') }}</div>
            </div>
            <div style="white-space:nowrap">
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                    @csrf
                    <button class="btn sm {{ $n->read_at ? 'secondary' : '' }}" type="submit">
                        {{ ! empty($n->data['url']) ? 'ክፈት' : 'ተነቧል' }}
                    </button>
                </form>
            </div>
        </div>
    @empty
        <p class="muted">ምንም ማሳሰቢያ የለም።</p>
    @endforelse
</div>

<div class="pager">{{ $notifications->links() }}</div>
@endsection
