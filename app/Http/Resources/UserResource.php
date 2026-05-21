<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $canSeeEmail = $authUser
            && (
                $authUser->getKey() === $this->resource->getKey()
                || (isset($authUser->role) && $authUser->role === 'admin')
            );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canSeeEmail, fn () => $this->email),
            'role' => $this->role,
            'avatar_url' => $this->avatar_url,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at,
        ];
    }
}
