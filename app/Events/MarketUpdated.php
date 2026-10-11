<?php

namespace App\Events;

use App\MarketData\AccessGate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MarketUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $kind, public array $payload) {}

    public function broadcastOn(): array
    {
        if (! app(AccessGate::class)->workerAllowed()) {
            return [];
        }

return app(AccessGate::class)->publicAllowed() ? [new Channel('market.'.$this->kind)] : [new PrivateChannel('market.local.'.$this->kind)];
    }

    public function broadcastAs(): string
    {
        return 'market.updated';
    }
}
