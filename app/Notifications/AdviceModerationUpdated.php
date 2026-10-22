<?php

namespace App\Notifications;

use App\Models\Advice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdviceModerationUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public Advice $advice,
        public string $status,
        public ?string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'advice_id' => $this->advice->getKey(),
            'title' => $this->advice->title,
            'status' => $this->status,
            'note' => $this->note,
            'url' => route('advice.submissions.index', ['advice' => $this->advice->getKey()]),
        ];
    }
}
