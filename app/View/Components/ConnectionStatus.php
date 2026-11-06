<?php

namespace App\View\Components;

use App\Enums\AppMode;
use App\Services\Sync\SyncService;
use App\Support\Toll;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

/**
 * Top-bar pill: central server, stand-alone booth, or booth sync health.
 */
class ConnectionStatus extends Component
{
    public string $state;

    public string $label;

    public ?string $title;

    public function __construct(SyncService $sync)
    {
        [$this->state, $this->label, $this->title] = $this->describe($sync);
    }

    /** @return array{0: string, 1: string, 2: ?string} */
    private function describe(SyncService $sync): array
    {
        if (AppMode::isCentral()) {
            return ['ok', __('Central server'), null];
        }

        if (! Toll::syncEnabled()) {
            return ['warn', __('Stand-alone booth'), __('Central sync is not configured.')];
        }

        $status = $sync->status();
        $pending = Cache::remember('toll.sync.pending', 30, fn (): int => $sync->pendingCount());
        $lastSync = $status['at'] ? CarbonImmutable::parse($status['at']) : null;
        $stale = ! $lastSync || $lastSync->lt(now()->subMinutes(5));

        $label = $lastSync
            ? __('Synced :time', ['time' => $lastSync->diffForHumans()])
            : __('Never synced');

        return [
            $stale ? 'off' : ($pending > 0 ? 'warn' : 'ok'),
            $pending > 0 ? $label.' · '.__(':count pending', ['count' => $pending]) : $label,
            $status['error'],
        ];
    }

    public function render(): View
    {
        return view('components.connection-status');
    }
}
