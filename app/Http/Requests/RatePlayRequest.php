<?php

namespace App\Http\Requests;

use App\Domain\Queue\VoteDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatePlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', Rule::enum(VoteDirection::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Choose whether to like or dislike this play.',
            'value.string' => 'Choose whether to like or dislike this play.',
            'value.enum' => 'Choose whether to like or dislike this play.',
        ];
    }

    public function direction(): VoteDirection
    {
        return VoteDirection::from($this->string('value')->toString());
    }
}
