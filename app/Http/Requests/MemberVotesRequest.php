<?php

namespace App\Http\Requests;

use App\Domain\Party\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MemberVotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $party = $this->route('party');

        return $party instanceof Party && $this->user()?->can('viewMembers', $party) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
