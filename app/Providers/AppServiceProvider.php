<?php

namespace App\Providers;

use App\Contracts\AiTextGenerator;
use App\Services\AnthropicTextGenerator;
use App\Services\GeminiTextGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            GeminiTextGenerator::class,
            fn () => new GeminiTextGenerator(config('services.gemini.key'), config('services.gemini.model'), config('services.gemini.fallback_models', []), config('services.gemini.embedding_model', 'gemini-embedding-001')),
        );

        $this->app->singleton(AiTextGenerator::class, fn () => match (config('services.ai.provider')) {
            'gemini' => new GeminiTextGenerator(config('services.gemini.key'), config('services.gemini.model'), config('services.gemini.fallback_models', []), config('services.gemini.embedding_model', 'gemini-embedding-001')),
            default => new AnthropicTextGenerator(config('services.anthropic.key'), config('services.anthropic.model')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
