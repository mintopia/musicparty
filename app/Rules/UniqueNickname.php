<?php

namespace App\Rules;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueNickname implements ValidationRule
{
    public const MESSAGE = 'That nickname is already taken.';

    public function __construct(private readonly ?int $ignoreUserId = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = User::query()
            ->where('nickname_key', User::nicknameKey($value))
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($taken) {
            $fail(self::MESSAGE);
        }
    }
}
