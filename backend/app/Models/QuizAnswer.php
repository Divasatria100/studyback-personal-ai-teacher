<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use Concerns\SerializesIso8601Dates;

    /**
     * quiz_answers has no created_at/updated_at columns; answered_at is
     * the only timestamp and is populated by the application.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_question_id',
        'submitted_answer',
        'is_correct',
        'ai_feedback',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}