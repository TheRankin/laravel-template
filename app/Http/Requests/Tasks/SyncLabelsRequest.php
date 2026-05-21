<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class SyncLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label_ids' => ['present', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }
}
