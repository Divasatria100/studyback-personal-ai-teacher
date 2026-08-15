<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Material payload (API Design §8). Nested flag includes detail-only fields.
 */
class MaterialResource extends JsonResource
{
    /**
     * @param  bool  $withDetail  include original_filename / file_size_bytes
     */
    public function __construct($resource, private readonly bool $withDetail = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Material $material */
        $material = $this->resource;

        $payload = [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description,
            'status' => $material->status,
            'topics_count' => $material->topicCount(),
            'overall_mastery' => $material->overallMastery(),
            'created_at' => $material->created_at,
        ];

        if ($this->withDetail) {
            $payload['original_filename'] = $material->original_filename;
            $payload['file_size_bytes'] = $material->file_size_bytes;
        }

        return $payload;
    }
}
