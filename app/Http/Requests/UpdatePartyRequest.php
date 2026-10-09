<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:64',
            'downvotes' => 'sometimes|boolean',
            'downvotes_per_hour' => 'sometimes|nullable|integer|min:0|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'The party name must be at least :min characters.',
            'name.max' => 'The party name may not be longer than :max characters.',
            'downvotes.boolean' => 'Downvotes must be either on or off.',
            'downvotes_per_hour.integer' => 'The downvote limit must be a whole number.',
            'downvotes_per_hour.min' => 'The downvote limit cannot be negative.',
            'downvotes_per_hour.max' => 'The downvote limit may not be more than :max per hour.',
        ];
    }
}
