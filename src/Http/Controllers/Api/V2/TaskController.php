<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\TaskResource;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\TaskService;

class TaskController extends ApiController
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->with(['ownerUser', 'assignedToUser']);

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'due_at', 'name'],
            '-created_at'
        );

        $tasks = $query->paginate($this->perPage($request))->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        $task->load(['ownerUser', 'assignedToUser']);

        return new TaskResource($task);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'start_at' => 'nullable|date',
            'user_owner_id' => 'nullable|integer',
            'user_assigned_id' => 'nullable|integer',
        ]);

        $payload = (object) array_merge([
            'name' => null,
            'description' => null,
            'due_at' => null,
            'start_at' => null,
            'user_owner_id' => $request->user()?->id,
            'user_assigned_id' => null,
        ], $validated);

        $task = $this->taskService->create($payload);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'start_at' => 'nullable|date',
            'user_owner_id' => 'nullable|integer',
            'user_assigned_id' => 'nullable|integer',
        ]);

        $payload = (object) array_merge([
            'name' => $task->name,
            'description' => $task->description,
            'due_at' => $task->due_at,
            'start_at' => $task->start_at,
            'user_owner_id' => $task->user_owner_id,
            'user_assigned_id' => $task->user_assigned_id,
        ], $validated);

        $task = $this->taskService->update($payload, $task);

        return new TaskResource($task);
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
