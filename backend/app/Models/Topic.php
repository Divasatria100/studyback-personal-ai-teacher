<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use Concerns\SerializesIso8601Dates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'material_id',
        'name',
        'description',
        'order_index',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(Subtopic::class)->orderBy('order_index');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function studySessions(): BelongsToMany
    {
        return $this->belongsToMany(StudySession::class, 'study_session_topics');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}