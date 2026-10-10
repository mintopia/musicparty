<?php

it('runs on in-memory SQLite', function (): void {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');

    $this->assertInMemorySqlite();
});

it('refuses to run on another connection and names it', function (string $connection): void {
    config(['database.default' => $connection]);

    $this->assertInMemorySqlite();
})->with(['mysql', 'mariadb'])->throws(RuntimeException::class, 'default connection is [');

it('refuses a file-backed SQLite database', function (): void {
    config(['database.connections.sqlite.database' => '/tmp/musicparty.sqlite']);

    $this->assertInMemorySqlite();
})->throws(RuntimeException::class, '[sqlite]');
