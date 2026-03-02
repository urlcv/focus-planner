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
        return 'The only free daily planner that pairs Things 3-style project organisation with a built-in Pomodoro timer, focus mode, task notes, and drag-to-queue sessions — saved to your email, no account needed.';
    }

    public function descriptionMd(): ?string
    {
        return <<<'MD'
## Focus Planner

A distraction-free daily planner with a built-in Pomodoro timer, drag-and-drop task management, and project organisation — all saved to your email with no account or login required.

---

### Tasks

- **Add tasks** — click **+ Add task** at the bottom of the list. Tasks auto-save to the server when you finish typing.
- **Add section headers** — click **+ Add header** to group tasks under a label (e.g. "Morning", "Deep Work").
- **Complete a task** — click the circle checkbox on any task row. Completed tasks are struck through.
- **Drag to reorder** — grab the ⠿ handle on the left of any row and drag it to a new position. Order is saved automatically.
- **Task details** — hover a task row and click the **›** arrow on the right to open the detail panel. From there you can:
  - Edit the task title
  - Add **notes** — multi-line text for links, context, or sub-steps
  - Reassign to a project
  - Set a scheduled date
  - Adjust the Pomodoro estimate
  - Delete the task

---

### Projects

Click the **All Projects ▾** dropdown in the nav bar to manage projects:

- **Filter tasks** — select a project to show only its tasks, or **Inbox** for unassigned tasks
- **Create a project** — click **New project** at the bottom of the dropdown, choose a colour, type a name and press Enter
- **Assign a task** — open the **›** task detail panel and pick a project, or hover any task and click the coloured dot to assign inline
- **Delete a project** — hover a project name in the dropdown and click **×**. Tasks move to Inbox automatically.
- **Search** — type in the search box at the top of the dropdown to filter across hundreds of projects instantly

---

### Scheduling ahead

- **Schedule a task for a future date** — hover a task and click the 📅 calendar icon, then pick a date. The task disappears from Today and appears in **Upcoming**.
- **Upcoming view** — click the **Upcoming** pill in the nav to see all future-dated tasks grouped by date.
- **Move back to today** — in Upcoming view, hover a task and click the ↩ return arrow to unschedule it.

---

### Pomodoro timer

Click **🍅 Pomodoro** in the top bar to open the timer panel.

**Building your queue:**
- Hover any task and click the 🕐 clock icon to add it to the queue, or simply **drag a task from the list and drop it onto the queue panel**.
- Reorder queue items by dragging them up or down within the queue.
- Remove a task from the queue with the × button.

**Running a session:**
1. Add tasks to the queue
2. Click **Start** — the timer counts down your focus block (default 25 min)
3. When the session ends a chime plays and the timer switches to a short break (5 min) or long break (15 min) after every 4 sessions
4. The session dots below the timer track your progress toward the next long break
5. Click **Skip** to advance to the next phase early, or **Reset** to start fresh

**Marking tasks complete:**
- Click the small circle next to any queue item to mark it done without leaving the timer view.

**Focus mode:**
- Click the ⛶ expand icon in the timer controls to enter **Focus mode** — a full-screen dark overlay showing only the large timer, the current task, and your queue. Press **Esc** or click ⛶ again to exit.

---

### Settings

Click ⚙ in the top bar to adjust:

| Setting | Default |
|---|---|
| Work start / end | 09:00 – 17:00 |
| Focus duration | 25 min |
| Short break | 5 min |
| Long break | 15 min |
| Sessions until long break | 4 |

Changes take effect immediately (the timer resets to the new duration if not currently running).

---

### Saving & recovery

Your planner is tied to your email address — no password needed.

- **New device or browser** — go to the tool, enter your email, and click **Get my planner →**. If a planner already exists for that email, a recovery link is sent to your inbox.
- **Sign out** — open ⚙ Settings and click **Sign out and clear local data**.
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
