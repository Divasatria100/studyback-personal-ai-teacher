<?php

namespace App\Http\Resources;

use App\Models\StudySession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Study session payload (API Design §11).
 */
class StudySessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StudySession $session */
        $session = $this->resource;

        return [
            'id' => $session->id,
            'material_id' => $session->material_id,
            'mode' => $session->mode,
            'difficulty' => $session->difficulty,
            'status' => $session->status,
            'topic_ids' => $session->topics->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'started_at' => $session->started_at,
            'ended_at' => $session->ended_at,
        ];
    }
}
