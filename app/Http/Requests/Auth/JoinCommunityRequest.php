<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo_invitacion' => Str::upper(trim((string) $this->input('codigo_invitacion'))),
            'registration_token' => trim((string) $this->input('registration_token')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registration_token' => ['required', 'string', 'max:128'],
            'codigo_invitacion' => ['required', 'string', 'max:12'],
        ];
    }
}
