<?php

namespace App\Http\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    /**
     * La autorización la da la firma del enlace (middleware `signed`) y el
     * hash del email, comprobados en el controlador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'contraseña'];
    }
}
