<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGitHubConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'github_username' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
            'access_token' => ['required', 'string', 'min:20'],
            'analysis_mode' => ['sometimes', 'string', Rule::in(['public_only', 'public_private'])],
        ];
    }
}
