<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use URLCV\FocusPlanner\Mail\FocusPlannerRecovery;
use URLCV\FocusPlanner\Models\FpProject;
use URLCV\FocusPlanner\Models\FpSession;
use URLCV\FocusPlanner\Models\FpTask;

class FocusPlannerController extends Controller
{
    /** POST /fp-api/session — create or fetch session by email */
    public function startSession(Request $request): JsonResponse
    {
        $data  = $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower($data['email']);

        $session = FpSession::where('email', $email)->first();

        if ($session) {
            Mail::to($email)->send(new FocusPlannerRecovery($session->token));

            return response()->json([
                'status'  => 'existing',
                'message' => 'You already have a planner! We sent a link to ' . $email . ' to sign you back in.',
            ]);
        }

        $session = FpSession::create([
            'email'    => $email,
            'token'    => Str::random(48),
            'settings' => $this->defaultSettings(),
        ]);

        return response()->json([
            'status'   => 'created',
            'token'    => $session->token,
            'settings' => $session->settings,
            'tasks'    => [],
            'projects' => [],
        ]);
    }

    /** POST /fp-api/recover — send a recovery email */
    public function sendRecovery(Request $request): JsonResponse
    {
        $data    = $request->validate(['email' => 'required|email|max:255']);
        $email   = strtolower($data['email']);
        $session = FpSession::where('email', $email)->first();

        if ($session) {
            Mail::to($email)->send(new FocusPlannerRecovery($session->token));
        }

        return response()->json(['status' => 'sent']);
    }

    /** GET /fp-api/session/{token} — load session data */
    public function getSession(string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        return response()->json([
            'token'    => $session->token,
            'settings' => $session->settings ?? $this->defaultSettings(),
            'tasks'    => $session->tasks->map(fn ($t) => $this->taskToArray($t)),
            'projects' => $session->projects->map(fn ($p) => $this->projectToArray($p)),
        ]);
    }

    /** PUT /fp-api/session/{token}/settings */
    public function updateSettings(Request $request, string $token): JsonResponse
    {
        $session  = FpSession::where('token', $token)->firstOrFail();
        $settings = $request->validate([
            'workStart'              => 'required|string|max:5',
            'workEnd'                => 'required|string|max:5',
            'pomodoroDuration'       => 'required|integer|min:1|max:120',
            'shortBreak'             => 'required|integer|min:1|max:60',
            'longBreak'              => 'required|integer|min:1|max:120',
            'sessionsUntilLongBreak' => 'required|integer|min:1|max:10',
        ]);

        $session->update(['settings' => $settings]);

        return response()->json(['status' => 'ok']);
    }

    // ── Projects ────────────────────────────────────────────────────────────

    /** POST /fp-api/session/{token}/projects */
    public function createProject(Request $request, string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'name'  => 'required|string|max:200',
            'color' => 'required|string|max:7',
        ]);

        $maxOrder = $session->projects()->max('sort_order') ?? -1;

        $project = FpProject::create([
            'session_id' => $session->id,
            'name'       => $data['name'],
            'color'      => $data['color'],
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json($this->projectToArray($project), 201);
    }

    /** PUT /fp-api/session/{token}/projects/{id} */
    public function updateProject(Request $request, string $token, int $id): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        $project = FpProject::where('id', $id)->where('session_id', $session->id)->firstOrFail();

        $data = $request->validate([
            'name'  => 'sometimes|string|max:200',
            'color' => 'sometimes|string|max:7',
        ]);

        $project->update($data);

        return response()->json($this->projectToArray($project));
    }

    /** DELETE /fp-api/session/{token}/projects/{id} */
    public function deleteProject(string $token, int $id): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        // Tasks are automatically set to null via nullOnDelete FK
        FpProject::where('id', $id)->where('session_id', $session->id)->delete();

        return response()->json(['status' => 'deleted']);
    }

    // ── Tasks ───────────────────────────────────────────────────────────────

    /** POST /fp-api/session/{token}/tasks */
    public function createTask(Request $request, string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'type'                => 'required|in:task,header',
            'title'               => 'nullable|string|max:500',
            'notes'               => 'nullable|string|max:5000',
            'estimated_pomodoros' => 'integer|min:1|max:20',
            'scheduled_for'       => 'nullable|date',
            'project_id'          => 'nullable|integer',
        ]);

        $maxOrder = $session->tasks()->max('sort_order') ?? -1;

        $task = FpTask::create([
            'session_id'          => $session->id,
            'project_id'          => $data['project_id'] ?? null,
            'type'                => $data['type'],
            'title'               => $data['title'] ?? '',
            'notes'               => $data['notes'] ?? null,
            'estimated_pomodoros' => $data['estimated_pomodoros'] ?? 1,
            'sort_order'          => $maxOrder + 1,
            'scheduled_for'       => $data['scheduled_for'] ?? null,
        ]);

        return response()->json($this->taskToArray($task), 201);
    }

    /** PUT /fp-api/session/{token}/tasks/{id} */
    public function updateTask(Request $request, string $token, int $id): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        $task    = FpTask::where('id', $id)->where('session_id', $session->id)->firstOrFail();

        $data = $request->validate([
            'title'               => 'sometimes|string|max:500',
            'notes'               => 'sometimes|nullable|string|max:5000',
            'completed'           => 'sometimes|boolean',
            'estimated_pomodoros' => 'sometimes|integer|min:1|max:20',
            'completed_pomodoros' => 'sometimes|integer|min:0|max:20',
            'scheduled_for'       => 'sometimes|nullable|date',
            'project_id'          => 'sometimes|nullable|integer',
        ]);

        $task->update($data);

        return response()->json($this->taskToArray($task));
    }

    /** DELETE /fp-api/session/{token}/tasks/{id} */
    public function deleteTask(string $token, int $id): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        FpTask::where('id', $id)->where('session_id', $session->id)->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** POST /fp-api/session/{token}/tasks/reorder */
    public function reorderTasks(Request $request, string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        $request->validate(['ids' => 'required|array']);

        foreach ($request->ids as $position => $taskId) {
            FpTask::where('id', $taskId)
                ->where('session_id', $session->id)
                ->update(['sort_order' => $position]);
        }

        return response()->json(['status' => 'ok']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function taskToArray(FpTask $task): array
    {
        return [
            'id'                  => $task->id,
            'project_id'          => $task->project_id,
            'type'                => $task->type,
            'title'               => $task->title,
            'notes'               => $task->notes,
            'completed'           => $task->completed,
            'sort_order'          => $task->sort_order,
            'estimated_pomodoros' => $task->estimated_pomodoros,
            'completed_pomodoros' => $task->completed_pomodoros,
            'scheduled_for'       => $task->scheduled_for?->format('Y-m-d'),
        ];
    }

    private function projectToArray(FpProject $project): array
    {
        return [
            'id'         => $project->id,
            'name'       => $project->name,
            'color'      => $project->color,
            'sort_order' => $project->sort_order,
        ];
    }

    private function defaultSettings(): array
    {
        return [
            'workStart'              => '09:00',
            'workEnd'                => '17:00',
            'pomodoroDuration'       => 25,
            'shortBreak'             => 5,
            'longBreak'              => 15,
            'sessionsUntilLongBreak' => 4,
        ];
    }
}
