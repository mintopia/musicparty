<?php

namespace Tests\Architecture;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tests\Architecture\PHPStan\CrossContextWriteRule;
use Tests\Architecture\Support\ArchitectureRules;
use Tests\Fixtures\Architecture\CrossContextForceFiller;
use Tests\Fixtures\Architecture\CrossContextQueryWriter;
use Tests\Fixtures\Architecture\CrossContextSaver;
use Tests\Fixtures\Architecture\SameContextWriter;

/**
 * @extends RuleTestCase<CrossContextWriteRule>
 */
class CrossContextWriteRuleTest extends RuleTestCase
{
    private mixed $errorHandler = null;

    private mixed $exceptionHandler = null;

    protected function setUp(): void
    {
        $this->errorHandler = $this->currentHandler('set_error_handler', 'restore_error_handler');
        $this->exceptionHandler = $this->currentHandler('set_exception_handler', 'restore_exception_handler');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while ($this->currentHandler('set_error_handler', 'restore_error_handler') !== $this->errorHandler) {
            restore_error_handler();
        }

        while ($this->currentHandler('set_exception_handler', 'restore_exception_handler') !== $this->exceptionHandler) {
            restore_exception_handler();
        }
    }

    private function currentHandler(callable $set, callable $restore): mixed
    {
        $handler = $set(null);
        $restore();

        return $handler;
    }

    protected function getRule(): Rule
    {
        $contexts = ArchitectureRules::contexts();
        array_push(
            $contexts['identity']['members'],
            CrossContextSaver::class,
            CrossContextForceFiller::class,
            CrossContextQueryWriter::class,
            SameContextWriter::class,
        );

        return new CrossContextWriteRule($contexts);
    }

    /**
     * @return array<int, string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [dirname(__DIR__, 2).'/vendor/larastan/larastan/extension.neon'];
    }

    public function test_direct_save_across_contexts_is_reported(): void
    {
        $this->analyse([dirname(__DIR__).'/Fixtures/Architecture/CrossContextSaver.php'], [
            ['Context identity must not write party model Party via save(); call an action in party instead.', 11],
        ]);
    }

    public function test_force_fill_then_save_across_contexts_is_reported(): void
    {
        $this->analyse([dirname(__DIR__).'/Fixtures/Architecture/CrossContextForceFiller.php'], [
            ['Context identity must not write party model Party via save(); call an action in party instead.', 11],
        ]);
    }

    public function test_query_builder_and_relation_writes_across_contexts_are_reported(): void
    {
        $this->analyse([dirname(__DIR__).'/Fixtures/Architecture/CrossContextQueryWriter.php'], [
            ['Context identity must not write party model Party via update(); call an action in party instead.', 12],
            ['Context identity must not write queue model RequestVote via delete(); call an action in queue instead.', 13],
        ]);
    }

    public function test_same_context_writes_are_allowed(): void
    {
        $this->analyse([dirname(__DIR__).'/Fixtures/Architecture/SameContextWriter.php'], []);
    }
}
