<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;

class ImportResidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ];
    }
}
