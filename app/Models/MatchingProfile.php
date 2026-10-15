<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchingProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'preferred_skills', 'positive_keywords', 'negative_keywords', 'min_budget', 'max_budget', 'preferred_currency', 'min_score', 'enabled'];

    protected function casts(): array
    {
        return ['preferred_skills' => 'array', 'positive_keywords' => 'array', 'negative_keywords' => 'array', 'min_budget' => 'decimal:2', 'max_budget' => 'decimal:2', 'min_score' => 'integer', 'enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ProjectMatch::class);
    }
}
