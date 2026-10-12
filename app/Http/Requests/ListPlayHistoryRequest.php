<?php

namespace App\Http\Requests;

use App\Domain\Queue\Actions\ListPlayHistory;
use App\Domain\Queue\PlayHistorySort;
use App\Domain\Queue\PlayHistoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlayHistoryRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'artist' => ['nullable', 'string', 'max:100'],
            'album' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::enum(PlayHistoryType::class)],
            'sort' => ['nullable', 'string', Rule::enum(PlayHistorySort::class)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in(ListPlayHistory::PER_PAGE_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.enum' => 'Choose all, played or unsent.',
            'sort.enum' => 'Sort by name, artist, requested, played or score.',
            'direction.in' => 'Choose ascending or descending.',
            'per_page.in' => 'Choose 10, 20, 50 or 100 per page.',
            'name.max' => 'Search terms may be at most 100 characters.',
            'artist.max' => 'Search terms may be at most 100 characters.',
            'album.max' => 'Search terms may be at most 100 characters.',
        ];
    }

    /**
     * @return array{name: string, artist: string, album: string, type: string, sort: string, direction: string, per_page: int}
     */
    public function filters(): array
    {
        return [
            'name' => trim($this->string('name')->toString()),
            'artist' => trim($this->string('artist')->toString()),
            'album' => trim($this->string('album')->toString()),
            'type' => $this->string('type')->toString() ?: PlayHistoryType::All->value,
            'sort' => $this->string('sort')->toString() ?: PlayHistorySort::Played->value,
            'direction' => $this->string('direction')->toString() ?: 'asc',
            'per_page' => $this->integer('per_page') ?: ListPlayHistory::PER_PAGE,
        ];
    }
}
