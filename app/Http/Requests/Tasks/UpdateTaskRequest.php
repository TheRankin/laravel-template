<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'cancelled'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
