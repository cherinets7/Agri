@extends('layouts.app')
@section('title', 'ፈቃዶች')
@section('content')
<h1>የሚና ፈቃዶች</h1>
<p class="muted">
    ዞኑ ለእያንዳንዱ ሚና የትኞቹ ተግባራት እንደሚፈቀዱ እዚህ ማስተካከል ይችላል። ይህ "ምን ማድረግ ይችላል" የሚለውን ብቻ ይቆጣጠራል፤
    የመዝገብ ደረጃ ህጎች (ቀበሌ የራሱን ረቂቅ ብቻ ያስተካክላል፣ ወረዳ የራሱን ቀበሌዎች ብቻ ያያል፣ የመጨረሻ ማጽደቅ የዞን ነው) ቋሚ ናቸው።
    ለውጦች በኦዲት መዝገብ ይመዘገባሉ። ነባሪው ከ <code>config/permissions.php</code> ነው።
</p>
<form method="POST" action="{{ route('permissions.update') }}">
    @csrf @method('PUT')
    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>ተግባር</th>
                @foreach($roles as $role)<th class="num">{{ $role->label() }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($capabilities as $key => $def)
                <tr>
                    <td>{{ $def['label'] }} <small class="muted"><code>{{ $key }}</code></small>@if($def['locked']) <span class="badge gray">ቋሚ</span>@endif</td>
                    @foreach($roles as $role)
                        @php($on = App\Support\Permissions::roleAllows($role, $key))
                        <td class="num">
                            <input type="checkbox" name="perm[{{ $role->value }}][{{ $key }}]" value="1" aria-label="{{ $role->label() }} — {{ $def['label'] }}"
                                   @checked($on) @disabled($def['locked'])>
                            @if($def['locked'] && $on)<input type="hidden" name="perm[{{ $role->value }}][{{ $key }}]" value="1">@endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(! config('plan.kebele_weekly_entry'))
        <p class="muted">ማስታወሻ፦ የቀበሌ ሳምንታዊ ግቤት ለጊዜው ጠፍቷል (<code>PLAN_KEBELE_WEEKLY_ENTRY=false</code>)፤ ተዛማጅ ፈቃዶች ሲነቃ ይሠራሉ።</p>
    @endif
    <div class="actions"><button class="btn" type="submit">አስቀምጥ</button></div>
</form>
@endsection
