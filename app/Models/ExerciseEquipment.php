<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseEquipment extends Model
{
    use HasUuids;

    protected $table = 'exercise_equipment';

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(EquipmentCapability::class, 'capability_id');
    }
}
