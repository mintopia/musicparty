<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Queue\Blocklist;
use App\Domain\Queue\BlocklistMatchType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBlocklistEntryRequest extends FormRequest
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
            'match_type' => ['required', 'string', Rule::enum(BlocklistMatchType::class)],
            'value' => ['required', 'string', 'max:500'],
            'is_regex' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_type.required' => 'Choose what the entry matches on.',
            'match_type.enum' => 'Choose a valid match type.',
            'value.required' => 'Enter a value to block.',
            'value.max' => 'The value may not be longer than 500 characters.',
            'is_regex.boolean' => 'The regular expression flag must be true or false.',
            'is_enabled.boolean' => 'The enabled flag must be true or false.',
            'notes.max' => 'Notes may not be longer than 1000 characters.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_regex')) {
                    return;
                }

                if (! $this->matchType()->supportsRegex()) {
                    $validator->errors()->add('is_regex', 'Only name matches can use a regular expression.');

                    return;
                }

                if (! Blocklist::isValidPattern($this->string('value')->toString())) {
                    $validator->errors()->add('value', 'That regular expression is not valid.');
                }
            },
        ];
    }

    public function matchType(): BlocklistMatchType
    {
        return BlocklistMatchType::from($this->string('match_type')->toString());
    }

    public function isRegex(): bool
    {
        return $this->boolean('is_regex');
    }

    public function isEnabled(): bool
    {
        return $this->boolean('is_enabled', true);
    }

    public function notes(): ?string
    {
        $notes = $this->input('notes');

        return is_string($notes) && trim($notes) !== '' ? $notes : null;
    }
}
