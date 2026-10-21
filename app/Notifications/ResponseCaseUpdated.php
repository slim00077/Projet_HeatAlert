<?php

namespace App\Notifications;

use App\Models\ResidentReport;
use App\Models\ResponseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * In-app notice to a resident that the response case handling their report has a published update.
 */
class ResponseCaseUpdated extends Notification
{
    use Queueable;

    public function __construct(public ResponseCase $responseCase, public ResidentReport $report, public string $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'response_case_id' => $this->responseCase->id,
            'resident_report_id' => $this->report->id,
            'report_title' => $this->report->title,
            'stage' => $this->responseCase->stage->value,
            'stage_label' => $this->responseCase->stage->label(),
            'message' => Str::limit($this->message, 240),
            'url' => route('resident-reports.show', $this->report),
        ];
    }
}
