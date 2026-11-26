@extends('layouts.app')
@section('title', 'ሳምንታዊ ማጠቃለያ')
@section('content')
<h1>ሳምንታዊ የባለሙያ ማጠቃለያ — {{ et_date($summary->week_start) }}</h1>

<div class="grid cols-2">
    <div class="card">
        <dl class="kv">
            <dt>ሁኔታ</dt><dd><span class="badge {{ $summary->status->badge() }}">{{ $summary->status->label() }}</span></dd>
            <dt>ሳምንት</dt><dd>{{ et_date($summary->week_start) }} – {{ et_date($summary->week_start->copy()->addDays(6)) }}</dd>
            <dt>ባለሙያ</dt><dd>{{ $summary->user?->name }}</dd>
            <dt>ቀበሌ / ወረዳ</dt><dd>{{ $summary->location?->name_am }} / {{ $summary->location?->parent?->name_am }}</dd>
            <dt>ቀናት / አርሶ አደር</dt><dd>{{ $summary->days_reported }} ቀናት · <strong>{{ $summary->farmers_total }}</strong> አርሶ አደር (አማካይ {{ $summary->avg_per_day }}/ቀን)</dd>
            <dt>ሴት / ወጣት</dt><dd>{{ $summary->female_farmers }} / {{ $summary->youth_farmers }}</dd>
            <dt>የአርሶ አደር አስተያየት</dt><dd>{{ $summary->feedback_avg ? "{$summary->feedback_avg}/5 ({$summary->feedback_count} አስተያየት)" : '—' }}</dd>
            <dt>ዋና ሥራዎች</dt><dd>{{ $summary->highlights }}</dd>
            <dt>ችግሮች</dt><dd>{{ $summary->challenges ?: '—' }}</dd>
            <dt>የቀጣይ ሳምንት ዕቅድ</dt><dd>{{ $summary->next_week_plan }}</dd>
            @if($summary->status === App\Enums\ReportStatus::Returned)<dt>የመመለስ ምክንያት</dt><dd>{{ $summary->return_reason }}</dd>@endif
        </dl>
    </div>

    <div class="card">
        <strong>የሁኔታ ታሪክ</strong>
        <ul class="timeline" style="margin-top:.6rem">
            @foreach($summary->actions as $a)
                <li>
                    <strong>{{ $a->actionLabel() }}</strong> — {{ $a->user?->name }}
                    <div class="muted">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
                    @if($a->note)<div>{{ $a->note }}</div>@endif
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="card">
    <div class="actions" style="margin-top:0">
        @can('update', $summary)<a class="btn secondary" href="{{ route('summaries.edit', $summary) }}">አስተካክል</a>@endcan
        @can('submit', $summary)
            <form method="POST" action="{{ route('summaries.submit', $summary) }}">@csrf<button class="btn" type="submit">አረጋግጥና ላክ</button></form>
        @endcan
        @can('delete', $summary)
            <form method="POST" action="{{ route('summaries.destroy', $summary) }}">@csrf @method('DELETE')<button class="btn danger" type="submit">ሰርዝ</button></form>
        @endcan
        <a href="{{ route('summaries.index') }}">← ወደ ዝርዝር</a>
    </div>

    @can('verify', $summary)
        <h2>የወረዳ ማረጋገጫ</h2>
        <form method="POST" action="{{ route('summaries.verify', $summary) }}">@csrf
            <input type="text" name="note" placeholder="ማስታወሻ (አማራጭ)">
            <div class="actions"><button class="btn" type="submit">✔ አረጋግጥ → ወደ ዞን</button></div>
        </form>
    @endcan
    @can('approve', $summary)
        <h2>የዞን ማጽደቅ</h2>
        <form method="POST" action="{{ route('summaries.approve', $summary) }}">@csrf
            <input type="text" name="note" placeholder="ማስታወሻ (አማራጭ)">
            <div class="actions"><button class="btn" type="submit">✔ አጽድቅ</button></div>
        </form>
    @endcan
    @can('return', $summary)
        <h2>ወደ ቀበሌ መልስ</h2>
        <form method="POST" action="{{ route('summaries.return', $summary) }}">@csrf
            <textarea name="reason" required placeholder="ምክንያት (ግዴታ)"></textarea>
            <div class="actions"><button class="btn warn" type="submit">↩ መልስ</button></div>
        </form>
    @endcan
</div>
@endsection
