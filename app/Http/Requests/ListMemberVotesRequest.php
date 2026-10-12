<?php

namespace App\Http\Requests;

use App\Domain\Membership\Actions\ListMemberVoteHistory;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListMemberVotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $party = $this->route('party');
        $member = $this->route('member');

        if (! $party instanceof Party || ! $member instanceof PartyMember) {
            return false;
        }

        if ($this->user()?->can('listMembers', $party) !== true) {
            return false;
        }

        abort_unless($member->party_id === $party->id, 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ListMemberVoteHistory::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'per_page.integer' => 'The page size must be a number.',
            'per_page.min' => 'The page size must be at least 1.',
            'per_page.max' => 'The page size may not be more than '.ListMemberVoteHistory::MAX_PER_PAGE.'.',
            'page.integer' => 'The page must be a number.',
            'page.min' => 'The page must be at least 1.',
        ];
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->toString() : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
