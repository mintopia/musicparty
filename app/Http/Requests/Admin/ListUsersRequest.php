<?php

namespace App\Http\Requests\Admin;

use App\Domain\Admin\Actions\ListUsers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'id' => ['nullable', 'integer', 'min:1'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'order' => ['nullable', 'string', Rule::in(ListUsers::SORTABLE)],
            'order_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => 'The search must be text.',
            'search.max' => 'The search may not be longer than 100 characters.',
            'id.integer' => 'The ID must be a number.',
            'id.min' => 'The ID must be at least 1.',
            'nickname.string' => 'The nickname must be text.',
            'nickname.max' => 'The nickname may not be longer than 100 characters.',
            'order.in' => 'Sort by id, nickname, last_login or created_at.',
            'order_direction.in' => 'The sort direction must be asc or desc.',
        ];
    }
}
