<?php

namespace App\Console\Commands;

use App\Models\HeatAlert;
use Illuminate\Console\Command;

class NotifyPendingHeatAlerts extends Command
{
    protected $signature = 'alerts:notify';

    protected $description = 'Notify residents about active heat alerts that have not been announced yet';

    public function handle(): int
    {
        $alerts = HeatAlert::active()->whereNull('notified_at')->with('neighborhood')->get();

        $notified = $alerts->sum(fn (HeatAlert $alert) => $alert->notifyResidents());

        $this->info("{$alerts->count()} alert(s) processed, {$notified} resident(s) notified.");

        return self::SUCCESS;
    }
}
