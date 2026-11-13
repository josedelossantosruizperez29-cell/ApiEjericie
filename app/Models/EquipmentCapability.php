<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentCapability extends Model
{
    use HasUuids;

    protected $table = 'equipment_capabilities';

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function exerciseEquipmentCapability(): HasMany
    {
        return $this->hasMany(ExerciseEquipment::class, 'capability_id');
    }
}
