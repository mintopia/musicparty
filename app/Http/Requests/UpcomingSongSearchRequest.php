<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpcomingSongSearchRequest extends FormRequest
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
            'name' => 'string|sometimes|nullable|max:100',
            'artist' => 'string|sometimes|nullable|max:100',
            'album' => 'string|sometimes|nullable|max:100',
            'status' => 'string|sometimes|nullable|in:queued,spotify',
            'order' => 'string|sometimes|nullable|in:name,votes,created_at,queued_at',
            'order_direction' => 'string|sometimes|nullable|in:asc,desc',
            'per_page' => 'integer|sometimes|nullable|between:0,100',
        ];
    }
}
