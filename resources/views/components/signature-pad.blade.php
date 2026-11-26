@props([
    'name' => 'signature_data',
    'label' => 'ዲጂታል ፊርማ (በጣት ወይም በመዳፊት ይፈርሙ — አማራጭ)',
    'existing' => null,
    'id' => null,
])
@php($uid = $id ?: 'sig-'.uniqid())
<div class="sig-pad-wrap" data-sig-wrap="{{ $uid }}">
    <label for="{{ $uid }}">{{ $label }}</label>
    @if($existing)
        <div class="muted" style="font-size:.85rem;margin-bottom:.35rem">
            ቀደም ሲል የተቀመጠ ፊርማ፦
            <img src="{{ $existing }}" alt="ፊርማ" style="max-height:54px;border:1px solid var(--line);border-radius:6px;background:#fff;vertical-align:middle">
        </div>
    @endif
    <canvas id="{{ $uid }}" class="sig-pad" width="460" height="120"></canvas>
    <input type="hidden" name="{{ $name }}" value="{{ old($name) }}" data-sig-input>
    <div class="actions" style="margin-top:.35rem">
        <button type="button" class="btn sm secondary" data-sig-clear>ፊርማ አጽዳ</button>
    </div>
</div>
