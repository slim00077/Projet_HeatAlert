<?php

namespace App\Services;

use App\Models\ResidentReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * AI Incident Lens: multimodal analysis (optional photo + resident description) that helps a resident
 * write a clearer report. Everything returned is a suggestion the resident reviews before submitting:
 * observations are limited to what is visible or stated, and possible causes are hypotheses.
 */
class IncidentLensService
{
    private const CONFIDENCE = ['low', 'medium', 'high'];

    private const PHOTO_ASSESSMENTS = ['relevant', 'unclear', 'unrelated', 'none'];

    private const SYSTEM = 'You are the HeatAlert Incident Lens. You help a resident describe a building problem clearly for their residence manager. '
        .'Describe only what is visible in the photo or stated by the resident; do not guess hidden details. Never present a cause or diagnosis as confirmed: '
        .'phrase causes as possibilities. Do not identify people in the photo and never include names, apartment numbers or contact details. '
        .'If the photo is unrelated to a building problem, say so.';

    public function __construct(private readonly StructuredAi $ai) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(?string $title, string $description, ?UploadedFile $photo): array
    {
        $base = [
            'source' => 'unavailable',
            'model' => null,
            'photo_sent' => $photo !== null,
        ];

        if ($photo !== null && ! $this->ai->supportsImages()) {
            return $base + ['notice' => $this->ai->failureNotice().'. The AI Incident Lens needs Gemini to read photos, so no analysis was produced.'];
        }

        $prompt = $this->prompt($title, $description, $photo !== null);

        $reply = $photo !== null
            ? $this->ai->jsonWithImage(self::SYSTEM, $prompt, (string) $photo->get(), (string) $photo->getMimeType(), 1600)
            : $this->ai->json(self::SYSTEM, $prompt, 1600);

        if ($reply === null) {
            return $base + ['notice' => $this->ai->failureNotice().'. No AI analysis was produced; you can still write and submit your report yourself.'];
        }

        $draft = is_array($reply['draft'] ?? null) ? $reply['draft'] : [];
        $category = is_string($reply['suggested_category'] ?? null) && array_key_exists($reply['suggested_category'], ResidentReport::CATEGORIES)
            ? $reply['suggested_category'] : null;

        if (! is_array($reply['observations'] ?? null) || ! is_string($draft['description'] ?? null)) {
            return $base + ['notice' => 'The AI replied, but not with the expected analysis structure, so nothing is shown.'];
        }

        return [
            'source' => 'ai',
            'model' => $this->ai->model(),
            'notice' => 'AI suggestions to review: observations describe what the AI could see or read, and possible causes are not confirmed.',
            'analysis' => [
                'photo_assessment' => in_array($reply['photo_assessment'] ?? null, self::PHOTO_ASSESSMENTS, true) ? $reply['photo_assessment'] : ($photo ? 'unclear' : 'none'),
                'observations' => collect($reply['observations'])->filter(fn ($item) => is_array($item) && is_string($item['detail'] ?? null))
                    ->map(fn (array $item) => [
                        'detail' => $this->clean($item['detail'], 200),
                        'source' => in_array($item['source'] ?? null, ['photo', 'description'], true) ? $item['source'] : 'description',
                    ])->take(6)->values()->all(),
                'suggested_category' => $category,
                'suggested_category_label' => $category !== null ? ResidentReport::CATEGORIES[$category] : null,
                'category_reason' => is_string($reply['category_reason'] ?? null) ? $this->clean($reply['category_reason'], 200) : null,
                'confidence' => in_array($reply['confidence'] ?? null, self::CONFIDENCE, true) ? $reply['confidence'] : 'low',
                'possible_causes' => $this->strings($reply['possible_causes'] ?? [], 3),
                'uncertainties' => $this->strings($reply['uncertainties'] ?? [], 4),
                'follow_up_questions' => $this->strings($reply['follow_up_questions'] ?? [], 4),
                'safety_note' => is_string($reply['safety_note'] ?? null) && trim($reply['safety_note']) !== '' ? $this->clean($reply['safety_note'], 240) : null,
                'draft' => [
                    'title' => $this->clean(is_string($draft['title'] ?? null) ? $draft['title'] : (string) $title, 120),
                    'description' => $this->clean($draft['description'], 1200),
                ],
            ],
        ] + $base;
    }

    private function prompt(?string $title, string $description, bool $hasPhoto): string
    {
        $categories = collect(ResidentReport::CATEGORIES)->map(fn (string $label, string $key) => "{$key} ({$label})")->implode(', ');

        return ($hasPhoto ? "The resident attached the photo above.\n" : "No photo was attached.\n")
            .'Resident title: '.Str::limit((string) $title, 200)."\nResident description: ".Str::limit($description, 2000)."\n\n"
            ."Allowed categories: {$categories}.\n\n"
            .'Return {"photo_assessment":"relevant|unclear|unrelated|none",'
            .'"observations":[{"detail":"<one observable fact>","source":"photo|description"}],'
            .'"suggested_category":"<one allowed category key>","category_reason":"<one sentence>","confidence":"low|medium|high",'
            .'"possible_causes":["<a possible cause, phrased as a possibility>"],"uncertainties":["<what cannot be determined>"],'
            .'"follow_up_questions":["<a question whose answer would help the manager>"],"safety_note":"<only if there is an immediate safety risk, else empty>",'
            .'"draft":{"title":"<at most 80 characters>","description":"<clear, factual report of at most 120 words, in the resident\'s voice, without names>"}}. '
            .'At most 6 observations, 3 possible causes, 4 uncertainties and 4 follow-up questions.';
    }

    /**
     * @param  mixed  $values
     * @return list<string>
     */
    private function strings($values, int $limit): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $this->clean($value, 220))
            ->take($limit)->values()->all();
    }

    private function clean(string $value, int $limit): string
    {
        return Str::limit(trim(strip_tags($value)), $limit);
    }
}
