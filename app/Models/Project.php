<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['platform', 'external_project_id', 'canonical_url', 'title', 'description', 'budget', 'deadline', 'required_skills', 'discovered_at', 'availability_status', 'decision_status', 'approved_at', 'rejected_at', 'is_demo'];

    protected function casts(): array
    {
        return ['required_skills' => 'array', 'discovered_at' => 'datetime', 'deadline' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ProjectActivityEvent::class);
    }
}
