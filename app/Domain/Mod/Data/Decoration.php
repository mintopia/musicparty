<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\DecorationAccent;
use App\Domain\Mod\DecorationIcon;
use App\Domain\Mod\DecorationVariant;

final readonly class Decoration
{
    public const int BADGE_MAX = 24;

    public const int LABEL_MAX = 60;

    public function __construct(
        public string $modId,
        public ?string $badge,
        public ?string $label,
        public ?DecorationIcon $icon,
        public DecorationAccent $accent = DecorationAccent::Accent,
        public DecorationVariant $variant = DecorationVariant::Soft,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function tryFromArray(string $modId, array $data): ?self
    {
        $badge = self::text($data, 'badge', self::BADGE_MAX);
        $label = self::text($data, 'label', self::LABEL_MAX);
        $icon = self::enum($data, 'icon', DecorationIcon::class, null);
        $accent = self::enum($data, 'accent', DecorationAccent::class, DecorationAccent::Accent);
        $variant = self::enum($data, 'variant', DecorationVariant::class, DecorationVariant::Soft);

        if ($badge === false || $label === false || $icon === false || $accent === false || $variant === false) {
            return null;
        }

        if ($modId === '' || ($badge === null && $label === null && $icon === null)) {
            return null;
        }

        assert($accent instanceof DecorationAccent && $variant instanceof DecorationVariant);

        return new self($modId, $badge, $label, $icon, $accent, $variant);
    }

    /**
     * @return array{mod_id: string, badge: string|null, label: string|null, icon: string|null, accent: string, variant: string}
     */
    public function toArray(): array
    {
        return [
            'mod_id' => $this->modId,
            'badge' => $this->badge,
            'label' => $this->label,
            'icon' => $this->icon?->value,
            'accent' => $this->accent->value,
            'variant' => $this->variant->value,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function text(array $data, string $key, int $max): string|false|null
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max || preg_match('/[<>\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $value) !== 0) {
            return false;
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string<T>  $enum
     * @param  T|null  $default
     * @return T|false|null
     */
    private static function enum(array $data, string $key, string $enum, ?\BackedEnum $default): \BackedEnum|false|null
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        $value = $data[$key];

        return is_string($value) || is_int($value) ? ($enum::tryFrom($value) ?? false) : false;
    }
}
