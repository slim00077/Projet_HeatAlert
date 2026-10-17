<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use App\Enums\Severity;
use App\Models\Neighborhood;
use Illuminate\Support\Facades\Cache;

/**
 * AI features of the heat module: zone safety briefs, a Q&A assistant and alert drafting.
 * Every feature falls back to rule-based text when the AI is not configured or fails,
 * and says so through the returned `source`.
 */
class HeatAdvisorService
{
    private const SYSTEM = 'You are the HeatAlert safety advisor for a city heat-wave warning platform. '
        .'Give calm, practical, accurate advice for residents. Use plain language, no markdown headings, '
        .'never invent data that is not in the context, and tell people to call emergency services for medical emergencies.';

    public function __construct(private readonly AiTextGenerator $ai) {}

    /**
     * @return array{text: string, source: 'ai'|'rules'}
     */
    public function briefFor(Neighborhood $neighborhood): array
    {
        $neighborhood->loadMissing('activeAlerts');
        $context = $this->context($neighborhood);

        $key = 'heat-brief:'.$neighborhood->id.':'.md5($context);

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $text = $this->ai->generate(
            self::SYSTEM,
            'Write a safety brief of at most 120 words for the residents of this zone: the current situation, who is most at risk, '
            ."then exactly three concrete actions for today.\n\nContext:\n{$context}",
            700,
        );

        if ($text === null) {
            return ['text' => $this->rulesBrief($neighborhood), 'source' => 'rules'];
        }

        $brief = ['text' => $text, 'source' => 'ai'];
        Cache::put($key, $brief, now()->addMinutes(30));

        return $brief;
    }

    /**
     * @return array{text: string, source: 'ai'|'rules'}
     */
    public function answer(Neighborhood $neighborhood, string $question): array
    {
        $neighborhood->loadMissing('activeAlerts');

        $text = $this->ai->generate(
            self::SYSTEM,
            "Answer the resident's question in at most 100 words, using only the context for local facts.\n\n"
            ."Context:\n{$this->context($neighborhood)}\n\nQuestion: {$question}",
            600,
        );

        return $text !== null
            ? ['text' => $text, 'source' => 'ai']
            : ['text' => $this->rulesBrief($neighborhood).' (The AI assistant is not available right now, so this is the standard advice.)', 'source' => 'rules'];
    }

    /**
     * Draft the title and description of an alert for the admin form.
     *
     * @return array{title: string, description: string, source: 'ai'|'rules'}
     */
    public function draftAlert(Neighborhood $neighborhood, Severity $severity, ?float $temperature): array
    {
        $neighborhood->loadMissing('activeAlerts');
        $temperatureText = $temperature !== null ? "{$temperature} °C expected" : 'temperature not specified';

        $text = $this->ai->generate(
            self::SYSTEM,
            "Draft a public heat alert for the zone below. Severity: {$severity->label()}. {$temperatureText}.\n"
            ."Reply in exactly this format and nothing else:\nTITLE: <max 80 characters>\nDESCRIPTION: <max 60 words>\n\n"
            ."Context:\n{$this->context($neighborhood)}",
            500,
        );

        if ($text !== null && preg_match('/TITLE:\s*(.+)\R+DESCRIPTION:\s*(.+)/s', $text, $matches)) {
            return ['title' => trim($matches[1]), 'description' => trim($matches[2]), 'source' => 'ai'];
        }

        return [
            'title' => "{$severity->label()} heat alert - {$neighborhood->name}",
            'description' => ($temperature !== null ? "Up to {$temperature} °C expected. " : '').implode(' ', array_slice($severity->recommendations(), 0, 2)),
            'source' => 'rules',
        ];
    }

    private function context(Neighborhood $neighborhood): string
    {
        $alerts = $neighborhood->activeAlerts
            ->map(fn ($alert) => "- {$alert->severity_level->label()}: {$alert->title}".($alert->temperature !== null ? " ({$alert->temperature} °C)" : ''))
            ->implode("\n");

        return implode("\n", array_filter([
            "Zone: {$neighborhood->name}",
            'Temperature now: '.($neighborhood->current_temperature !== null ? "{$neighborhood->current_temperature} °C" : 'unknown'),
            'Forecast maximum (3 days): '.($neighborhood->forecast_max_temperature !== null ? "{$neighborhood->forecast_max_temperature} °C" : 'unknown'),
            'Current risk: '.($neighborhood->currentRisk()?->label() ?? 'no active alert'),
            $neighborhood->population !== null ? "Population: {$neighborhood->population}" : null,
            $neighborhood->vulnerableShare() !== null ? "Vulnerable people (elderly, children, ill): {$neighborhood->vulnerableShare()}%" : null,
            $alerts !== '' ? "Active alerts:\n{$alerts}" : 'Active alerts: none',
        ]));
    }

    private function rulesBrief(Neighborhood $neighborhood): string
    {
        $risk = $neighborhood->currentRisk();

        if ($risk === null) {
            return "No active heat alert in {$neighborhood->name}. Stay hydrated and avoid long exposure to the sun in the afternoon.";
        }

        return "{$risk->label()} heat risk in {$neighborhood->name}. ".implode(' ', $risk->recommendations());
    }
}
