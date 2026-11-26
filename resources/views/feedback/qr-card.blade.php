@extends('layouts.app')
@section('title', 'የQR ኮድ ካርድ')
@section('content')
<h1>የአርሶ አደር አስተያየት QR ኮድ ካርድ</h1>

<div class="card" style="max-width:460px;text-align:center">
    <h2>{{ $expert->name }}</h2>
    <p class="muted">{{ $expert->location?->name_am }} / {{ $expert->location?->parent?->name_am }}</p>
    <div style="margin:1rem auto">{!! $svg !!}</div>
    <p>አርሶ አደሩ ስልኩን በመጠቀም ይህን QR ኮድ ስካን አድርጎ ከ1–5 ደረጃና ፊርማ ማስገባት ይችላል።</p>
    <p class="muted" style="font-size:.82rem;word-break:break-all"><a href="{{ $url }}">{{ $url }}</a></p>
    <div class="actions" style="justify-content:center">
        <button class="btn" type="button" onclick="window.print()">🖨 አትም</button>
        <a class="btn secondary" href="{{ route('feedback.index') }}">← ተመለስ</a>
    </div>
</div>
@endsection
