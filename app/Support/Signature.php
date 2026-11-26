<?php

namespace App\Support;

use Illuminate\Http\Request;

class Signature
{
    /** @return array<int, string> */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:500000', 'starts_with:data:image/png;base64,'];
    }

    public static function fromRequest(Request $request): ?string
    {
        $data = $request->validate([
            'signature_data' => self::rules(),
        ])['signature_data'] ?? null;

        return $data ? trim($data) : null;
    }
}
