<?php

namespace App\Notifications;

use App\Models\HeatAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HeatAlertIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HeatAlert $heatAlert) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Heat alert ({$this->heatAlert->severity_level->label()}) - {$this->heatAlert->neighborhood->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->heatAlert->title)
            ->line('Severity: '.$this->heatAlert->severity_level->label());

        foreach ($this->heatAlert->severity_level->recommendations() as $recommendation) {
            $message->line('- '.$recommendation);
        }

        return $message->action('See current alerts', route('front.alerts.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'heat_alert_id' => $this->heatAlert->id,
            'title' => $this->heatAlert->title,
            'severity' => $this->heatAlert->severity_level->value,
            'neighborhood' => $this->heatAlert->neighborhood->name,
        ];
    }
}
