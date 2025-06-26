<?php
// app/Http/Controllers/API/V1/WorkflowController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Workflow\Services\WorkflowService;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowInstance;
use App\Domain\Workflow\DTOs\CreateWorkflowDTO;
use App\Domain\Workflow\DTOs\StartWorkflowDTO;
use App\Http\Requests\API\V1\Workflow\CreateWorkflowRequest;
use App\Http\Requests\API\V1\Workflow\StartWorkflowRequest;
use App\Http\Requests\API\V1\Workflow\ProcessTaskRequest;
use App\Http\Resources\API\V1\WorkflowResource;
use App\Http\Resources\API\V1\WorkflowInstanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Workflow
 *
 * APIs for workflow management and processing
 */
class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
    }

    /**
     * Get workflows
     *
     * @authenticated
     * @queryParam type string Filter by workflow type. Example: loan_approval
     * @queryParam status string Filter by status. Example: active
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'string|max:50',
            'status' => 'string|in:active,inactive,draft',
        ]);

        $user = Auth::user();

        $query = Workflow::where('cooperative_id', $user->cooperative_id)
            ->orderBy('name');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $workflows = $query->get();

        return response()->json([
            'success' => true,
            'data' => WorkflowResource::collection($workflows),
        ]);
    }

    /**
     * Create workflow
     *
     * @authenticated
     */
    public function store(CreateWorkflowRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateWorkflowDTO(
                cooperativeId: $user->cooperative_id,
                name: $request->name,
                description: $request->description,
                type: $request->type,
                definition: $request->definition,
                isActive: $request->is_active ?? true,
                createdBy: $user->id
            );

            $workflow = $this->workflowService->createWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Workflow created successfully',
                'data' => new WorkflowResource($workflow),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow details
     *
     * @authenticated
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $workflow = Workflow::where('cooperative_id', $user->cooperative_id)
            ->with(['instances' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WorkflowResource($workflow),
        ]);
    }

    /**
     * Update workflow
     *
     * @authenticated
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'definition' => 'array',
            'is_active' => 'boolean',
        ]);

        try {
            $user = Auth::user();

            $workflow = Workflow::where('cooperative_id', $user->cooperative_id)
                ->findOrFail($id);

            $workflow->update($request->only(['name', 'description', 'definition', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
                'data' => new WorkflowResource($workflow),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete workflow
     *
     * @authenticated
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $workflow = Workflow::where('cooperative_id', $user->cooperative_id)
                ->findOrFail($id);

            // Check if workflow has active instances
            $activeInstances = $workflow->instances()->active()->count();
            if ($activeInstances > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete workflow with active instances',
                ], 422);
            }

            $workflow->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workflow deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start workflow instance
     *
     * @authenticated
     */
    public function startInstance(StartWorkflowRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = new StartWorkflowDTO(
                workflowId: $request->workflow_id,
                entityType: $request->entity_type,
                entityId: $request->entity_id,
                data: $request->data ?? [],
                startedBy: $user->id
            );

            $instance = $this->workflowService->startWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Workflow started successfully',
                'data' => new WorkflowInstanceResource($instance),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow instances
     *
     * @authenticated
     * @queryParam workflow_id integer Filter by workflow ID
     * @queryParam status string Filter by status
     * @queryParam entity_type string Filter by entity type
     */
    public function instances(Request $request): JsonResponse
    {
        $request->validate([
            'workflow_id' => 'integer|exists:workflows,id',
            'status' => 'string|in:pending,running,completed,failed,cancelled',
            'entity_type' => 'string|max:50',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $query = WorkflowInstance::whereHas('workflow', function ($q) use ($user) {
            $q->where('cooperative_id', $user->cooperative_id);
        })->with(['workflow', 'currentTask'])
            ->orderBy('created_at', 'desc');

        if ($request->has('workflow_id')) {
            $query->where('workflow_id', $request->workflow_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        $instances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WorkflowInstanceResource::collection($instances->items()),
            'meta' => [
                'current_page' => $instances->currentPage(),
                'last_page' => $instances->lastPage(),
                'per_page' => $instances->perPage(),
                'total' => $instances->total(),
            ],
        ]);
    }

    /**
     * Get workflow instance details
     *
     * @authenticated
     */
    public function getInstance(int $id): JsonResponse
    {
        $user = Auth::user();

        $instance = WorkflowInstance::whereHas('workflow', function ($q) use ($user) {
            $q->where('cooperative_id', $user->cooperative_id);
        })->with(['workflow', 'tasks', 'currentTask'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WorkflowInstanceResource($instance),
        ]);
    }

    /**
     * Process workflow task
     *
     * @authenticated
     */
    public function processTask(int $instanceId, ProcessTaskRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->workflowService->processTask(
                $instanceId,
                $request->task_id,
                $request->action,
                $request->data ?? [],
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Task processed successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user tasks
     *
     * @authenticated
     * @queryParam status string Filter by task status
     * @queryParam priority string Filter by priority
     */
    public function userTasks(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'string|in:pending,in_progress,completed,cancelled',
            'priority' => 'string|in:low,normal,high,urgent',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $query = \App\Domain\Workflow\Models\WorkflowTask::where('assigned_to', $user->id)
            ->whereHas('instance.workflow', function ($q) use ($user) {
                $q->where('cooperative_id', $user->cooperative_id);
            })
            ->with(['instance.workflow'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Get workflow statistics
     *
     * @authenticated
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->workflowService->getWorkflowStatistics($user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
