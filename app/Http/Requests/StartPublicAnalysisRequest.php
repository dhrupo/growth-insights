<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPublicAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'github_username' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
