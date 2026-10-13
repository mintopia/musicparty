<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $readonly
 * @property bool $active
 * @property bool $dark_mode
 * @property array<string, string>|null $tokens
 * @property string|null $css
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereReadonly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Theme extends Model
{
    /** @use HasFactory<Factory<Theme>> */
    use HasFactory;

    public const COLOUR_PATTERN = '/^#(?:(?:[0-9a-f]{3}){1,2}|(?:[0-9a-f]{4}){1,2})$/i';

    /** Editable colour tokens, mapped to their `--mp-*` CSS variables; the first two are required. */
    public const COLOUR_TOKENS = [
        'primary' => 'Primary',
        'nav-background' => 'Navigation Background',
        'surface' => 'Surface',
        'text' => 'Text',
        'muted' => 'Muted Text',
        'border' => 'Border',
        'success' => 'Success',
        'danger' => 'Danger',
        'warning' => 'Warning',
    ];

    protected $casts = [
        'readonly' => 'boolean',
        'active' => 'boolean',
        'dark_mode' => 'boolean',
        'tokens' => 'array',
    ];

    /**
     * Token values that are known tokens with valid colours; anything else is dropped.
     *
     * @return array<string, string>
     */
    public function validTokens(): array
    {
        $valid = [];
        foreach (self::COLOUR_TOKENS as $token => $label) {
            $value = $this->tokens[$token] ?? null;
            if (is_string($value) && preg_match(self::COLOUR_PATTERN, $value)) {
                $valid[$token] = $value;
            }
        }

        return $valid;
    }

    public function rgb(string $token): string
    {
        $colour = $this->tokens[$token] ?? '';
        if (! preg_match(self::COLOUR_PATTERN, $colour)) {
            return '0, 0, 0';
        }

        $hex = str_replace('#', '', $colour);
        if (strlen($hex) <= 4) {
            $hex = implode('', array_map(fn (string $c): string => $c.$c, str_split($hex)));
        }

        return implode(', ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }

    public function version(): string
    {
        return substr(md5(serialize([$this->validTokens(), $this->css, $this->dark_mode])), 0, 12);
    }

    public function stylesheetUrl(): string
    {
        return route('theme.css', ['code' => $this->code, 'v' => $this->version()]);
    }

    public function renderCss(): string
    {
        $css = ":root {\n";
        foreach ($this->validTokens() as $token => $value) {
            $css .= "    --mp-{$token}: {$value};\n";
        }
        if (isset($this->validTokens()['primary'])) {
            $css .= "    --mp-primary-rgb: {$this->rgb('primary')};\n";
            $css .= "    --tblr-link-color: var(--mp-primary);\n    --tblr-link-color-rgb: var(--mp-primary-rgb);\n";
            $css .= "    --tblr-link-hover-color: var(--mp-primary);\n    --tblr-link-hover-color-rgb: var(--mp-primary-rgb);\n";
        }
        $css .= "}\n\n";
        $css .= ".breadcrumb a {\n    --tblr-breadcrumb-link-color: var(--mp-primary);\n}\n\n";
        $css .= ".btn-link, .btn-link:hover {\n    color: var(--mp-primary);\n}\n\n";
        $css .= ".border-primary {\n    border-color: var(--mp-primary) !important;\n}\n";
        if (trim((string) $this->css) !== '') {
            $css .= "\n/* Custom stylesheet */\n{$this->css}\n";
        }

        return $css;
    }
}
