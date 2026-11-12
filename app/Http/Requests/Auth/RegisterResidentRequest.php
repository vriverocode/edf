<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'unidad' => trim((string) $this->input('unidad')),
            'codigo_invitacion' => Str::upper(trim((string) $this->input('codigo_invitacion'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'unidad' => ['required', 'string', 'max:255'],
            'codigo_invitacion' => ['required', 'string', 'max:12'],
        ];
    }
}
