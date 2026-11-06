<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('toll:sync')]
#[Description('Push unsynced booth data to the central server and pull master data')]
class SyncCommand extends Command
{
    public function handle(SyncService $sync): int
    {
        $result = $sync->run();

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info('Pushed: '.json_encode($result['pushed']));
        $this->info('Pulled: '.json_encode($result['pulled']));

        return self::SUCCESS;
    }
}
