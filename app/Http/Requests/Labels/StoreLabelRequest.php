<?php

namespace App\Http\Requests\Labels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = is_object($project) && method_exists($project, 'getKey') ? $project->getKey() : $project;

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('labels', 'name')->where(fn ($q) => $q->where('project_id', $projectId)),
            ],
            'color' => ['required', 'string', 'max:32', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
        ];
    }
}
