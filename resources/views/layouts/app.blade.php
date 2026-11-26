<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2f7d32">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>@yield('title', 'ዳሽቦርድ') · {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div id="offline-banner">ኢንተርኔት ተቋርጧል — የተሞሉ ረቂቆች በመሣሪያዎ ላይ በጊዜያዊነት ይቀመጣሉ።</div>
@auth
@php($unreadNotifs = auth()->user()->unreadNotifications()->count())
<header class="top">
    <div class="wrap">
        <a class="brand" href="{{ route('dashboard') }}">🌾 የየም ዞን ግብርና ክትትል</a>
        <nav>
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>ዳሽቦርድ</a>
            <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.index', 'reports.show', 'reports.edit')])>ሪፖርቶች</a>
            <a href="{{ route('periodic.hub') }}" @class(['active' => request()->routeIs('periodic.*')])>ሪፖርቶች (#2–#8)</a>
            <a href="{{ route('sectors.hub') }}" @class(['active' => request()->routeIs('sectors.*')])>የዘርፍ ቅጾች</a>
            <a href="{{ route('summaries.index') }}" @class(['active' => request()->routeIs('summaries.*')])>ሳምንታዊ ማጠቃለያ</a>
            <a href="{{ route('feedback.index') }}" @class(['active' => request()->routeIs('feedback.*')])>አስተያየት (QR)</a>
            <a href="{{ route('comparisons.index') }}" @class(['active' => request()->routeIs('comparisons.*')])>ንጽጽር</a>
            <a href="{{ route('annual.index') }}" @class(['active' => request()->routeIs('annual.*')])>ዓመታዊ ሪፖርት</a>
            @can('create', App\Models\ExtensionReport::class)
                <a href="{{ route('reports.create') }}" @class(['active' => request()->routeIs('reports.create')])>+ አዲስ ዕለታዊ ሪፖርት</a>
            @endcan
            @can('view-matrix')
                <a href="{{ route('matrix.index') }}" @class(['active' => request()->routeIs('matrix.*')])>ሳምንታዊ ማትሪክስ</a>
                <a href="{{ route('weekly.index') }}" @class(['active' => request()->routeIs('weekly.*')])>ሳምንታዊ ክንውን</a>
                <a href="{{ route('map.index') }}" @class(['active' => request()->routeIs('map.*')])>ካርታ</a>
            @endcan
            @can('manage-plan')
                <a href="{{ route('plan.edit') }}" @class(['active' => request()->routeIs('plan.*')])>ዕቅድ</a>
            @endcan
            @if(auth()->user()->isZone() || auth()->user()->isWoreda())
                <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>ተጠቃሚዎች</a>
                <a href="{{ route('locations.index') }}" @class(['active' => request()->routeIs('locations.*')])>አካባቢዎች</a>
            @endif
            <a href="{{ route('notifications.index') }}" @class(['active' => request()->routeIs('notifications.*')])>
                🔔 ማሳሰቢያ @if($unreadNotifs > 0)<span class="notif-badge">{{ $unreadNotifs }}</span>@endif
            </a>
        </nav>
        <div class="who">
            <span>{{ auth()->user()->name }} · {{ auth()->user()->role->label() }} ({{ auth()->user()->location?->name_am }})</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">ውጣ</button></form>
        </div>
    </div>
</header>
@endauth
<main>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="errors"><strong>እባክዎ ስህተቶቹን ያስተካክሉ፦</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
<script src="{{ asset('js/agri-ui.js') }}" defer></script>
</body>
</html>
