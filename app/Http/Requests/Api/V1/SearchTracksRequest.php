<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchTracksRequest extends FormRequest
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
            'q' => 'required|string|max:200',
            'page' => 'sometimes|integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Enter something to search for.',
            'q.string' => 'Enter something to search for.',
            'q.max' => 'The search is too long.',
            'page.integer' => 'The page must be a whole number.',
            'page.min' => 'The page must be at least 1.',
        ];
    }
}
