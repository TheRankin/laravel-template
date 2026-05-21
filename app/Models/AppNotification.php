<?php

namespace App\Models;

use Database\Factories\AppNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'data', 'read_at'])]
class AppNotification extends Model
{
    /** @use HasFactory<AppNotificationFactory> */
    use HasFactory;

    protected $table = 'app_notifications';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
