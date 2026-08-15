<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    use Concerns\SerializesIso8601Dates;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'material_id',
        'topic_id',
        'subtopic_id',
        'content',
        'chunk_index',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
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
}