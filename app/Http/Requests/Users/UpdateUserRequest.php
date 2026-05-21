<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        if (is_object($userId) && method_exists($userId, 'getKey')) {
            $userId = $userId->getKey();
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'member'])],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'timezone' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }
}
