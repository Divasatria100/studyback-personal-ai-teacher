<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Materials\StoreMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Materials\MaterialProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Material module endpoints (API Design §8). Ownership is enforced by scoping
 * every lookup to the authenticated user; a resource owned by someone else is
 * indistinguishable from a nonexistent one (404).
 */
class MaterialController extends Controller
{
    public function __construct(
        private readonly MaterialRepositoryInterface $materials,
        private readonly MaterialProcessingService $processing,
    ) {}

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $filePath = $file->store('materials', 'local');

        if ($filePath === false) {
            return new JsonResponse([
                'message' => 'The uploaded file could not be stored. Please try again.',
            ], 500);
        }

        $material = $this->materials->create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title') ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $request->input('description'),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_size_bytes' => $file->getSize(),
            'status' => 'processing',
        ]);

        try {
            $success = $this->processing->process($material->id, Storage::disk('local')->path($filePath));

            $material->refresh();

            if (! $success) {
                return new JsonResponse(new MaterialResource($material), 422);
            }

            return new JsonResponse(new MaterialResource($material), 201);
        } catch (AiProviderException) {
            $material->refresh();

            return new JsonResponse([
                'message' => 'The AI provider is temporarily unavailable. Please try again.',
                'material' => new MaterialResource($material),
            ], 503);
        }
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'sort' => $request->query('sort', 'recent'),
            'per_page' => (int) $request->query('per_page', 20),
        ];

        $paginator = $this->materials->paginateOwnedByUser($request->user()->id, $filters);

        return MaterialResource::collection($paginator);
    }

    public function show(Request $request, int $material): JsonResponse
    {
        $material = $this->materials->findOwnedByUser($request->user()->id, (int) $material);

        abort_if($material === null, 404);

        return new JsonResponse(new MaterialResource($material, withDetail: true));
    }

    public function download(Request $request, int $material): StreamedResponse
    {
        $material = $this->materials->findOwnedByUser($request->user()->id, (int) $material);

        abort_if($material === null, 404);

        return Storage::disk('local')->download($material->file_path, $material->original_filename);
    }

    public function destroy(Request $request, int $material): Response
    {
        $material = $this->materials->findOwnedByUser($request->user()->id, (int) $material);

        abort_if($material === null, 404);

        $filePath = $material->file_path;

        $this->materials->delete($material->id);

        if ($filePath !== null && $filePath !== '' && ! Storage::disk('local')->delete($filePath)) {
            Log::warning('[MATERIAL DELETE] Stored file could not be removed', [
                'material_id' => $material->id,
                'file_path' => $filePath,
            ]);
        }

        return response()->noContent();
    }
}
