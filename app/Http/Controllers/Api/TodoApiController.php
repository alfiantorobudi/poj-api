<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTodoRequest;
use App\Http\Requests\Api\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TodoApiController extends Controller
{
    /**
     * Display a listing of the authenticated user's todos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Todo::query()->where('user_id', $user->id);

        if ($request->has('status')) {
            $status = strtolower((string) $request->query('status'));
            if ($status === 'completed') {
                $query->completed();
            } elseif ($status === 'pending' || $status === 'active') {
                $query->pending();
            }
        }

        if ($request->has('priority')) {
            $query->priority((string) $request->query('priority'));
        }

        if ($request->has('search')) {
            $query->search((string) $request->query('search'));
        }

        $sortBy = (string) $request->query('sort_by', 'newest');
        match ($sortBy) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'due_date' => $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min((int) $request->query('per_page', 15), 100);
        $todos = $query->paginate($perPage);

        return TodoResource::collection($todos);
    }

    /**
     * Store a newly created todo in storage.
     */
    public function store(StoreTodoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $isCompleted = (bool) ($validated['is_completed'] ?? false);

        $todo = Todo::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_completed' => $isCompleted,
            'priority' => $validated['priority'] ?? 'medium',
            'due_date' => $validated['due_date'] ?? null,
            'completed_at' => $isCompleted ? now() : null,
        ]);

        return (new TodoResource($todo))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified todo.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        return (new TodoResource($todo))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified todo in storage.
     */
    public function update(UpdateTodoRequest $request, int $id): JsonResponse
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $request->validated();

        if (array_key_exists('is_completed', $validated)) {
            $isCompleted = (bool) $validated['is_completed'];
            $validated['completed_at'] = $isCompleted ? ($todo->completed_at ?? now()) : null;
        }

        $todo->update($validated);

        return (new TodoResource($todo->fresh()))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified todo from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        $todo->delete();

        return response()->json([
            'message' => 'Todo deleted successfully.',
        ], 200);
    }
}
