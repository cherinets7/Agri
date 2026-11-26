@extends('layouts.app')
@section('title', 'አካባቢዎች')
@section('content')
<h1>አካባቢዎች (ዞን → ወረዳ → ቀበሌ)</h1>
<p class="muted">
    <span class="badge amber">ናሙና — ሊስተካከል የሚችል</span> = ከዞኑ እውነተኛ ዝርዝር እስኪገባ የተመደበ ቁጥር ያለው ስም።
    <span class="badge red">ግምት — ያረጋግጡ</span> = ከምንጭ ሰነዱ አህጽሮት የተሰፋ ግምታዊ ስም።
    "ስም ቀይር" ሲጫኑ ምልክቱ ይወገዳል፤ ኮዱ (code) አይለወጥም።
</p>
@php($canRename = auth()->user()->can('manage-locations'))

<div class="grid cols-2">
    <div>
        @foreach($zones as $zone)
            <div class="card">
                <strong>ዞን፦ {{ $zone->name_am }}</strong>
                @foreach($zone->children as $woreda)
                    <div style="margin:.6rem 0 0 .8rem">
                        <strong>ወረዳ፦ {{ $woreda->name_am }}</strong>
                        @include('locations._flags', ['location' => $woreda])
                        @if(auth()->user()->isZone() && $woreda->id)@include('locations._rename', ['location' => $woreda])@endif
                        <ul style="margin:.2rem 0 0 1rem">
                            @forelse($woreda->children as $kebele)
                                <li>ቀበሌ፦ {{ $kebele->name_am }} <small class="muted">({{ $kebele->code }})</small>
                                    @include('locations._flags', ['location' => $kebele])
                                    @include('locations._rename', ['location' => $kebele])
                                </li>
                            @empty
                                <li class="muted">ቀበሌ የለም</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="card">
        <strong>አዲስ አካባቢ ጨምር</strong>
        <form method="POST" action="{{ route('locations.store') }}">
            @csrf
            <label for="parent_id">የበላይ አካባቢ (ዞን ከሆነ ወረዳ፤ ወረዳ ከሆነ ቀበሌ ይፈጠራል)</label>
            <select id="parent_id" name="parent_id" required>
                @foreach($parents as $p)
                    <option value="{{ $p->id }}">{{ $p->level->label() }}፦ {{ $p->name_am }}</option>
                @endforeach
            </select>
            <label for="name_am">ስም (አማርኛ)</label>
            <input id="name_am" type="text" name="name_am" required>
            <label for="name_en">ስም (እንግሊዝኛ)</label>
            <input id="name_en" type="text" name="name_en">
            <label for="code">ኮድ (አማራጭ)</label>
            <input id="code" type="text" name="code">
            <div class="actions"><button class="btn" type="submit">ጨምር</button></div>
        </form>
    </div>
</div>
@endsection
