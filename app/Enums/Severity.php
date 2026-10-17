<?php

namespace App\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    /**
     * Matches the soft-* colour classes of the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'orange',
            self::High => 'coral',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => '#10b981',
            self::Medium => '#f59e0b',
            self::High => '#ef4444',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }

    /**
     * Whether residents are notified when an alert of this level starts.
     */
    public function notifiesResidents(): bool
    {
        return $this !== self::Low;
    }

    /**
     * @return list<string>
     */
    public function recommendations(): array
    {
        return match ($this) {
            self::Low => [
                'Drink water regularly, even if you are not thirsty.',
                'Wear light, loose-fitting clothes.',
            ],
            self::Medium => [
                'Avoid strenuous outdoor activity between 12:00 and 16:00.',
                'Keep curtains closed on sun-facing windows and ventilate at night.',
                'Check on elderly neighbours and relatives.',
            ],
            self::High => [
                'Stay indoors in the coolest room, especially between 12:00 and 16:00.',
                'Drink water often and avoid alcohol and caffeine.',
                'Check on elderly, ill and isolated people twice a day.',
                'Call emergency services if you notice confusion, nausea or fainting.',
            ],
        };
    }

    public static function fromTemperature(float $celsius): self
    {
        return match (true) {
            $celsius >= 40 => self::High,
            $celsius >= 35 => self::Medium,
            default => self::Low,
        };
    }
}
