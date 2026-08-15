<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopicTreeResource;
use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\TopicRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Topics / Learning State endpoint (API Design §10): the topic/subtopic tree
 * with current mastery/status, read through the owning material.
 */
class TopicController extends Controller
{
    public function __construct(
        private readonly MaterialRepositoryInterface $materials,
        private readonly TopicRepositoryInterface $topics,
    ) {}

    public function index(Request $request, int $material): JsonResponse
    {
        $material = $this->materials->findOwnedByUser($request->user()->id, (int) $material);

        abort_if($material === null, 404);

        return response()->json([
            'material_id' => $material->id,
            'overall_mastery' => $material->overallMastery(),
            'topics' => TopicTreeResource::collection($this->topics->treeForMaterial($material->id))->resolve(),
        ]);
    }
}
