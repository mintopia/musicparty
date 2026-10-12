<?php

use App\Enums\PartyModerationType;
use App\Http\Requests\PartyModerationRequest;
use App\Models\PartyModeration;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

function rule(string $value, bool $regex): PartyModeration
{
    $moderation = new PartyModeration;
    $moderation->type = PartyModerationType::mtName;
    $moderation->value = $value;
    $moderation->regex = $regex;

    return $moderation;
}

function matches(PartyModeration $moderation, string $name): bool
{
    return $moderation->match((object) ['id' => 'x', 'name' => $name]);
}

it('treats wildcard rules literally except for *', function (string $rule, string $name, bool $expected) {
    expect(matches(rule($rule, false), $name))->toBe($expected);
})->with([
    'dot is literal' => ['a.b*', 'a.bcd', true],
    'dot does not match any char' => ['a.b*', 'axbcd', false],
    'star matches anything' => ['*love*', 'My Love Song', true],
    'star matches empty' => ['abc*', 'abc', true],
    'case insensitive' => ['HELLO', 'hello', true],
    'anchored' => ['abc', 'xabcx', false],
    'brackets literal' => ['[a]', 'a', false],
    'brackets literal match' => ['[a]', '[a]', true],
    'parens literal' => ['(x)*', '(x)yz', true],
    'slash literal' => ['a/b', 'a/b', true],
    'plus literal' => ['a+', 'aaa', false],
    'plain mismatch' => ['abc', 'abd', false],
]);

it('keeps legitimate regex rules matching', function (string $rule, string $name, bool $expected) {
    expect(matches(rule($rule, true), $name))->toBe($expected);
})->with([
    'simple' => ['/^love/i', 'Love Story', true],
    'alternation' => ['/(foo|bar)/', 'a bar b', true],
    'no match' => ['/^love/i', 'Story of Love', false],
]);

it('treats an invalid or hostile regex as no match', function () {
    Log::spy();
    expect(matches(rule('/(unclosed', true), 'abc'))->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('returns no match quickly for catastrophic backtracking and logs a warning', function () {
    Log::spy();
    $start = microtime(true);
    $result = matches(rule('/^(a+)+$/', true), str_repeat('a', 5000).'!');
    expect($result)->toBeFalse()
        ->and(microtime(true) - $start)->toBeLessThan(2.0);
    Log::shouldHaveReceived('warning')->once();
});

it('restores pcre ini settings after matching', function () {
    $limit = ini_get('pcre.backtrack_limit');
    $jit = ini_get('pcre.jit');
    matches(rule('/a/', true), 'a');
    expect(ini_get('pcre.backtrack_limit'))->toBe($limit)
        ->and(ini_get('pcre.jit'))->toBe($jit);
});

function validateRequest(array $data): void
{
    PartyModerationRequest::create('/', 'POST', $data)
        ->setContainer(app())
        ->setRedirector(app('redirect'))
        ->validateResolved();
}

it('rejects invalid and over-long regex rules on save', function (string $value) {
    validateRequest(['type' => 'mtName', 'regex' => true, 'value' => $value]);
})->with([
    'invalid' => ['/(unclosed'],
    'too long' => ['/'.str_repeat('a', 300).'/'],
])->throws(ValidationException::class);

it('accepts valid regex and any wildcard value on save', function (array $data) {
    validateRequest($data + ['type' => 'mtName']);
    expect(true)->toBeTrue();
})->with([
    'valid regex' => [['regex' => true, 'value' => '/^love/i']],
    'wildcard that is not a regex' => [['regex' => false, 'value' => '/(unclosed']],
]);
