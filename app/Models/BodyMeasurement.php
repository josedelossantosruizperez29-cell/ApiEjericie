<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurement extends Model
{
    use HasUuids;

    protected $table = 'body_measurements';

    protected function casts(): array
    {
        return [
            'measured_at' => 'immutable_datetime',
            'value' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
