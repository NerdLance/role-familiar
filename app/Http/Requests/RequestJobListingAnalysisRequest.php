<?php

namespace App\Http\Requests;

use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;

class RequestJobListingAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole
            && $this->user()?->can('analyze', $targetRole) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'job_listing' => ['required', 'string', 'min:100', 'max:50000'],
        ];
    }
}
