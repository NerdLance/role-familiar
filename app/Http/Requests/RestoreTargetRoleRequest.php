<?php

namespace App\Http\Requests;

use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;

class RestoreTargetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole && $this->user()->can('restore', $targetRole);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
