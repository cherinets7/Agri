@extends('layouts.app')
@section('title', $summary->exists ? 'ሳምንታዊ ማጠቃለያ አስተካክል' : 'አዲስ ሳምንታዊ ማጠቃለያ')
@section('content')
<h1>{{ $summary->exists ? 'ሳምንታዊ ማጠቃለያ አስተካክል' : 'አዲስ ሳምንታዊ የባለሙያ ማጠቃለያ' }}</h1>

@if($summary->exists && $summary->status === App\Enums\ReportStatus::Returned)
    <div class="errors"><strong>ማጠቃለያው ተመልሷል።</strong> ምክንያት፦ {{ $summary->return_reason }}</div>
@endif

<div class="grid cols-4">
    <div class="card stat"><div class="n">{{ $summary->days_reported }}</div><div class="l">ሪፖርት የተላከባቸው ቀናት</div></div>
    <div class="card stat"><div class="n">{{ $summary->farmers_total }}</div><div class="l">ጠቅላላ አርሶ አደር (ሴት {{ $summary->female_farmers }} / ወጣት {{ $summary->youth_farmers }})</div></div>
    <div class="card stat"><div class="n">{{ $summary->avg_per_day }}</div><div class="l">አማካይ/ቀን</div></div>
    <div class="card stat"><div class="n">{{ $summary->feedback_avg ? "{$summary->feedback_avg}/5" : '—' }}</div><div class="l">የአርሶ አደር አስተያየት ({{ $summary->feedback_count }})</div></div>
</div>

<form method="POST" action="{{ $summary->exists ? route('summaries.update', $summary) : route('summaries.store') }}" class="card" data-offline-draft>
    @csrf
    @if($summary->exists) @method('PUT') @endif

    <div class="row">
        <x-ethiopian-date-input name="week_start" id="week_start" label="የሳምንቱ መጀመሪያ (ሰኞ)" :max="today()->toDateString()" :value="old('week_start', $summary->week_start?->toDateString())" :required="true" />
    </div>

    <label for="highlights">የሳምንቱ ዋና ዋና ሥራዎች እና ስኬቶች</label>
    <textarea id="highlights" name="highlights" required>{{ old('highlights', $summary->highlights) }}</textarea>

    <label for="challenges">ያጋጠሙ ችግሮችና መፍትሔዎች (አማራጭ)</label>
    <textarea id="challenges" name="challenges">{{ old('challenges', $summary->challenges) }}</textarea>

    <label for="next_week_plan">የሚቀጥለው ሳምንት ዕቅድ</label>
    <textarea id="next_week_plan" name="next_week_plan" required>{{ old('next_week_plan', $summary->next_week_plan) }}</textarea>

    <x-signature-pad name="signature_data" label="የባለሙያ ዲጂታል ፊርማ (አማራጭ)" :existing="$summary->signature_data" />

    <div class="actions">
        <button class="btn secondary" type="submit" name="action" value="draft">አስቀምጥ (ረቂቅ)</button>
        <button class="btn" type="submit" name="action" value="submit">አረጋግጥና ላክ</button>
        <a href="{{ route('summaries.index') }}">← ወደ ዝርዝር</a>
    </div>
</form>
@endsection
