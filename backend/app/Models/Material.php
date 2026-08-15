<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use Concerns\SerializesIso8601Dates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'original_filename',
        'file_path',
        'file_size_bytes',
        'status',
        'failed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
        ];
    }

    private ?float $masteryOverride = null;

    /**
     * Preloads an already-aggregated overall mastery value so list payloads
     * do not trigger a per-material query (N+1) — see MaterialRepository::paginateOwnedByUser.
     */
    public function setOverallMasteryOverride(float $mastery): void
    {
        $this->masteryOverride = $mastery;
    }

    /**
     * Current overall mastery, computed on the fly as the average subtopic
     * mastery score across the material's subtopics (Database Design §8).
     * Returns 0 when the material has never been studied (no subtopics).
     */
    public function overallMastery(): float
    {
        if ($this->masteryOverride !== null) {
            return $this->masteryOverride;
        }

        $score = $this->topics()
            ->with('subtopics')
            ->get()
            ->flatMap(fn ($topic) => $topic->subtopics)
            ->avg('mastery_score');

        return (float) $score ?? 0.0;
    }

    /**
     * The number of identified topics for this material.
     */
    public function topicCount(): int
    {
        return $this->topics()->count();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('order_index');
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
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }
}