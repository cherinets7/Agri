@extends('layouts.app')
@section('title', 'ሳምንታዊ ክንውን ማስገቢያ')
@section('content')
<h1>ሳምንታዊ ክንውን ማስገቢያ — {{ $woreda?->name_am }}</h1>

@if($report->exists && $report->status === App\Enums\WeeklyPlanStatus::Returned)
    <div class="errors"><strong>ሪፖርቱ ተመልሷል።</strong> ምክንያት፦ {{ $report->return_reason }}</div>
@endif

@unless($report->exists)
    <form method="GET" action="{{ route('weekly.create') }}" class="card filters">
        <x-ethiopian-date-input name="week" id="week" label="ሳምንት (የሳምንቱ ማንኛውም ቀን)" :max="today()->toDateString()" :value="$report->week_start->toDateString()" />
        <div><button class="btn secondary" type="submit">ሳምንት ቀይር</button></div>
    </form>
@endunless

<p class="muted">
    ሳምንት፦ <strong>{{ et_date($report->week_start) }} – {{ et_date($report->weekEnd()) }}</strong>
    ({{ $report->week_start->toDateString() }}) · {{ $report->fiscal_year }} ዓ/ም።
    በዚህ ሳምንት የተከናወነውን ብቻ ያስገቡ (ድምሩ ራስ-ሰር ይደመራል)። ባዶ = አልተከናወነም/አልተመዘገበም፤ 0 = ዜሮ።
</p>

<form method="POST" action="{{ $report->exists ? route('weekly.update', $report) : route('weekly.store') }}" data-offline-draft>
    @csrf
    @if($report->exists) @method('PUT') @else <input type="hidden" name="week_start" value="{{ $report->week_start->toDateString() }}"> @endif

    <div class="card table-wrap">
        <table>
            <thead><tr><th>ተ/ቁ</th><th>ተግባር</th><th>መለኪያ</th><th class="num">የወረዳ ዕቅድ</th><th class="num">እስካሁን (ድምር)</th><th class="num">የዚህ ሳምንት ክንውን</th></tr></thead>
            <tbody>
            @foreach($goals as $goal)
                <tr><td colspan="6" class="goal-head"><strong>{{ $goal->number }}. {{ $goal->name_am }}</strong></td></tr>
                @foreach($goal->activities as $a)
                    <tr>
                        <td></td>
                        <td>{{ $a->name_am }}</td>
                        <td>{{ $a->unit->label() }}</td>
                        <td class="num">{{ qty($targets[$a->id] ?? null) }}</td>
                        <td class="num">{{ qty($before[$a->id] ?? null) }}</td>
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

    <div class="card">
        <label for="notes">ማስታወሻ (አማራጭ)</label>
        <textarea id="notes" name="notes">{{ old('notes', $report->notes) }}</textarea>
        <x-signature-pad name="signature_data" label="የወረዳ ዲጂታል ፊርማ (አማራጭ)" :existing="$report->signature_data" />
        <div class="actions">
            <button class="btn secondary" type="submit" name="action" value="draft">አስቀምጥ (ረቂቅ)</button>
            <button class="btn" type="submit" name="action" value="submit" onclick="return confirm('መረጃው ትክክል መሆኑን አረጋግጠዋል? ለዞን ይላካል።')">አረጋግጥና ለዞን ላክ</button>
            <a href="{{ route('weekly.index') }}">← ወደ ዝርዝር</a>
        </div>
    </div>
</form>
@endsection
