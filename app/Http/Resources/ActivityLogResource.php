<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'causer_id' => $this->causer_id,
            'causer' => new UserResource($this->whenLoaded('causer')),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties,
            'project_id' => $this->project_id,
            'team_id' => $this->team_id,
            'created_at' => $this->created_at,
        ];
    }
}
