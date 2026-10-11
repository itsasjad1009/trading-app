<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('account.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'account.updated';
    }

    public function broadcastWith(): array
    {
        return ['refresh' => true];
    }
}
