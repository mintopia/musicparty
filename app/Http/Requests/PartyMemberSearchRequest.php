<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyMemberSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => 'sometimes|nullable|string|max:100',
            'role' => 'sometimes|nullable|string|exists:party_member_roles,code',
            'order' => 'sometimes|nullable|string|in:id,created_at,nickname,role',
            'order_direction' => 'sometimes|nullable|string|in:asc,desc',
            'per_page' => 'sometimes|nullable|int|between:1,100',
        ];
    }
}
