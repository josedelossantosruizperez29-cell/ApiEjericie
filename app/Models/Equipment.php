<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasUuids;

    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function equipmentCapabilities(): HasMany
    {
        return $this->hasMany(EquipmentCapability::class, 'equipment_id');
    }

    public function userEquipment(): HasMany
    {
        return $this->hasMany(UserEquipment::class, 'equipment_id');
    }

    public function exerciseEquipment(): HasMany
    {
        return $this->hasMany(ExerciseEquipment::class, 'equipment_id');
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_equipment');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_equipment');
    }
}
