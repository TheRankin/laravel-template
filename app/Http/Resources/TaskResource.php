<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dueDate = $this->due_date;
        $isOverdue = false;

        if ($dueDate && ! in_array($this->status, ['done', 'cancelled'], true)) {
            $dueCarbon = $dueDate instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance($dueDate)
                : \Illuminate\Support\Carbon::parse($dueDate);
            $isOverdue = $dueCarbon->isPast();
        }

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assignee_id' => $this->assignee_id,
            'reporter_id' => $this->reporter_id,
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'due_date' => $this->due_date,
            'completed_at' => $this->completed_at,
            'position' => $this->position,
            'is_overdue' => $isOverdue,
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'subtasks_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
