<?php

namespace App\Http\Requests;

use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmTargetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole && $this->user()->can('confirm', $targetRole);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var TargetRole $targetRole */
            $targetRole = $this->route('targetRole');

            if (blank($targetRole->title)) {
                $validator->errors()->add('title', 'A title is required before confirmation.');
            }

            if (! $targetRole->responsibilities()->where('content', '!=', '')->exists()) {
                $validator->errors()->add('responsibilities', 'Add at least one responsibility before confirmation.');
            }

            if (! $targetRole->competencies()->where('name', '!=', '')->exists()) {
                $validator->errors()->add('competencies', 'Add at least one competency before confirmation.');
            }
        }];
    }
}
