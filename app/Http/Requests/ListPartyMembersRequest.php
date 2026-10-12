<?php

namespace App\Http\Requests;

use App\Domain\Membership\Actions\ListPartyMembers;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartyMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $party = $this->route('party');

        return $party instanceof Party && $this->user()?->can('listMembers', $party) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::enum(PartyRole::class)],
            'sort' => ['nullable', 'string', Rule::in(ListPartyMembers::SORTABLE)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ListPartyMembers::MAX_PER_PAGE],
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
            'role.enum' => 'Choose host, moderator, vip or guest.',
            'sort.in' => 'Sort by nickname, role or joined.',
            'direction.in' => 'The sort direction must be asc or desc.',
            'per_page.integer' => 'The page size must be a number.',
            'per_page.min' => 'The page size must be at least 1.',
            'per_page.max' => 'The page size may not be more than '.ListPartyMembers::MAX_PER_PAGE.'.',
            'page.integer' => 'The page must be a number.',
            'page.min' => 'The page must be at least 1.',
        ];
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->toString() : null;
    }

    public function role(): ?PartyRole
    {
        return $this->filled('role') ? PartyRole::from($this->string('role')->toString()) : null;
    }

    public function sort(): string
    {
        return $this->string('sort', 'role')->toString();
    }

    public function direction(): string
    {
        return $this->string('direction', 'asc')->toString();
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
