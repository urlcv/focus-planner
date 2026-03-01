<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Laravel;

use App\Tools\Contracts\ToolInterface;

class FocusPlannerTool implements ToolInterface
{
    public function slug(): string
    {
        return 'focus-planner';
    }

    public function name(): string
    {
        return 'Focus Planner';
    }

    public function summary(): string
    {
        return 'Plan your day with drag-and-drop tasks, custom headers, and a built-in Pomodoro timer — no login needed.';
    }

    public function descriptionMd(): ?string
    {
        return <<<'MD'
## Focus Planner

A clean daily planner inspired by the best parts of task managers like Things 3 — with two things they're missing: **Pomodoro timers** and **flexible today headers**.

### What you can do

- **Organise your day** — add tasks and section headers, then drag them into the order that fits your day
- **Set your work hours** — configure your working day (e.g. 9 AM–5 PM) to see how much time remains
- **Run Pomodoro sessions** — select tasks, set your timer durations, and work through them with short and long breaks
- **No login** — just enter your email to save your planner. Revisit from any device by requesting a recovery link.

### How the Pomodoro timer works

1. Add tasks to your today list
2. Click **Start Pomodoro** and select which tasks to include
3. The timer runs 25-minute work blocks (adjustable) with 5-minute short breaks and a 15-minute long break every 4 sessions
4. Check off tasks as you complete them

### Saving your planner

Your tasks are synced to the server when you provide an email address. Enter your email at any time — if you already have a planner, we'll send a recovery link to your inbox.
MD;
    }

    public function categories(): array
    {
        return ['productivity'];
    }

    public function tags(): array
    {
        return ['pomodoro', 'focus', 'tasks', 'planner', 'today', 'time-management', 'drag-and-drop'];
    }

    public function inputSchema(): array
    {
        return [];
    }

    public function run(array $input): array
    {
        return [];
    }

    public function mode(): string
    {
        return 'frontend';
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function frontendView(): ?string
    {
        return 'focus-planner::focus-planner';
    }

    public function rateLimitPerMinute(): int
    {
        return 60;
    }

    public function cacheTtlSeconds(): int
    {
        return 0;
    }

    public function sortWeight(): int
    {
        return 12;
    }
}
