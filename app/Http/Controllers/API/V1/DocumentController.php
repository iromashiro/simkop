<?php
// app/Http/Controllers/API/V1/DocumentController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Document\Services\DocumentService;
use App\Domain\Document\Models\Document;
use App\Domain\Document\DTOs\UploadDocumentDTO;
use App\Http\Requests\API\V1\Document\UploadDocumentRequest;
use App\Http\Resources\API\V1\DocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @group Documents
 *
 * APIs for document management
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
    }

    /**
     * Get documents
     *
     * @authenticated
     * @queryParam type string Filter by document type. Example: contract
     * @queryParam entity_type string Filter by entity type. Example: member
     * @queryParam entity_id integer Filter by entity ID. Example: 123
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'string|max:50',
            'entity_type' => 'string|max:50',
            'entity_id' => 'integer',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $query = Document::where('cooperative_id', $user->cooperative_id)
            ->with(['uploadedBy'])
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }

        $documents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($documents->items()),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    /**
     * Upload document
     *
     * @authenticated
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = new UploadDocumentDTO(
                cooperativeId: $user->cooperative_id,
                file: $request->file('file'),
                type: $request->type,
                title: $request->title,
                description: $request->description,
                entityType: $request->entity_type,
                entityId: $request->entity_id,
                isPublic: $request->boolean('is_public', false),
                uploadedBy: $user->id
            );

            $document = $this->documentService->uploadDocument($dto);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => new DocumentResource($document),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get document details
     *
     * @authenticated
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $document = Document::where('cooperative_id', $user->cooperative_id)
            ->with(['uploadedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * Update document
     *
     * @authenticated
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'type' => 'string|max:50',
            'is_public' => 'boolean',
        ]);

        try {
            $user = Auth::user();

            $document = Document::where('cooperative_id', $user->cooperative_id)
                ->findOrFail($id);

            $document->update($request->only(['title', 'description', 'type', 'is_public']));

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully',
                'data' => new DocumentResource($document),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete document
     *
     * @authenticated
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $document = Document::where('cooperative_id', $user->cooperative_id)
                ->findOrFail($id);

            $success = $this->documentService->deleteDocument($document);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download document
     *
     * @authenticated
     */
    public function download(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        try {
            $user = Auth::user();

            $document = Document::where('cooperative_id', $user->cooperative_id)
                ->findOrFail($id);

            if (!Storage::disk($document->disk)->exists($document->path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found',
                ], 404);
            }

            // Log download activity
            $this->documentService->logDocumentAccess($document, $user->id, 'download');

            return Storage::disk($document->disk)->download($document->path, $document->original_name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get document types
     *
     * @authenticated
     */
    public function types(): JsonResponse
    {
        $types = $this->documentService->getDocumentTypes();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get document statistics
     *
     * @authenticated
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->documentService->getDocumentStatistics($user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Search documents
     *
     * @authenticated
     * @queryParam q string Search query
     * @queryParam type string Filter by type
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'string|max:50',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $documents = $this->documentService->searchDocuments(
            $user->cooperative_id,
            $request->q,
            $request->type,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($documents->items()),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'search_query' => $request->q,
            ],
        ]);
    }

    /**
     * Get document access log
     *
     * @authenticated
     */
    public function accessLog(int $id): JsonResponse
    {
        $user = Auth::user();

        $document = Document::where('cooperative_id', $user->cooperative_id)
            ->findOrFail($id);

        $accessLog = $this->documentService->getDocumentAccessLog($document);

        return response()->json([
            'success' => true,
            'data' => $accessLog,
        ]);
    }
}
