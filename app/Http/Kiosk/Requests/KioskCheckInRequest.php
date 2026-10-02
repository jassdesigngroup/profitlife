<?php

namespace App\Http\Kiosk\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KioskCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware del kiosco ya validó el dispositivo.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['qr', 'member_number', 'phone'])],
            'value' => ['required', 'string', 'max:200'],
            'pin' => ['required_if:method,phone', 'nullable', 'digits:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['method' => 'método', 'value' => 'dato', 'pin' => 'PIN'];
    }
}
