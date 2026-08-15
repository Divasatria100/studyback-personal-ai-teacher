<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudySession extends Model
{
    use Concerns\SerializesIso8601Dates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'material_id',
        'mode',
        'difficulty',
        'status',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'study_session_topics')
            ->orderBy('order_index');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}