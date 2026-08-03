<?php

namespace App\Http\Requests;

use App\Enums\FamiliarInteractionIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AskPrototypeInterviewGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::enum(FamiliarInteractionIntent::class)],
            'topic_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::prohibitedIf(fn (): bool => in_array($this->input('intent'), [
                    FamiliarInteractionIntent::InterviewPrep->value,
                    FamiliarInteractionIntent::CustomQuestion->value,
                ], true)),
            ],
            'question' => [
                Rule::requiredIf(fn (): bool => $this->input('intent') === FamiliarInteractionIntent::CustomQuestion->value),
                Rule::prohibitedIf(fn (): bool => $this->input('intent') !== FamiliarInteractionIntent::CustomQuestion->value),
                'string',
                'not_regex:/^\s*$/u',
                'max:1000',
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedFields = array_diff(array_keys($this->all()), [
                    'intent',
                    'topic_id',
                    'question',
                ]);

                if ($unexpectedFields !== []) {
                    $validator->errors()->add('request', 'The request contains unexpected fields.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'intent.required' => 'Choose a familiar interaction.',
            'question.required' => 'Please enter a question.',
            'question.not_regex' => 'Please enter a question.',
        ];
    }
}
