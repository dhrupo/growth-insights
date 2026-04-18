<?php

namespace App\Services\Analysis;

class SkillSignalBuilder
{
    public function build(array $facts): array
    {
        $languages = $facts['language_shares'] ?? [];
        $repoNames = $facts['repo_names'] ?? [];
        $commitMessages = $facts['commit_messages'] ?? [];
        $pullRequestTitles = $facts['pull_request_titles'] ?? [];
        $issues = $facts['issue_titles'] ?? [];
        $combinedText = array_merge($repoNames, $commitMessages, $pullRequestTitles, $issues);

        $signals = [
            $this->buildBackendSignal($languages, $combinedText),
            $this->buildFrontendSignal($languages, $combinedText),
            $this->buildFullStackSignal($languages),
            $this->buildTestingSignal($combinedText),
            $this->buildDevOpsSignal($combinedText),
            $this->buildCollaborationSignal($facts),
        ];

        return array_values(array_filter($signals, static fn (array $signal): bool => $signal['score'] > 0));
    }

    private function buildBackendSignal(array $languages, array $text): array
    {
        $phpShare = (float) ($languages['php'] ?? 0);
        $backendHits = $this->keywordHits($text, ['laravel', 'api', 'backend', 'service', 'controller', 'php']);

        return [
            'skill_key' => 'backend',
            'score' => $this->clamp($phpShare * 78 + min(22, $backendHits * 5)),
            'confidence' => $this->clamp(42 + min(30, $backendHits * 6) + min(20, $phpShare * 20)),
            'evidence' => [
                'language_share' => $phpShare,
                'keyword_hits' => $backendHits,
            ],
            'notes' => 'Backend signal is derived from PHP share and backend-oriented repository or commit language.',
        ];
    }

    private function buildFrontendSignal(array $languages, array $text): array
    {
        $jsShare = (float) (($languages['javascript'] ?? 0) + ($languages['typescript'] ?? 0) + ($languages['vue'] ?? 0));
        $frontendHits = $this->keywordHits($text, ['frontend', 'ui', 'vue', 'react', 'tailwind', 'component']);

        return [
            'skill_key' => 'frontend',
            'score' => $this->clamp($jsShare * 72 + min(28, $frontendHits * 5)),
            'confidence' => $this->clamp(40 + min(30, $frontendHits * 6) + min(20, $jsShare * 20)),
            'evidence' => [
                'language_share' => $jsShare,
                'keyword_hits' => $frontendHits,
            ],
            'notes' => 'Frontend signal is based on JavaScript/TypeScript/Vue usage and UI-focused repository terms.',
        ];
    }

    private function buildFullStackSignal(array $languages): array
    {
        $backend = (float) ($languages['php'] ?? 0);
        $frontend = (float) (($languages['javascript'] ?? 0) + ($languages['typescript'] ?? 0) + ($languages['vue'] ?? 0));

        return [
            'skill_key' => 'full_stack',
            'score' => $this->clamp(min($backend, $frontend) * 120),
            'confidence' => $this->clamp(34 + min(28, ($backend + $frontend) * 20)),
            'evidence' => [
                'backend_share' => $backend,
                'frontend_share' => $frontend,
            ],
            'notes' => 'Full-stack signal rises when backend and frontend shares are both material.',
        ];
    }

    private function buildTestingSignal(array $text): array
    {
        $hits = $this->keywordHits($text, ['test', 'tests', 'spec', 'phpunit', 'jest', 'pest', 'coverage', 'qa']);

        return [
            'skill_key' => 'testing_quality',
            'score' => $this->clamp($hits * 12),
            'confidence' => $this->clamp(30 + min(40, $hits * 10)),
            'evidence' => [
                'keyword_hits' => $hits,
            ],
            'notes' => 'Testing signal is inferred from test-related repository, commit, and PR language.',
        ];
    }

    private function buildDevOpsSignal(array $text): array
    {
        $hits = $this->keywordHits($text, ['ci', 'cd', 'deploy', 'docker', 'kubernetes', 'infra', 'devops', 'workflow', '.github']);

        return [
            'skill_key' => 'devops_ci',
            'score' => $this->clamp($hits * 12),
            'confidence' => $this->clamp(28 + min(40, $hits * 10)),
            'evidence' => [
                'keyword_hits' => $hits,
            ],
            'notes' => 'DevOps signal is inferred from CI/CD and infrastructure language in repo and activity metadata.',
        ];
    }

    private function buildCollaborationSignal(array $facts): array
    {
        $pullRequests = (int) ($facts['pull_requests_count'] ?? 0);
        $issues = (int) ($facts['issues_count'] ?? 0);
        $reposTouched = (int) ($facts['repos_touched'] ?? 0);

        return [
            'skill_key' => 'oss_collaboration',
            'score' => $this->clamp(($pullRequests * 9) + ($issues * 4) + ($reposTouched * 3)),
            'confidence' => $this->clamp(35 + min(35, $pullRequests * 3) + min(20, $reposTouched * 2)),
            'evidence' => [
                'pull_requests_count' => $pullRequests,
                'issues_count' => $issues,
                'repos_touched' => $reposTouched,
            ],
            'notes' => 'Collaboration signal uses PR, issue, and cross-repository participation counts.',
        ];
    }

    private function keywordHits(array $text, array $keywords): int
    {
        $count = 0;

        foreach ($text as $entry) {
            $haystack = strtolower((string) $entry);

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
