@extends('layouts.app')
@section('title', 'ግባ')
@section('content')
<div class="login card">
    <h1>🌾 የየም ዞን ግብርና መምሪያ<br><small class="muted" style="font-size:.9rem">የክትትልና ሪፖርት ሲስተም</small></h1>
    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <label for="email">ኢሜይል</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label for="password">የይለፍ ቃል</label>
        <input id="password" type="password" name="password" required>
        <label style="font-weight:400"><input type="checkbox" name="remember" value="1"> አስታውሰኝ</label>
        <div class="actions"><button class="btn" type="submit">ግባ</button></div>
    </form>
</div>
@endsection
