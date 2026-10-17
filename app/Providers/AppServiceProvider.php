<?php

namespace App\Providers;

use App\Contracts\AiTextGenerator;
use App\Services\AnthropicTextGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiTextGenerator::class, fn () => new AnthropicTextGenerator(
            config('services.anthropic.key'),
            config('services.anthropic.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
