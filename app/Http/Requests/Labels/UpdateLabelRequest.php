<?php

namespace App\Http\Requests\Labels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $label = $this->route('label');
        $labelId = is_object($label) && method_exists($label, 'getKey') ? $label->getKey() : $label;
        $projectId = is_object($label) && isset($label->project_id) ? $label->project_id : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('labels', 'name')
                    ->where(fn ($q) => $projectId ? $q->where('project_id', $projectId) : $q)
                    ->ignore($labelId),
            ],
            'color' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
        ];
    }
}
