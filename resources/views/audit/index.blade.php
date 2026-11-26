@extends('layouts.app')
@section('title', 'የኦዲት መዝገብ')
@section('content')
<h1>የኦዲት መዝገብ</h1>
<p class="muted">ማን፣ መቼ፣ ምን እንደፈጠረ/እንዳሻሻለ/እንደሰረዘ እና የመግቢያ ሙከራዎች። የይለፍ ቃልና ፊርማ ዋጋ አይመዘገብም።</p>

<form method="GET" class="card filters">
    <div>
        <label for="a-event">ክስተት</label>
        <select id="a-event" name="event">
            <option value="">ሁሉም</option>
            @foreach($events as $k => $label)<option value="{{ $k }}" @selected(request('event') === $k)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="a-type">ዓይነት</label>
        <select id="a-type" name="type">
            <option value="">ሁሉም</option>
            @foreach($types as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="a-user">ተጠቃሚ</label>
        <select id="a-user" name="user">
            <option value="">ሁሉም</option>
            @foreach($users as $u)<option value="{{ $u->id }}" @selected((int) request('user') === $u->id)>{{ $u->name }}</option>@endforeach
        </select>
    </div>
    <div><label for="a-from">ከ</label><input id="a-from" type="date" name="from" value="{{ request('from') }}"></div>
    <div><label for="a-to">እስከ</label><input id="a-to" type="date" name="to" value="{{ request('to') }}"></div>
    <div><button class="btn" type="submit">አጣራ</button></div>
</form>

<div class="card table-wrap">
    <table>
        <thead><tr><th>ጊዜ</th><th>ተጠቃሚ</th><th>ክስተት</th><th>ዓይነት</th><th>ማጠቃለያ / ለውጦች</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                <td>{{ $log->user?->name ?? '—' }}</td>
                <td><span class="badge {{ ['created' => 'green', 'updated' => 'amber', 'deleted' => 'red', 'login_failed' => 'red'][$log->event] ?? 'gray' }}">{{ $log->eventLabel() }}</span></td>
                <td>{{ $log->typeLabel() }}@if($log->auditable_id) #{{ $log->auditable_id }}@endif</td>
                <td>
                    @if($log->summary){{ $log->summary }}<br>@endif
                    @if($log->event === 'updated' && $log->changes)
                        @foreach($log->changes as $field => $c)
                            <small><code>{{ $field }}</code>: {{ is_array($c['old'] ?? null) ? json_encode($c['old']) : ($c['old'] ?? '∅') }} → {{ is_array($c['new'] ?? null) ? json_encode($c['new']) : ($c['new'] ?? '∅') }}</small><br>
                        @endforeach
                    @elseif($log->changes)
                        <small class="muted">{{ \Illuminate\Support\Str::limit(json_encode($log->changes, JSON_UNESCAPED_UNICODE), 160) }}</small>
                    @endif
                </td>
                <td class="muted">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">ምንም መዝገብ የለም።</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
