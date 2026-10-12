<?php

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

it('refuses to run when the default connection is not sqlite memory', function (array $config) {
    TestCase::assertSafeDatabase(new Repository($config));
})->with([
    'mariadb' => [['database' => ['default' => 'mariadb', 'connections' => ['mariadb' => ['driver' => 'mariadb', 'database' => 'musicparty']]]]],
    'mysql' => [['database' => ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'database' => 'musicparty']]]]],
    'sqlite file' => [['database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => '/tmp/real.sqlite']]]]],
    'undefined' => [['database' => ['default' => 'nope', 'connections' => []]]],
])->throws(RuntimeException::class, 'Refusing to run tests');

it('accepts sqlite memory as the default connection', function () {
    TestCase::assertSafeDatabase(config());

    expect(true)->toBeTrue();
});

it('cannot open any non-sqlite connection', function (string $name) {
    DB::connection($name);
})->with(['mysql', 'mariadb', 'pgsql', 'sqlsrv'])->throws(InvalidArgumentException::class);
