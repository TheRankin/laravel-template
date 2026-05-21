<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'cancelled'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'due_date' => ['nullable', 'date'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }
}
