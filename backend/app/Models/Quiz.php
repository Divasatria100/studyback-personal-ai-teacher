<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use Concerns\SerializesIso8601Dates, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'study_session_id',
        'topic_id',
        'subtopic_id',
        'difficulty',
        'status',
        'total_questions',
        'correct_count',
        'score',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => 'string',
            'total_questions' => 'integer',
            'correct_count' => 'integer',
            'score' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(Subtopic::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order_index');
    }
}
