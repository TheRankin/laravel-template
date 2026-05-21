<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $type, array $data): ?AppNotification
    {
        $actorId = $data['actor_id'] ?? null;

        if ($actorId !== null && $user->id === $actorId) {
            return null;
        }

        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function notifyMany(iterable $users, string $type, array $data, ?User $exceptActor = null): void
    {
        $exceptId = $exceptActor?->id;
        $seen = [];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            if ($exceptId !== null && $user->id === $exceptId) {
                continue;
            }

            if (isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;

            $this->notify($user, $type, $data);
        }
    }
}
