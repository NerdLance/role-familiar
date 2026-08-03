<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskPrototypeInterviewGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a question.',
            'message.not_regex' => 'Please enter a question.',
        ];
    }
}
