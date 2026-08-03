<?php

namespace App\Http\Requests;

use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTargetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole && $this->user()->can('delete', $targetRole);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted']];
    }
}
