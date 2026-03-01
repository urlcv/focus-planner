<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use URLCV\FocusPlanner\Mail\FocusPlannerRecovery;
use URLCV\FocusPlanner\Models\FpSession;
use URLCV\FocusPlanner\Models\FpTask;

class FocusPlannerController extends Controller
{
    /** POST /fp-api/session — create or fetch session by email */
    public function startSession(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        $email = strtolower(trim($request->string('email')));

        $session = FpSession::where('email', $email)->first();

        if ($session) {
            // Existing session — send a recovery link instead of returning the token
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
        ]);
    }

    /** POST /fp-api/recover — send a recovery email */
    public function sendRecovery(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        $email = strtolower(trim($request->string('email')));
        $session = FpSession::where('email', $email)->first();

        if ($session) {
            Mail::to($email)->send(new FocusPlannerRecovery($session->token));
        }

        // Always return success to avoid email enumeration
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
        ]);
    }

    /** PUT /fp-api/session/{token}/settings */
    public function updateSettings(Request $request, string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();

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

    /** POST /fp-api/session/{token}/tasks */
    public function createTask(Request $request, string $token): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'type'                => 'required|in:task,header',
            'title'               => 'required|string|max:500',
            'estimated_pomodoros' => 'integer|min:1|max:20',
        ]);

        $maxOrder = $session->tasks()->max('sort_order') ?? -1;

        $task = FpTask::create([
            'session_id'          => $session->id,
            'type'                => $data['type'],
            'title'               => $data['title'],
            'estimated_pomodoros' => $data['estimated_pomodoros'] ?? 1,
            'sort_order'          => $maxOrder + 1,
        ]);

        return response()->json($this->taskToArray($task), 201);
    }

    /** PUT /fp-api/session/{token}/tasks/{id} */
    public function updateTask(Request $request, string $token, int $id): JsonResponse
    {
        $session = FpSession::where('token', $token)->firstOrFail();
        $task = FpTask::where('id', $id)->where('session_id', $session->id)->firstOrFail();

        $data = $request->validate([
            'title'                => 'sometimes|string|max:500',
            'completed'            => 'sometimes|boolean',
            'estimated_pomodoros'  => 'sometimes|integer|min:1|max:20',
            'completed_pomodoros'  => 'sometimes|integer|min:0|max:20',
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

    private function taskToArray(FpTask $task): array
    {
        return [
            'id'                  => $task->id,
            'type'                => $task->type,
            'title'               => $task->title,
            'completed'           => $task->completed,
            'sort_order'          => $task->sort_order,
            'estimated_pomodoros' => $task->estimated_pomodoros,
            'completed_pomodoros' => $task->completed_pomodoros,
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
