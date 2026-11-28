<?php

use Symfony\Component\Finder\Finder;

const PRODUCTION_ROOT = __DIR__.'/../..';
const PRODUCTION_BRANCH_PATTERN = '/isProduction\(|->environment\(|environment\(\[|APP_ENV/';

/** @return array<string, string> */
function productionBranchCoverage(): array
{
    return [
        'app/Providers/TrustedRootUrlServiceProvider.php' => 'tests/Production/ProductionProfileTest.php',
        'app/Providers/AppServiceProvider.php' => 'tests/Production/ProductionProfileTest.php',
        'app/Providers/RequiredSecretsServiceProvider.php' => 'tests/Production/ProductionProfileTest.php',
        'app/Services/Music/MusicProviderFactory.php' => 'tests/Production/ProductionProfileTest.php',
        'app/Console/Review/ReviewSeed.php' => 'tests/Production/ProductionProfileTest.php',
        'config/session.php' => 'tests/Production/ProductionProfileTest.php',
        'config/app.php' => 'tests/Production/ProductionProfileTest.php',
    ];
}

it('has a production-profile test for every file that branches on the environment', function () {
    $branching = collect(Finder::create()->files()->name('*.php')->in([PRODUCTION_ROOT.'/'.'app', PRODUCTION_ROOT.'/'.'config', PRODUCTION_ROOT.'/'.'bootstrap', PRODUCTION_ROOT.'/'.'routes']))
        ->filter(fn (SplFileInfo $file): bool => preg_match(PRODUCTION_BRANCH_PATTERN, (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => str_replace(PRODUCTION_ROOT.'/', '', $file->getPathname()))
        ->sort()
        ->values()
        ->all();

    $uncovered = array_values(array_diff($branching, array_keys(productionBranchCoverage())));

    expect($uncovered)->toBe([], 'Add a test under tests/Production and list the file in productionBranchCoverage()');
});

it('points every covered file at a production-profile test that exists and is registered', function (string $source, string $test) {
    expect(PRODUCTION_ROOT.'/'.$source)->toBeFile()
        ->and(PRODUCTION_ROOT.'/'.$test)->toBeFile()
        ->and(file_get_contents(PRODUCTION_ROOT.'/'.'phpunit.production.xml'))->toContain('tests/Production')
        ->and(file_get_contents(PRODUCTION_ROOT.'/'.$test))->toContain(pathinfo($source, PATHINFO_FILENAME));
})->with(fn (): array => collect(productionBranchCoverage())->map(fn (string $test, string $source): array => [$source, $test])->all());
