<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells every open dashboard in a workspace that its numbers are stale.
 *
 * The payload deliberately carries no figures: broadcasting them would leak
 * data to members whose permissions hide the widget that produced them. Clients
 * are told *that* something changed and re-fetch through the normal,
 * authorised path.
 */
class DashboardStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $widgets  Widget keys to refresh; empty means all.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly array $widgets = [],
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("company.{$this->companyId}")];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.stats.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->companyId,
            'widgets' => $this->widgets,
            'at' => now()->toIso8601String(),
        ];
    }
}
