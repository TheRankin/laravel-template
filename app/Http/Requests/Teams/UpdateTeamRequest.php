<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teamId = $this->route('team');

        if (is_object($teamId) && method_exists($teamId, 'getKey')) {
            $teamId = $teamId->getKey();
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams', 'slug')->ignore($teamId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
