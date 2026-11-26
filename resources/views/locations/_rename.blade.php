<details style="display:inline-block;margin-left:.4rem">
    <summary style="display:inline;cursor:pointer;font-size:.85rem">ስም ቀይር</summary>
    <form method="POST" action="{{ route('locations.update', $location) }}" style="margin:.4rem 0">
        @csrf @method('PUT')
        <label for="rn-am-{{ $location->id }}">ስም (አማርኛ)</label>
        <input id="rn-am-{{ $location->id }}" type="text" name="name_am" value="{{ $location->name_am }}" required>
        <label for="rn-en-{{ $location->id }}">ስም (እንግሊዝኛ)</label>
        <input id="rn-en-{{ $location->id }}" type="text" name="name_en" value="{{ $location->name_en }}">
        <div class="actions"><button class="btn secondary" type="submit">አስቀምጥ / አረጋግጥ</button></div>
    </form>
</details>
