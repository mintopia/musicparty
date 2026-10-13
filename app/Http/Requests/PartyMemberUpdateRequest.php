<?php

namespace App\Http\Requests;

use App\Http\Concerns\ResolvesAuthenticatedUser;
use App\Models\Party;
use App\Models\PartyMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PartyMemberUpdateRequest extends FormRequest
{
    use ResolvesAuthenticatedUser;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Party $party */
        $party = $this->route('party');
        /** @var PartyMember $member */
        $member = $this->route('user');
        $user = $this->resolveAuthenticatedUser($this);

        $touchesOwnerRole = $this->input('role') === 'owner' || $member->role->code === 'owner';
        if (! $touchesOwnerRole) {
            return true;
        }

        return $user->id === $party->user_id || $user->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => 'required|string|exists:party_member_roles,code',
        ];
    }
}
