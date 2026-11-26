@extends('layouts.app')
@section('title', $user->exists ? 'ተጠቃሚ አስተካክል' : 'አዲስ ተጠቃሚ')
@section('content')
<h1>{{ $user->exists ? 'ተጠቃሚ አስተካክል' : 'አዲስ ተጠቃሚ' }}</h1>
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="row">
        <div><label for="name">ሙሉ ስም</label><input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label for="email">ኢሜይል</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div><label for="phone">ስልክ</label><input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}"></div>
        <div>
            <label for="password">የይለፍ ቃል @if($user->exists)<span class="muted">(ለመቀየር ብቻ)</span>@endif</label>
            <input id="password" type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
        </div>
    </div>
    <div class="row">
        <div>
            <label for="role">ሚና</label>
            <select id="role" name="role" required>
                @foreach($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="location_id">አካባቢ</label>
            <select id="location_id" name="location_id" required>
                @foreach($locationsByRole as $role => $locations)
                    <optgroup label="{{ $roles[$role] }}" data-role="{{ $role }}">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" data-role="{{ $role }}" @selected((int) old('location_id', $user->location_id) === $loc->id)>
                                {{ $loc->name_am }}@if($loc->parent) ({{ $loc->parent->name_am }})@endif
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
    </div>
    <label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> ንቁ መለያ</label>
    <div class="actions">
        <button class="btn" type="submit">አስቀምጥ</button>
        <a href="{{ route('users.index') }}">ተመለስ</a>
    </div>
</form>
<script>
(function () {
    const role = document.getElementById('role'), loc = document.getElementById('location_id');
    const all = Array.from(loc.querySelectorAll('option')).map(o => ({ o, g: o.parentNode }));
    function sync() {
        all.forEach(({ o, g }) => {
            const show = g.dataset.role === role.value;
            g.hidden = !show; o.disabled = !show; o.hidden = !show;
        });
        const sel = loc.options[loc.selectedIndex];
        if (!sel || sel.disabled) { const first = Array.from(loc.options).find(o => !o.disabled); if (first) first.selected = true; }
    }
    role.addEventListener('change', sync); sync();
})();
</script>
@endsection
