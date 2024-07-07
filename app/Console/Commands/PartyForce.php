<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyForce extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:force {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Check any non-polling forced playback parties and start them if possible";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = now()->subMinutes(15);
        $query = Party::whereActive(true)->wherePoll(false)->whereForce(true)->whereNotNull('device_id')->where('last_updated_at', '<', $cutoff);
        $code = $this->argument('code');
        if ($code !== null) {
            $party = $query->whereCode($code)->first();
            if (!$party) {
                $this->error("Unable to find suitable party with code {$code}");
                return self::FAILURE;
            }
            return $this->processParty($party);
        }

        $query->chunk(50, function($chunk) {
            foreach ($chunk as $party) {
                $this->processParty($party);
            }
        });

        return self::SUCCESS;
    }

    protected function processParty(Party $party): int
    {
        $this->output->writeln("{$party}: Checking to see if we can play this party");
        $devices = $party->user->getDevices();
        foreach ($devices as $device) {
            if ($device->id == $party->device_id) {
                $this->output->writeln("Device is online, starting party");
                $party->updateState();
            }
        }
        $this->output->writeln("{$party}: Done");
        return self::SUCCESS;
    }
}
