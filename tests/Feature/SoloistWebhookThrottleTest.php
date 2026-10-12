<?php

use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

function makeParty(string $code): Party
{
    $party = new Party;
    $party->code = $code;
    $party->name = $code;
    $party->user_id = User::factory()->create()->getKey();
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

beforeEach(function () {
    Cache::flush();
    config(['musicparty.webhook_burst' => 3, 'musicparty.webhook_leak_per_second' => 1]);
    Queue::fake();
});

it('accepts a burst, rejects the overflow, and recovers after leaking', function () {
    $party = makeParty('ABCD');
    $url = route('webhooks.parties.soloist', $party);

    foreach (range(1, 3) as $_) {
        $this->postJson($url)->assertNoContent();
    }
    Queue::assertPushed(PartyUpdate::class, 3);

    $this->postJson($url)->assertStatus(429);
    Queue::assertPushed(PartyUpdate::class, 3);

    $this->travel(2)->seconds();
    $this->postJson($url)->assertNoContent();
    Queue::assertPushed(PartyUpdate::class, 4);
});

it('does not share buckets between parties', function () {
    $a = makeParty('AAAA');
    $b = makeParty('BBBB');

    foreach (range(1, 3) as $_) {
        $this->postJson(route('webhooks.parties.soloist', $a))->assertNoContent();
    }
    $this->postJson(route('webhooks.parties.soloist', $a))->assertStatus(429);
    $this->postJson(route('webhooks.parties.soloist', $b))->assertNoContent();
});
