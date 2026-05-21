<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class ProjectMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The underlying resource can either be a User with a `pivot` (when listed
     * through the belongsToMany relationship) or a ProjectMember model directly.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $pivot = $resource->pivot ?? null;

        $role = $resource->role ?? ($pivot->role ?? null);
        $projectId = $resource->project_id ?? ($pivot->project_id ?? null);
        $userId = $resource->user_id ?? ($pivot->user_id ?? $resource->id ?? null);

        $userPayload = new MissingValue();

        if (isset($resource->name) && isset($resource->email)) {
            $userPayload = new UserResource($resource);
        } elseif ($resource->relationLoaded('user')) {
            $userPayload = new UserResource($resource->user);
        }

        return [
            'id' => $resource->id,
            'project_id' => $projectId,
            'user_id' => $userId,
            'role' => $role,
            'user' => $userPayload,
            'created_at' => $resource->created_at ?? ($pivot->created_at ?? null),
            'updated_at' => $resource->updated_at ?? ($pivot->updated_at ?? null),
        ];
    }
}
