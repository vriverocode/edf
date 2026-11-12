<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
