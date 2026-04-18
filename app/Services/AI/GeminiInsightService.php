<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GeminiInsightService
{
    private const PROMPT_VERSION = '1';

    public function __construct(private readonly Factory $http)
    {
    }

    public function enabled(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function enhance(array $analysisFacts): array
    {
        $snapshotHash = $this->snapshotHash($analysisFacts);
        $primaryModel = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $fallbackModel = (string) config('services.gemini.fallback_model', 'gemini-2.0-flash');
        $cacheKey = "gemini:analysis:{$snapshotHash}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['cached'] = true;

                return $cached;
            }

            return $this->fallback($snapshotHash, $primaryModel, 'cache_corrupted');
        }

        if (! $this->enabled()) {
            return $this->fallback($snapshotHash, $primaryModel, 'missing_api_key');
        }

        $models = array_values(array_unique(array_filter([$primaryModel, $fallbackModel])));
        $lastError = null;

        foreach ($models as $model) {
            try {
                $enhancement = $this->requestEnhancement($model, $analysisFacts, $snapshotHash);

                Cache::put(
                    $cacheKey,
                    $enhancement,
                    now()->addMinutes((int) config('services.gemini.cache_ttl_minutes', 10080)),
                );

                return $enhancement;
            } catch (Throwable $throwable) {
                $lastError = $throwable->getMessage();
            }
        }

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['cached'] = true;

                return $cached;
            }
        }

        return $this->fallback($snapshotHash, $primaryModel, $lastError ?? 'gemini_unavailable');
    }

    public function snapshotHash(array $analysisFacts): string
    {
        $snapshot = [
            'prompt_version' => self::PROMPT_VERSION,
            'analysis_mode' => Arr::get($analysisFacts, 'profile.analysis_mode'),
            'github_username' => Arr::get($analysisFacts, 'profile.login'),
            'window_start' => Arr::get($analysisFacts, 'window_start'),
            'window_end' => Arr::get($analysisFacts, 'window_end'),
            'metrics' => Arr::get($analysisFacts, 'metrics', []),
            'strengths' => Arr::get($analysisFacts, 'strengths', []),
            'weaknesses' => Arr::get($analysisFacts, 'weaknesses', []),
            'weekly_plan' => Arr::get($analysisFacts, 'weekly_plan', []),
            'recommendations' => Arr::get($analysisFacts, 'recommendations', []),
            'skill_signals' => Arr::get($analysisFacts, 'skill_signals', []),
            'evidence_summary' => Arr::get($analysisFacts, 'evidence_summary'),
        ];

        return hash('sha256', json_encode($this->sortForHash($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function buildPrompt(array $analysisFacts, string $snapshotHash): string
    {
        $prompt = [
            'role' => 'You are a strict product analyst for developer growth insights.',
            'rules' => [
                'Only use the structured facts provided.',
                'Do not overwrite or contradict rule-based evidence.',
                'Do not infer job, salary, burnout, or hiring outcomes.',
                'Return concise, actionable wording.',
                'Keep each note short and grounded in evidence.',
                'Reference existing rule-based items rather than inventing new evidence.',
            ],
            'output_requirements' => [
                'Return JSON that matches the schema.',
                'Provide one AI note per recommendation and one note per weekly plan item.',
                'Keep summary to 2-3 short sentences.',
                'If no meaningful enhancement is possible, leave notes empty but still return valid JSON.',
            ],
            'snapshot_hash' => $snapshotHash,
            'analysis' => [
                'profile' => Arr::get($analysisFacts, 'profile', []),
                'metrics' => Arr::get($analysisFacts, 'metrics', []),
                'strengths' => Arr::get($analysisFacts, 'strengths', []),
                'weaknesses' => Arr::get($analysisFacts, 'weaknesses', []),
                'weekly_plan' => Arr::get($analysisFacts, 'weekly_plan', []),
                'recommendations' => Arr::get($analysisFacts, 'recommendations', []),
                'skill_signals' => Arr::get($analysisFacts, 'skill_signals', []),
                'weekly_buckets' => Arr::get($analysisFacts, 'weekly_buckets', []),
                'evidence_summary' => Arr::get($analysisFacts, 'evidence_summary'),
            ],
            'requested_enhancement' => [
                'summary',
                'weekly_plan_notes',
                'recommendation_notes',
            ],
        ];

        return json_encode($prompt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => 'Short AI summary that adds nuance but does not replace rule-based findings.',
                ],
                'weekly_plan_notes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer'],
                            'ai_note' => ['type' => 'string'],
                        ],
                        'required' => ['index', 'ai_note'],
                    ],
                ],
                'recommendation_notes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer'],
                            'ai_note' => ['type' => 'string'],
                            'confidence' => ['type' => 'string'],
                        ],
                        'required' => ['index', 'ai_note'],
                    ],
                ],
            ],
            'required' => ['summary', 'weekly_plan_notes', 'recommendation_notes'],
        ];
    }

    private function normalizeResponse(array $payload): array
    {
        $text = data_get($payload, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini response did not contain structured content.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini response was not valid JSON.');
        }

        return $decoded;
    }

    private function formatEnhancement(string $snapshotHash, string $model, array $payload, bool $cached, ?string $error): array
    {
        return [
            'status' => $error ? 'failed' : 'enhanced',
            'cached' => $cached,
            'model' => $model,
            'snapshot_hash' => $snapshotHash,
            'generated_at' => now()->toIso8601String(),
            'summary' => (string) ($payload['summary'] ?? ''),
            'weekly_plan_notes' => array_values(array_map(static fn (array $item): array => [
                'index' => (int) ($item['index'] ?? 0),
                'ai_note' => (string) ($item['ai_note'] ?? ''),
            ], $payload['weekly_plan_notes'] ?? [])),
            'recommendation_notes' => array_values(array_map(static fn (array $item): array => [
                'index' => (int) ($item['index'] ?? 0),
                'ai_note' => (string) ($item['ai_note'] ?? ''),
                'confidence' => (string) ($item['confidence'] ?? 'medium'),
            ], $payload['recommendation_notes'] ?? [])),
            'error' => $error,
        ];
    }

    private function fallback(string $snapshotHash, string $model, string $error): array
    {
        return $this->formatEnhancement(
            snapshotHash: $snapshotHash,
            model: $model,
            payload: [
                'summary' => '',
                'weekly_plan_notes' => [],
                'recommendation_notes' => [],
            ],
            cached: false,
            error: $error,
        );
    }

    private function endpointPath(string $model): string
    {
        return sprintf('/v1beta/models/%s:generateContent', $model);
    }

    private function requestEnhancement(string $model, array $analysisFacts, string $snapshotHash): array
    {
        $response = $this->http
            ->baseUrl((string) config('services.gemini.base_uri', 'https://generativelanguage.googleapis.com'))
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('services.gemini.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->retry(2, 400)
            ->timeout((int) config('services.gemini.timeout_seconds', 20))
            ->post($this->endpointPath($model), [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->buildPrompt($analysisFacts, $snapshotHash),
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $this->responseSchema(),
                    'temperature' => 0.2,
                    'topP' => 0.8,
                ],
            ])
            ->throw();

        $payload = $this->normalizeResponse($response->json());

        return $this->formatEnhancement($snapshotHash, $model, $payload, false, null);
    }

    private function sortForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortForHash($item);
        }

        return $value;
    }
}
