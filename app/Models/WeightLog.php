<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightLog extends Model
{
    use HasUuids;

    protected $table = 'weight_logs';

    protected function casts(): array
    {
        return [
            'measured_at' => 'immutable_datetime',
            'weight_kg' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
