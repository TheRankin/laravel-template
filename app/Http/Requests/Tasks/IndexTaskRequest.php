<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'cancelled'])],
            'priority' => ['sometimes', 'nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'label_id' => ['sometimes', 'nullable', 'integer', 'exists:labels,id'],
            'due_before' => ['sometimes', 'nullable', 'date'],
            'due_after' => ['sometimes', 'nullable', 'date'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'overdue' => ['sometimes', 'nullable', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'root' => ['sometimes', 'nullable', 'boolean'],
            'sort' => ['sometimes', 'nullable', Rule::in(['created_at', 'due_date', 'priority', 'position', 'title'])],
            'dir' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
