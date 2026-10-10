<?php

use App\Models\Party;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->track = TrackRequest::factory()->for($this->party)->create();
});

it('limits joins to 20 a minute per user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    foreach (range(1, 20) as $attempt) {
        $this->postJson('/api/v1/parties/ABCD/join')->assertSuccessful();
    }
    $this->postJson('/api/v1/parties/ABCD/join')->assertStatus(429);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/parties/ABCD/join')->assertSuccessful();
});

it('limits web joins to 20 a minute per user', function () {
    $user = User::factory()->create();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($user)->post(route('parties.join'), ['code' => 'ABCD'])->assertRedirect();
    }
    $this->actingAs($user)->post(route('parties.join'), ['code' => 'ABCD'])->assertStatus(429);

    $this->actingAs(User::factory()->create())->post(route('parties.join'), ['code' => 'ABCD'])->assertRedirect();
});

it('limits requests to 10 a minute per user', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 10) as $attempt) {
        expect($this->postJson('/api/v1/parties/ABCD/requests', [])->status())->not->toBe(429);
    }
    $this->postJson('/api/v1/parties/ABCD/requests', [])->assertStatus(429);

    Sanctum::actingAs(User::factory()->create());
    expect($this->postJson('/api/v1/parties/ABCD/requests', [])->status())->not->toBe(429);
});

it('limits votes to 60 a minute per user', function () {
    $url = "/api/v1/parties/ABCD/requests/{$this->track->id}/vote";
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 60) as $attempt) {
        expect($this->putJson($url, ['value' => 'up'])->status())->not->toBe(429);
    }
    $this->putJson($url, ['value' => 'up'])->assertStatus(429);

    Sanctum::actingAs(User::factory()->create());
    expect($this->putJson($url, ['value' => 'up'])->status())->not->toBe(429);
});
