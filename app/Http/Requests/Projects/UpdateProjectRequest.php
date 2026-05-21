<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = is_object($project) && method_exists($project, 'getKey') ? $project->getKey() : $project;
        $teamId = is_object($project) && isset($project->team_id) ? $project->team_id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:16',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('projects', 'key')
                    ->where(fn ($q) => $teamId ? $q->where('team_id', $teamId) : $q)
                    ->ignore($projectId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'archived'])],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
