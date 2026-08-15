<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuizQuestion extends Model
{
    use Concerns\SerializesIso8601Dates;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'subtopic_id',
        'question_type',
        'question_text',
        'options',
        'correct_answer',
        'order_index',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'order_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(Subtopic::class);
    }

    /**
     * @return HasOne<Model, $this>
     */
    public function answer(): HasOne
    {
        return $this->hasOne(QuizAnswer::class);
    }
}