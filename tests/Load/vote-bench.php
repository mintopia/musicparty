<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Exceptions\VoteRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use App\Models\Play;
use App\Models\TrackRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Queue::fake();

if (getenv('WITH_STATS') !== '1') {
    Event::forget(VoteCast::class);
}

$voters = (int) (getenv('VOTERS') ?: 300);
$rounds = (int) (getenv('ROUNDS') ?: 5);
$requestCount = (int) (getenv('REQUESTS') ?: 60);
$cap = (int) (getenv('CAP') ?: 0);

DB::connection()->getSchemaBuilder()->disableForeignKeyConstraints();
foreach (['ratings', 'request_votes', 'plays', 'track_requests', 'party_members', 'parties', 'users'] as $table) {
    DB::table($table)->delete();
}
DB::connection()->getSchemaBuilder()->enableForeignKeyConstraints();

$party = Party::factory()->live()->create([
    'code' => 'BENCH',
    'downvotes' => true,
    'downvotes_per_hour' => $cap > 0 ? $cap : null,
]);
$members = collect(range(1, $voters))->map(fn () => PartyMember::factory()->for($party)->for(User::factory()->create())->create());
$requests = TrackRequest::factory()->count($requestCount)->for($party)->create(['status' => RequestStatus::Queued]);
$playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing]);
$play = Play::factory()->for($party)->create(['track_request_id' => $playing->id]);

$requestIds = $requests->pluck('id')->all();
$memberIds = $members->pluck('id')->all();
$dir = sys_get_temp_dir().'/vote-bench-'.getmypid();
mkdir($dir);
$startAt = microtime(true) + 3 + $voters * 0.01;
$pids = [];

foreach ($memberIds as $index => $memberId) {
    $pid = pcntl_fork();

    if ($pid === 0) {
        DB::purge();
        $member = PartyMember::query()->findOrFail($memberId);
        $party = Party::query()->findOrFail($member->party_id);
        $samples = ['vote' => [], 'rate' => []];
        $refused = 0;
        $errors = 0;
        mt_srand($index);

        while (microtime(true) < $startAt) {
            usleep(1000);
        }

        for ($round = 0; $round < $rounds; $round++) {
            $requestId = $requestIds[mt_rand(0, count($requestIds) - 1)];
            $direction = mt_rand(0, 1) === 0 ? VoteDirection::Up : VoteDirection::Down;
            $t = microtime(true);

            try {
                (new VoteOnRequest)($party, $member, TrackRequest::query()->findOrFail($requestId), $direction);
            } catch (VoteRefusedException|RequestRefusedException) {
                $refused++;
            } catch (Throwable) {
                $errors++;
            }

            $samples['vote'][] = (microtime(true) - $t) * 1000;

            $t = microtime(true);
            try {
                (new RatePlay)($member, $play, mt_rand(0, 1) === 0 ? VoteDirection::Up : VoteDirection::Down);
            } catch (Throwable) {
                $errors++;
            }

            $samples['rate'][] = (microtime(true) - $t) * 1000;
        }

        file_put_contents("{$dir}/{$index}.json", json_encode($samples + ['refused' => $refused, 'errors' => $errors]));
        posix_kill(getmypid(), SIGKILL);
    }

    $pids[] = $pid;
}

foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

$all = ['vote' => [], 'rate' => []];
$refused = 0;
$errors = 0;
foreach (glob("{$dir}/*.json") as $file) {
    $data = json_decode(file_get_contents($file), true);
    $all['vote'] = array_merge($all['vote'], $data['vote']);
    $all['rate'] = array_merge($all['rate'], $data['rate']);
    $refused += $data['refused'];
    $errors += $data['errors'];
    unlink($file);
}
rmdir($dir);

$percentile = function (array $values, float $p): float {
    sort($values);

    return $values[(int) min(count($values) - 1, floor($p * count($values)))];
};

$report = ['voters' => $voters, 'rounds' => $rounds, 'refused' => $refused, 'errors' => $errors];
foreach ($all as $name => $values) {
    $report[$name] = [
        'n' => count($values),
        'p50_ms' => round($percentile($values, 0.50), 1),
        'p95_ms' => round($percentile($values, 0.95), 1),
        'p99_ms' => round($percentile($values, 0.99), 1),
        'max_ms' => round(max($values), 1),
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
