<?php

use App\Services\OpenApi\RuleSchemaMapper;

it('maps common validation rules to schemas', function () {
    $result = (new RuleSchemaMapper)->map([
        'name' => 'required|string|min:2|max:10',
        'count' => ['required', 'integer', 'min:1', 'max:5'],
        'flag' => 'boolean',
        'mode' => 'nullable|string|in:a,b',
        'id' => 'required|uuid',
        'email' => 'required|email|nullable',
        'tags' => 'array|max:3',
        'nested.*' => 'string',
    ]);

    expect($result['required'])->toBe(['count', 'id', 'name'])
        ->and($result['properties']['name'])->toBe(['type' => 'string', 'minLength' => 2, 'maxLength' => 10])
        ->and($result['properties']['count'])->toBe(['type' => 'integer', 'minimum' => 1, 'maximum' => 5])
        ->and($result['properties']['flag'])->toBe(['type' => 'boolean'])
        ->and($result['properties']['mode'])->toEqual(['type' => 'string', 'enum' => ['a', 'b'], 'nullable' => true])
        ->and($result['properties']['id'])->toBe(['type' => 'string', 'format' => 'uuid'])
        ->and($result['properties']['email'])->toBe(['type' => 'string', 'format' => 'email', 'nullable' => true])
        ->and($result['properties']['tags']['maxItems'])->toBe(3)
        ->and($result['properties'])->not->toHaveKey('nested.*');
});
