<?php

use App\Domain\Party\Actions\GeneratePartyCode;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $candidates
 */
function codeGenerator(array $candidates): GeneratePartyCode
{
    return new class($candidates) extends GeneratePartyCode
    {
        public int $calls = 0;

        /**
         * @param  list<string>  $candidates
         */
        public function __construct(private array $candidates) {}

        protected function candidate(): string
        {
            $this->calls++;

            return array_shift($this->candidates) ?? 'AAAA';
        }
    };
}

it('generates four uppercase letters', function () {
    expect((new GeneratePartyCode)())->toMatch('/^[A-Z]{4}$/');
});

it('retries when a candidate already exists', function () {
    Party::factory()->create(['code' => 'TAKE']);
    $generator = codeGenerator(['TAKE', 'FREE']);

    expect($generator())->toBe('FREE')->and($generator->calls)->toBe(2);
});

it('gives up after ten collisions', function () {
    Party::factory()->create(['code' => 'AAAA']);
    $generator = codeGenerator([]);

    expect(fn () => $generator())->toThrow(RuntimeException::class)
        ->and($generator->calls)->toBe(10);
});
