<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Membership\PartyRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeMemberRoleRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::enum(PartyRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Choose a role.',
            'role.string' => 'Choose a role.',
            'role.enum' => 'Choose a valid role.',
        ];
    }

    public function role(): PartyRole
    {
        return PartyRole::from($this->string('role')->toString());
    }
}
