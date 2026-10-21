<?php

namespace App\Enums;

/**
 * Incident Command Center workflow: Reported → Under Investigation → Action in Progress → Awaiting Verification → Resolved.
 */
enum ResponseStage: string
{
    case Reported = 'reported';
    case Investigating = 'investigating';
    case Action = 'action';
    case Verification = 'verification';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Investigating => 'Under Investigation',
            self::Action => 'Action in Progress',
            self::Verification => 'Awaiting Verification',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * What the stage means, worded for residents.
     */
    public function description(): string
    {
        return match ($this) {
            self::Reported => 'The issue was received and grouped into a response case.',
            self::Investigating => 'The residence team is checking the cause and the evidence.',
            self::Action => 'Repairs or other actions are under way.',
            self::Verification => 'The work is done and is being checked. Tell us if the issue still appears.',
            self::Resolved => 'The response case is closed.',
        };
    }

    /**
     * Matches the soft-* colour classes of the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'coral',
            self::Investigating => 'purple',
            self::Action => 'orange',
            self::Verification => 'blue',
            self::Resolved => 'green',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Reported => 'file',
            self::Investigating => 'search',
            self::Action => 'cog',
            self::Verification => 'eye',
            self::Resolved => 'check',
        };
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }

    /**
     * Share of the workflow completed when the case sits at this stage (0 to 100).
     */
    public function percent(): int
    {
        return (int) round($this->position() / (count(self::cases()) - 1) * 100);
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
