<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ReviewCompleted extends Notification
{
    public function __construct(public string $kind, public string $decision, public int $requestId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'status' => $this->decision, 'request_id' => $this->requestId];
    }
}
