<?php

declare(strict_types=1);

/**
 * @return list<string>
 */
function reverbAllowedOrigins(?string $value): array
{
    if ($value === null) {
        unset($_ENV['REVERB_ALLOWED_ORIGINS'], $_SERVER['REVERB_ALLOWED_ORIGINS']);
    } else {
        $_ENV['REVERB_ALLOWED_ORIGINS'] = $_SERVER['REVERB_ALLOWED_ORIGINS'] = $value;
    }

    try {
        return (require base_path('config/reverb.php'))['apps']['apps'][0]['allowed_origins'];
    } finally {
        unset($_ENV['REVERB_ALLOWED_ORIGINS'], $_SERVER['REVERB_ALLOWED_ORIGINS']);
    }
}

it('allows any origin by default', function () {
    expect(reverbAllowedOrigins(null))->toBe(['*']);
});

it('reads a comma-separated origin list from REVERB_ALLOWED_ORIGINS', function () {
    expect(reverbAllowedOrigins('https://a.example, https://b.example,'))->toBe(['https://a.example', 'https://b.example']);
});
