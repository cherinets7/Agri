@extends('layouts.app')
@section('title', 'ስትራተጂክ ግቦችና ተግባራት')
@section('content')
<h1>ስትራተጂክ ግቦችና ተግባራት (የማትሪክስ ረድፎች)</h1>

@foreach($goals as $goal)
    <div class="card">
        <form method="POST" action="{{ route('plan.goals.update', $goal) }}" class="filters">
            @csrf @method('PUT')
            <div style="min-width:80px;max-width:90px"><label>ተ/ቁ</label><input type="number" name="number" min="1" value="{{ $goal->number }}" required></div>
            <div style="flex:1;min-width:260px"><label>ስትራተጂክ ግብ</label><input type="text" name="name_am" value="{{ $goal->name_am }}" required></div>
            <div><label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked($goal->is_active)> ንቁ</label></div>
            <div><button class="btn sm secondary" type="submit">አስቀምጥ</button></div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>ተግባር</th><th>መለኪያ</th><th>ቅደም ተከተል</th><th>ንቁ</th><th></th></tr></thead>
                <tbody>
                @foreach($goal->activities as $a)
                    <tr>
                        <td style="min-width:320px"><input type="text" name="name_am" form="act-{{ $a->id }}" value="{{ $a->name_am }}" required></td>
                        <td><select name="unit" form="act-{{ $a->id }}">@foreach($units as $k => $label)<option value="{{ $k }}" @selected($a->unit->value === $k)>{{ $label }}</option>@endforeach</select></td>
                        <td style="width:110px"><input type="number" name="sort_order" form="act-{{ $a->id }}" min="0" value="{{ $a->sort_order }}" required></td>
                        <td><input type="checkbox" name="is_active" form="act-{{ $a->id }}" value="1" @checked($a->is_active)></td>
                        <td>
                            <form id="act-{{ $a->id }}" method="POST" action="{{ route('plan.activities.update', $a) }}">@csrf @method('PUT')<button class="btn sm secondary" type="submit">አስቀምጥ</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('plan.activities.store') }}" class="filters" style="margin-top:.8rem">
            @csrf
            <input type="hidden" name="strategic_goal_id" value="{{ $goal->id }}">
            <div style="flex:1;min-width:260px"><label>አዲስ ተግባር</label><input type="text" name="name_am" required></div>
            <div><label>መለኪያ</label><select name="unit">@foreach($units as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div><button class="btn sm" type="submit">+ ጨምር</button></div>
        </form>
    </div>
@endforeach

<h2>አዲስ ስትራተጂክ ግብ</h2>
<form method="POST" action="{{ route('plan.goals.store') }}" class="card filters">
    @csrf
    <div style="max-width:90px"><label>ተ/ቁ</label><input type="number" name="number" min="1" value="{{ ($goals->max('number') ?? 0) + 1 }}" required></div>
    <div style="flex:1;min-width:260px"><label>ስትራተጂክ ግብ</label><input type="text" name="name_am" required></div>
    <div><button class="btn" type="submit">+ ጨምር</button></div>
</form>
<div class="actions"><a href="{{ route('plan.edit') }}">ወደ ዓመታዊ ዕቅድ →</a> <a href="{{ route('matrix.index') }}">ወደ ማትሪክስ →</a></div>
@endsection
