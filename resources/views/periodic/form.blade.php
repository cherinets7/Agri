@extends('layouts.app')
@section('title', ($report->exists ? 'አስተካክል · ' : 'አዲስ · ').$reportType->label())
@section('content')
@php($user = auth()->user())
<h1>#{{ $reportType->number() }} · {{ $reportType->label() }} — {{ $report->exists ? 'አስተካክል' : 'አዲስ ሪፖርት' }}</h1>
<p class="muted">{{ $reportType->description() }}</p>

@if($report->exists && $report->status === App\Enums\ReportStatus::Returned)
    <div class="errors"><strong>ሪፖርቱ ተመልሷል።</strong> ምክንያት፦ {{ $report->return_reason }}</div>
@endif

<form method="POST" action="{{ $report->exists ? route('periodic.update', $report) : route('periodic.store', $reportType->value) }}" class="card">
    @csrf
    @if($report->exists) @method('PUT') @endif

    <div class="row">
        <div>
            <label>ቀበሌ / ወረዳ</label>
            <input type="text" value="{{ ($report->location ?? $user->location)?->name_am }} / {{ ($report->location ?? $user->location)?->parent?->name_am }}" disabled>
        </div>
        <div>
            <label for="report_date">ቀን</label>
            <input id="report_date" type="date" name="report_date" max="{{ today()->toDateString() }}" value="{{ old('report_date', $report->report_date?->toDateString()) }}" required>
        </div>
        <div>
            <label for="period_type">የሪፖርት ጊዜ</label>
            <select id="period_type" name="period_type" required>
                @foreach($granularities as $k => $label)
                    <option value="{{ $k }}" @selected(old('period_type', $report->period_type?->value ?? 'weekly') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <h2>የሪፖርት መረጃዎች</h2>
    <div class="row">
        @foreach($reportType->fields() as $key => $field)
            @continue(! empty($field['computed']))
            @php($val = old("payload.{$key}", $report->payload[$key] ?? ($field['type'] === 'int' || $field['type'] === 'decimal' ? '0' : '')))
            <div>
                <label for="f-{{ $key }}">{{ $field['label'] }}</label>
                @if($field['type'] === 'select')
                    <select id="f-{{ $key }}" name="payload[{{ $key }}]" @required(! empty($field['required']))>
                        @foreach($field['options'] as $optVal => $optLabel)
                            <option value="{{ $optVal }}" @selected((string) $val === (string) $optVal)>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @elseif($field['type'] === 'int')
                    <input id="f-{{ $key }}" type="number" min="0" step="1" name="payload[{{ $key }}]" value="{{ $val }}" @required(! empty($field['required']))>
                @elseif($field['type'] === 'decimal')
                    <input id="f-{{ $key }}" type="number" min="0" step="any" name="payload[{{ $key }}]" value="{{ $val }}" @required(! empty($field['required']))>
                @else
                    <input id="f-{{ $key }}" type="text" name="payload[{{ $key }}]" value="{{ $val }}" @required(! empty($field['required']))>
                @endif
            </div>
        @endforeach
    </div>

    <label for="notes">ማስታወሻ (አማራጭ)</label>
    <textarea id="notes" name="notes">{{ old('notes', $report->notes) }}</textarea>

    <div class="actions">
        <button class="btn secondary" type="submit" name="action" value="draft">አስቀምጥ (ረቂቅ)</button>
        <button class="btn" type="submit" name="action" value="submit" onclick="return confirm('መረጃው ትክክል መሆኑን አረጋግጠዋል? ለወረዳ ይላካል።')">አረጋግጥና ላክ</button>
        <a href="{{ route('periodic.index', $reportType->value) }}">← ወደ ዝርዝር</a>
    </div>
</form>
@endsection
