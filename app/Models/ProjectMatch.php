<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMatch extends Model
{
    protected $fillable = ['matching_profile_id', 'project_id', 'score', 'breakdown', 'source_hash', 'scored_at'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'breakdown' => 'array', 'scored_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MatchingProfile::class, 'matching_profile_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
