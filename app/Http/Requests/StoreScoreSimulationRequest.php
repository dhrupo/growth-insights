<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoreSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'current_consistency_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'current_diversity_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'current_contribution_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'extra_prs_per_week' => ['sometimes', 'numeric', 'min:0', 'max:20'],
            'extra_commits_per_week' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'extra_repos' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'extra_languages' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'extra_testing_signals' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'extra_collaboration_signals' => ['sometimes', 'numeric', 'min:0', 'max:10'],
        ];
    }
}
