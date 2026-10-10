<?php

use App\Domain\Mod\Data\Decoration;
use App\Domain\Mod\DecorationAccent;
use App\Domain\Mod\DecorationIcon;
use App\Domain\Mod\DecorationVariant;

it('serialises a valid decoration with defaults', function () {
    $decoration = Decoration::tryFromArray('hype', ['badge' => ' HOT ', 'icon' => 'flame']);

    expect($decoration->toArray())->toBe([
        'mod_id' => 'hype',
        'badge' => 'HOT',
        'label' => null,
        'icon' => 'flame',
        'accent' => 'accent',
        'variant' => 'soft',
    ]);
});

it('accepts every allow-listed value', function () {
    foreach (DecorationAccent::cases() as $accent) {
        expect(Decoration::tryFromArray('m', ['label' => 'x', 'accent' => $accent->value]))->not->toBeNull();
    }

    foreach (DecorationVariant::cases() as $variant) {
        expect(Decoration::tryFromArray('m', ['label' => 'x', 'variant' => $variant->value]))->not->toBeNull();
    }

    foreach (DecorationIcon::cases() as $icon) {
        expect(Decoration::tryFromArray('m', ['icon' => $icon->value]))->not->toBeNull();
    }
});

it('discards invalid decorations', function (array $data) {
    expect(Decoration::tryFromArray('m', $data))->toBeNull();
})->with([
    'empty' => [[]],
    'blank texts only' => [['badge' => '  ', 'label' => '']],
    'html badge' => [['badge' => '<b>x</b>']],
    'script label' => [['label' => '<script>alert(1)</script>']],
    'angle bracket' => [['label' => 'a > b']],
    'control char' => [['label' => "bad\x00name"]],
    'newline' => [['label' => "two\nlines"]],
    'invalid utf8' => [['label' => "\xC3\x28"]],
    'badge too long' => [['badge' => str_repeat('a', 25)]],
    'label too long' => [['label' => str_repeat('a', 61)]],
    'non string badge' => [['badge' => ['x']]],
    'unknown accent' => [['badge' => 'x', 'accent' => 'neon']],
    'css accent' => [['badge' => 'x', 'accent' => 'red; background:url(x)']],
    'unknown variant' => [['badge' => 'x', 'variant' => 'glitter']],
    'unknown icon' => [['badge' => 'x', 'icon' => 'skull']],
    'non string icon' => [['badge' => 'x', 'icon' => ['star']]],
]);

it('accepts texts at the length limits', function () {
    expect(Decoration::tryFromArray('m', ['badge' => str_repeat('a', 24), 'label' => str_repeat('é', 60)]))->not->toBeNull();
});

it('rejects an empty mod id', function () {
    expect(Decoration::tryFromArray('', ['badge' => 'x']))->toBeNull();
});
