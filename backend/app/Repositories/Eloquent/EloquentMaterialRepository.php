<?php

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentMaterialRepository implements MaterialRepositoryInterface
{
    public function create(array $attributes): Material
    {
        return Material::query()->create($attributes);
    }

    public function findById(int $id): ?Material
    {
        return Material::query()->find($id);
    }

    public function findOwnedByUser(int $userId, int $materialId): ?Material
    {
        return Material::query()
            ->where('user_id', $userId)
            ->find($materialId);
    }

    public function paginateOwnedByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Material::query()->where('user_id', $userId);

        if (! empty($filters['search'])) {
            $query->whereLower('title', 'like', '%'.mb_strtolower($filters['search']).'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'recent';
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        $query->when($sort === 'title', function ($q) {
            $q->orderBy('title', 'asc');
        }, function ($q) {
            $q->orderBy('created_at', 'desc');
        });

        $paginator = $query->paginate($perPage);

        // Preload aggregated overall mastery to avoid per-item aggregate queries.
        $masteryMap = $this->masteryByMaterial(
            $paginator->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $paginator->getCollection()->each(function (Material $material) use ($masteryMap) {
            $material->setOverallMasteryOverride($masteryMap[$material->id] ?? 0.0);
        });

        return $paginator;
    }

    public function masteryByMaterial(array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }

        $rows = DB::table('subtopics')
            ->join('topics', 'topics.id', '=', 'subtopics.topic_id')
            ->whereIn('topics.material_id', $materialIds)
            ->selectRaw('topics.material_id, AVG(subtopics.mastery_score) AS mastery')
            ->groupBy('topics.material_id')
            ->get();

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->material_id => (float) $row->mastery])
            ->all();
    }

    public function updateProcessingState(int $materialId, string $status, ?string $failedReason = null): void
    {
        Material::query()
            ->whereKey($materialId)
            ->update([
                'status' => $status,
                'failed_reason' => $failedReason,
            ]);
    }
}