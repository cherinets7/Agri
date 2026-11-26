{{-- expects $period, optional $woredas, $woredaId, $statuses --}}
<form method="GET" class="card filters">
    <x-ethiopian-date-input name="from" id="from" label="ከ" :value="$period->from->toDateString()" />
    <x-ethiopian-date-input name="to" id="to" label="እስከ" :value="$period->to->toDateString()" />
    @if(isset($woredas) && $woredas->isNotEmpty())
        <div>
            <label for="woreda">ወረዳ</label>
            <select id="woreda" name="woreda">
                <option value="">ሁሉም ወረዳዎች</option>
                @foreach($woredas as $w)
                    <option value="{{ $w->id }}" @selected((int) request('woreda') === $w->id)>{{ $w->name_am }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @isset($statuses)
        <div>
            <label for="status">ሁኔታ</label>
            <select id="status" name="status">
                <option value="">ሁሉም</option>
                @foreach($statuses as $k => $label)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <div><button class="btn" type="submit">አጣራ</button></div>
    <div class="presets">
        @php $keep = request()->except(['from', 'to', 'range', 'page']); @endphp
        <a href="?{{ http_build_query($keep + ['range' => 'today']) }}">ዛሬ</a>
        <a href="?{{ http_build_query($keep + ['range' => 'week']) }}">ይህ ሳምንት</a>
        <a href="?{{ http_build_query($keep + ['range' => 'month']) }}">ይህ ወር</a>
        <a href="?{{ http_build_query($keep + ['range' => 'last30']) }}">ባለፉት 30 ቀን</a>
    </div>
</form>
