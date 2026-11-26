@extends('layouts.app')
@section('title', 'ሪፖርት ዝርዝር')
@section('content')
@php
    $services = config('extension.service_types');
    $techniques = config('extension.techniques');
@endphp
<h1>ዕለታዊ ሪፖርት — {{ et_date($report->report_date) }}</h1>

<div class="grid cols-2">
    <div class="card">
        <dl class="kv">
            <dt>ሁኔታ</dt><dd><span class="badge {{ $report->status->badge() }}">{{ $report->status->label() }}</span></dd>
            <dt>ቀን</dt><dd>{{ et_date($report->report_date) }} ({{ $report->report_date->toDateString() }})</dd>
            <dt>ቀበሌ / ወረዳ</dt><dd>{{ $report->location?->name_am }} / {{ $report->location?->parent?->name_am }}</dd>
            <dt>ባለሙያ</dt><dd>{{ $report->user?->name }}</dd>
            <dt>ጠቅላላ አርሶ አደር</dt><dd><strong>{{ $report->farmers_total }}</strong> <span class="badge {{ $report->band()->badge() }}">{{ $report->band()->label() }}</span></dd>
            <dt>አዲስ / ተደጋጋሚ</dt><dd>{{ $report->new_farmers }} / {{ $report->repeat_farmers }}</dd>
            <dt>ሴት / ወጣት (18–35)</dt><dd>{{ $report->female_farmers }} / {{ $report->youth_farmers }}</dd>
            <dt>ቆይታ</dt><dd>{{ $report->duration_minutes }} ደቂቃ</dd>
            <dt>የአገልግሎት ዓይነት</dt><dd>{{ collect($report->service_types)->map(fn ($k) => $services[$k] ?? $k)->implode('፣ ') ?: '—' }}</dd>
            <dt>ቴክኒክ</dt><dd>{{ collect($report->techniques)->map(fn ($k) => $techniques[$k] ?? $k)->implode('፣ ') ?: '—' }}</dd>
            <dt>ማስታወሻ</dt><dd>{{ $report->notes ?: '—' }}</dd>
            @if($report->declared_at)<dt>ማረጋገጫ</dt><dd>{{ $report->user?->name }} — {{ $report->declared_at->format('Y-m-d H:i') }}@if($report->signature_data)<img class="sig-img" src="{{ $report->signature_data }}" alt="የባለሙያ ፊርማ">@endif</dd>@endif
            @if($report->verified_signature_data)<dt>የወረዳ ፊርማ</dt><dd>{{ $report->verifier?->name }}<img class="sig-img" src="{{ $report->verified_signature_data }}" alt="የወረዳ ፊርማ"></dd>@endif
            @if($report->approved_signature_data)<dt>የዞን ፊርማ</dt><dd>{{ $report->approver?->name }}<img class="sig-img" src="{{ $report->approved_signature_data }}" alt="የዞን ፊርማ"></dd>@endif
            @if($report->status === App\Enums\ReportStatus::Returned)<dt>የመመለስ ምክንያት</dt><dd>{{ $report->return_reason }}</dd>@endif
        </dl>
    </div>

    <div class="card">
        <strong>የሁኔታ ታሪክ</strong>
        <ul class="timeline" style="margin-top:.6rem">
            @foreach($report->actions as $a)
                <li>
                    <strong>{{ $a->actionLabel() }}</strong> — {{ $a->user?->name }}
                    <div class="muted">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
                    @if($a->note)<div>{{ $a->note }}</div>@endif
                    @if($a->signature_data)<img class="sig-img" src="{{ $a->signature_data }}" alt="ፊርማ">@endif
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="card">
    <div class="actions" style="margin-top:0">
        @can('update', $report)
            <a class="btn secondary" href="{{ route('reports.edit', $report) }}">አስተካክል</a>
        @endcan
        @can('submit', $report)
            <form method="POST" action="{{ route('reports.submit', $report) }}" onsubmit="return confirm('መረጃው ትክክል መሆኑን አረጋግጠዋል? ለወረዳ ይላካል።')">@csrf<button class="btn" type="submit">አረጋግጥና ላክ</button></form>
        @endcan
        @can('delete', $report)
            <form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('ረቂቁ ይሰረዝ?')">@csrf @method('DELETE')<button class="btn danger" type="submit">ሰርዝ</button></form>
        @endcan
        <a href="{{ route('reports.index') }}">← ወደ ዝርዝር</a>
    </div>

    @can('verify', $report)
        <h2>የወረዳ ማረጋገጫ</h2>
        <form method="POST" action="{{ route('reports.verify', $report) }}">
            @csrf
            <label for="note-v">ማስታወሻ (አማራጭ)</label>
            <input id="note-v" type="text" name="note">
            <x-signature-pad name="signature_data" label="የወረዳ ማረጋገጫ ፊርማ (አማራጭ)" />
            <div class="actions"><button class="btn" type="submit">✔ አረጋግጥ → ወደ ዞን</button></div>
        </form>
    @endcan
    @can('approve', $report)
        <h2>የዞን ማጽደቅ</h2>
        <form method="POST" action="{{ route('reports.approve', $report) }}">
            @csrf
            <label for="note-a">ማስታወሻ (አማራጭ)</label>
            <input id="note-a" type="text" name="note">
            <x-signature-pad name="signature_data" label="የዞን ማጽደቂያ ፊርማ (አማራጭ)" />
            <div class="actions"><button class="btn" type="submit">✔ አጽድቅ</button></div>
        </form>
    @endcan
    @can('return', $report)
        <h2>ወደ ቀበሌ መልስ</h2>
        <form method="POST" action="{{ route('reports.return', $report) }}">
            @csrf
            <label for="reason">የመመለስ ምክንያት (ግዴታ)</label>
            <textarea id="reason" name="reason" required></textarea>
            <div class="actions"><button class="btn warn" type="submit">↩ መልስ</button></div>
        </form>
    @endcan
</div>
@endsection
