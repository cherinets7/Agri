@extends('layouts.app')
@section('title', 'ተጠቃሚዎች')
@section('content')
<h1>ተጠቃሚዎች</h1>
<div class="actions" style="margin:0 0 1rem"><a class="btn" href="{{ route('users.create') }}">+ አዲስ ተጠቃሚ</a></div>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ስም</th><th>ኢሜይል</th><th>ስልክ</th><th>ሚና</th><th>አካባቢ</th><th>ሁኔታ</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->phone }}</td>
                <td>{{ $u->role->label() }}</td>
                <td>{{ $u->location?->name_am }} @if($u->location?->parent)<span class="muted">({{ $u->location->parent->name_am }})</span>@endif</td>
                <td><span class="badge {{ $u->is_active ? 'green' : 'gray' }}">{{ $u->is_active ? 'ንቁ' : 'ታግዷል' }}</span></td>
                <td><a class="btn sm secondary" href="{{ route('users.edit', $u) }}">አስተካክል</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pager">{{ $users->links() }}</div>
</div>
@endsection
