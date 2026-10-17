<?php

namespace App\View\Components;

use App\Models\HeatAlert;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class DangerBanner extends Component
{
    /**
     * @var Collection<int, HeatAlert>
     */
    public Collection $alerts;

    public function __construct()
    {
        $this->alerts = HeatAlert::query()->danger()->with('neighborhood')->latest('start_date')->get();
    }

    public function render(): View
    {
        return view('components.danger-banner');
    }
}
