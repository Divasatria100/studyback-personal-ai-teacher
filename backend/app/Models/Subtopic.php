<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subtopic extends Model
{
    use Concerns\SerializesIso8601Dates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'topic_id',
        'name',
        'description',
        'order_index',
        'mastery_score',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mastery_score' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}