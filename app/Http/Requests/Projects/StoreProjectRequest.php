<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = is_object($team) && method_exists($team, 'getKey') ? $team->getKey() : $team;

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                'max:16',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('projects', 'key')->where(fn ($q) => $q->where('team_id', $teamId)),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'archived'])],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
