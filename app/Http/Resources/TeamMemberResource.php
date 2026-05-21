<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The underlying resource can either be a User with a `pivot` (when listed
     * through the belongsToMany relationship) or a TeamMember model directly.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $pivot = $resource->pivot ?? null;

        $role = $resource->role ?? ($pivot->role ?? null);
        $teamId = $resource->team_id ?? ($pivot->team_id ?? null);

        // When the wrapped resource is the User itself (via belongsToMany),
        // user_id is the model's primary key. When wrapped resource is the
        // TeamMember pivot model, user_id is a column on it.
        $userId = $resource->user_id ?? ($pivot->user_id ?? $resource->id ?? null);

        $userPayload = new MissingValue();

        if (isset($resource->name) && isset($resource->email)) {
            // It's a User instance directly.
            $userPayload = new UserResource($resource);
        } elseif ($resource->relationLoaded('user')) {
            $userPayload = new UserResource($resource->user);
        }

        return [
            'id' => $resource->id,
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role,
            'user' => $userPayload,
            'created_at' => $resource->created_at ?? ($pivot->created_at ?? null),
            'updated_at' => $resource->updated_at ?? ($pivot->updated_at ?? null),
        ];
    }
}
