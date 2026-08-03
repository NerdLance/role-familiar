<?php

namespace App\Http\Requests;

use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobListingTargetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TargetRole::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_listing' => ['required', 'string', 'min:100', 'max:50000'],
            'interview_at' => ['nullable', 'date'],
            'biggest_concern' => ['nullable', 'string', 'max:2000'],
            'company_name' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_name' => $this->trimmedOrNull('company_name'),
            'biggest_concern' => $this->trimmedOrNull('biggest_concern'),
        ]);
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
