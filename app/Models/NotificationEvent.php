<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEvent extends Model
{
    use HasUuids;

    protected $table = 'notification_events';

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'attempts' => 'integer',
            'delivered_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
