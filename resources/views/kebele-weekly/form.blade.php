@extends('layouts.app')
@section('title', 'የቀበሌ ሳምንታዊ ክንውን')
@section('content')
<h1>የቀበሌ ሳምንታዊ ክንውን — {{ $kebele?->name_am }}</h1>

<form method="GET" action="{{ route('kebele-weekly.edit') }}" class="card filters">
    <x-ethiopian-date-input name="week" id="kw-week" label="ሳምንት (የሳምንቱ ማንኛውም ቀን)" :max="today()->toDateString()" :value="$week->toDateString()" />
    <div><button class="btn secondary" type="submit">ሳምንት ቀይር</button></div>
</form>

<p class="muted">
    ሳምንት፦ <strong>{{ et_date($week) }} – {{ et_date($week->copy()->addDays(6)) }}</strong> ({{ $week->toDateString() }}).
    በዚህ ሳምንት በቀበሌዎ የተከናወነውን ብቻ ያስገቡ። ወረዳው የቀበሌዎችን ድምር ተመልክቶ ኦፊሴላዊውን ሳምንታዊ ሪፖርት ለዞን ይልካል — የቀበሌ አሃዝ ብቻውን ወደ ማትሪክስ አይደመርም።
    ባዶ = አልተከናወነም/አልተመዘገበም፤ 0 = ዜሮ።
</p>

<form method="POST" action="{{ route('kebele-weekly.update') }}">
    @csrf @method('PUT')
    <input type="hidden" name="week_start" value="{{ $week->toDateString() }}">
    <div class="card table-wrap">
        <table>
            <thead><tr><th>ተ/ቁ</th><th>ተግባር</th><th>መለኪያ</th><th class="num">የዚህ ሳምንት ክንውን</th></tr></thead>
            <tbody>
            @foreach($goals as $goal)
                <tr><td colspan="4" class="goal-head"><strong>{{ $goal->number }}. {{ $goal->name_am }}</strong></td></tr>
                @foreach($goal->activities as $a)
                    <tr>
                        <td></td>
                        <td>{{ $a->name_am }}</td>
                        <td>{{ $a->unit->label() }}</td>
                        <td class="num" style="min-width:9rem">
                            <input type="number" step="any" min="0" name="values[{{ $a->id }}]" aria-label="{{ $a->name_am }}"
                                   value="{{ old('values.'.$a->id, isset($current[$a->id]) ? qty($current[$a->id], '') : '') }}">
                        </td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="actions"><button class="btn" type="submit">አስቀምጥ</button></div>
</form>
@endsection
