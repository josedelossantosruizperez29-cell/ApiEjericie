<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasUuids;

    protected $table = 'user_profiles';

    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            'age_years' => 'integer',
            'age_as_of' => 'immutable_date',
            'weight_kg' => 'decimal:3',
            'height_cm' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
