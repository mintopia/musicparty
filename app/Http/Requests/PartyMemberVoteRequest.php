<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyMemberVoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|nullable|string|max:100',
            'type' => 'sometimes|nullable|string|exists:upvote,downvote',
            'order' => 'sometimes|nullable|string|in:created_at',
            'order_direction' => 'sometimes|nullable|string|in:asc,desc',
            'per_page' => 'sometimes|nullable|int|between:1,100',
        ];
    }
}
