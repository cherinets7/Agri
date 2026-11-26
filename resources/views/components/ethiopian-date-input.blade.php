@props([
    'name',
    'id' => null,
    'value' => '',
    'max' => null,
    'required' => false,
    'label' => null,
])
@php
    $inputId = $id ?: $name.'-'.uniqid();
    $etMonths = App\Support\EthiopianDate::MONTHS;
    $curG = $value ?: null;
    $etInit = null;
    if ($curG && preg_match('/^\d{4}-\d{2}-\d{2}$/', $curG)) {
        try {
            $c = Carbon\Carbon::createFromFormat('Y-m-d', $curG);
            $etInit = App\Support\EthiopianDate::fromGregorian((int) $c->format('Y'), (int) $c->format('n'), (int) $c->format('j'));
        } catch (Throwable) {
            $etInit = null;
        }
    }
    $nowEt = App\Support\EthiopianDate::fromGregorian((int) now()->format('Y'), (int) now()->format('n'), (int) now()->format('j'));
    $minY = min($nowEt[0] - 5, $etInit[0] ?? $nowEt[0]);
    $maxY = max($nowEt[0] + 2, $etInit[0] ?? $nowEt[0]);
@endphp
<div class="et-date-picker" data-et-date-picker>
    @if($label)
        <label for="{{ $inputId }}">{{ $label }}</label>
    @endif
    <input
        id="{{ $inputId }}"
        type="date"
        name="{{ $name }}"
        value="{{ $value }}"
        @if($max) max="{{ $max }}" @endif
        @required($required)
        data-et-greg
    >
    <div class="et-date-row">
        <span class="muted" style="font-size:.82rem">ኢት/ቀን፦</span>
        <select aria-label="የኢትዮጵያ ወር" data-et-month>
            @foreach($etMonths as $mNum => $mName)
                <option value="{{ $mNum }}" @selected(($etInit[1] ?? $nowEt[1]) === $mNum)>{{ $mName }}</option>
            @endforeach
        </select>
        <select aria-label="የኢትዮጵያ ቀን" data-et-day>
            @for($d = 1; $d <= 30; $d++)
                <option value="{{ $d }}" @selected(($etInit[2] ?? $nowEt[2]) === $d)>{{ $d }}</option>
            @endfor
        </select>
        <select aria-label="የኢትዮጵያ ዓመት" data-et-year>
            @for($y = $minY; $y <= $maxY; $y++)
                <option value="{{ $y }}" @selected(($etInit[0] ?? $nowEt[0]) === $y)>{{ $y }} ዓ/ም</option>
            @endfor
        </select>
    </div>
</div>
