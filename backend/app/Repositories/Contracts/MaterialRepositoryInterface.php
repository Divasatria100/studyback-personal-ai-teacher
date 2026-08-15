<?php

namespace App\Repositories\Contracts;

use App\Models\Material;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MaterialRepositoryInterface
{
    public function create(array $attributes): Material;

    public function findById(int $id): ?Material;

    public function findOwnedByUser(int $userId, int $materialId): ?Material;

    /**
     * List the user's materials with optional search/status filtering and sorting.
     *
     * @param  array{search?: string|null, status?: string|null, sort?: string, per_page?: int}  $filters
     */
    public function paginateOwnedByUser(int $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Map of material_id => average subtopic mastery score for the given material ids.
     *
     * @param  list<int>  $materialIds
     * @return array<int, float>
     */
    public function masteryByMaterial(array $materialIds): array;

    public function updateProcessingState(int $materialId, string $status, ?string $failedReason = null): void;
}