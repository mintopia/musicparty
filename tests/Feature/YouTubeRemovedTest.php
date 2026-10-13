<?php

use App\Models\Party;

it('no longer serves any YouTube routes', function (string $method, string $path) {
    $party = Party::factory()->create();

    $this->call($method, "/parties/{$party->code}/{$path}")->assertNotFound();
})->with([
    'page' => ['GET', 'youtube'],
    'player' => ['GET', 'youtube/player'],
]);

it('rejects posting a YouTube video', function () {
    $party = Party::factory()->create();

    expect($this->post("/parties/{$party->code}/youtube")->status())->toBeIn([404, 405]);
});
