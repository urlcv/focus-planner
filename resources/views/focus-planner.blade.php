{{--
  Focus Planner — drag-and-drop daily planner with Pomodoro timer.
  Server-synced via /fp-api/* routes. No auth required — email-based sessions.
--}}

@push('head')
<style>
    [x-cloak] { display: none !important; }

    .fp-drag-over { border-top: 2px solid #2563eb !important; }
    .fp-dragging  { opacity: 0.4; }

    .fp-task-row:hover .fp-drag-handle { opacity: 1; }
    .fp-drag-handle { opacity: 0; transition: opacity 0.15s; cursor: grab; }
    .fp-drag-handle:active { cursor: grabbing; }

    /* Pomodoro ring */
    .fp-ring-bg   { stroke: #e5e7eb; }
    .fp-ring-fill { stroke: #2563eb; stroke-linecap: round; transition: stroke-dashoffset 1s linear; transform: rotate(-90deg); transform-origin: 50% 50%; }

    /* Smooth checkbox */
    .fp-check { width:18px; height:18px; border-radius:50%; border:2px solid #d1d5db; appearance:none; cursor:pointer; transition:all 0.15s; flex-shrink:0; }
    .fp-check:checked { background:#2563eb; border-color:#2563eb; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 12 10' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 5l4 4L11 1' stroke='white' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-size:70%; background-position:center; background-repeat:no-repeat; }

    .fp-phase-work       { stroke: #2563eb; }
    .fp-phase-short      { stroke: #10b981; }
    .fp-phase-long       { stroke: #8b5cf6; }
</style>
@endpush

<div x-data="focusPlanner()" x-init="init()" x-cloak class="-m-6">

    {{-- ══════════════════════════════════════════════════
         LOADING
    ══════════════════════════════════════════════════ --}}
    <div x-show="screen === 'loading'" class="flex items-center justify-center h-48">
        <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
    </div>

    {{-- ══════════════════════════════════════════════════
         EMAIL GATE
    ══════════════════════════════════════════════════ --}}
    <div x-show="screen === 'start'" class="max-w-md mx-auto px-6 py-10 space-y-8">

        <div class="text-center space-y-2">
            <div class="text-4xl">🍅</div>
            <h2 class="text-2xl font-bold text-gray-900">Focus Planner</h2>
            <p class="text-gray-500 text-sm">Plan your day. Run Pomodoro sessions. Stay in flow.</p>
        </div>

        {{-- Start / recover toggle --}}
        <div x-show="!showRecover" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5 uppercase tracking-wide">Your email address</label>
                <input
                    x-model="email"
                    type="email"
                    placeholder="you@example.com"
                    @keydown.enter="startSession()"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                >
            </div>

            <div x-show="startError" x-text="startError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
            <div x-show="startMessage" x-text="startMessage" class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2"></div>

            <button
                @click="startSession()"
                :disabled="starting || !email.trim()"
                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors text-sm"
            >
                <span x-show="!starting">Get my planner →</span>
                <span x-show="starting">Setting up…</span>
            </button>

            <p class="text-center text-xs text-gray-400">
                Already have one?
                <button @click="showRecover = true" class="text-blue-600 hover:underline font-medium">Recover your planner</button>
            </p>
        </div>

        {{-- Recovery form --}}
        <div x-show="showRecover" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5 uppercase tracking-wide">Enter your email to recover</label>
                <input
                    x-model="recoverEmail"
                    type="email"
                    placeholder="you@example.com"
                    @keydown.enter="sendRecovery()"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                >
            </div>

            <div x-show="recoverSent" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                Check your inbox — we sent a link to sign you back in.
            </div>

            <button
                @click="sendRecovery()"
                :disabled="recovering || !recoverEmail.trim()"
                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors text-sm"
            >
                <span x-show="!recovering">Send recovery link</span>
                <span x-show="recovering">Sending…</span>
            </button>

            <p class="text-center text-xs text-gray-400">
                <button @click="showRecover = false" class="text-blue-600 hover:underline">← Back</button>
            </p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-700">
            <strong>How it works:</strong> Enter your email to create a personal planner that syncs to any device.
            Your email is only used to send recovery links — we never send marketing email.
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         MAIN PLANNER
    ══════════════════════════════════════════════════ --}}
    <div x-show="screen === 'planner'" class="flex flex-col min-h-[480px]">

        {{-- ── Top bar ── --}}
        <div class="flex items-center justify-between gap-4 px-6 pt-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="text-base font-bold text-gray-900" x-text="todayLabel"></div>
                <div class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="settings.workStart + ' – ' + settings.workEnd"></span>
                    <span x-show="hoursRemaining > 0" class="text-gray-300">·</span>
                    <span x-show="hoursRemaining > 0" x-text="hoursRemaining + 'h left'" class="text-blue-500"></span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="pomodoroOpen = !pomodoroOpen"
                    class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors"
                    :class="pomodoroOpen ? 'bg-red-50 border-red-200 text-red-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                    <span>🍅</span>
                    <span x-text="pomodoroOpen ? 'Close timer' : 'Pomodoro'"></span>
                </button>
                <button @click="showSettings = true"
                    class="p-1.5 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"
                    title="Settings">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        {{-- ── Pomodoro panel ── --}}
        <div x-show="pomodoroOpen" x-transition class="mx-6 mt-4 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="flex flex-col sm:flex-row items-stretch">

                {{-- Timer column --}}
                <div class="flex flex-col items-center justify-center gap-4 p-6 sm:border-r border-gray-100 sm:w-52 shrink-0">
                    {{-- Ring --}}
                    <div class="relative w-28 h-28">
                        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                            <circle class="fp-ring-bg" cx="50" cy="50" r="42" fill="none" stroke-width="8"/>
                            <circle
                                cx="50" cy="50" r="42" fill="none" stroke-width="8"
                                :class="{
                                    'fp-phase-work':  pomoPhase === 'work',
                                    'fp-phase-short': pomoPhase === 'short_break',
                                    'fp-phase-long':  pomoPhase === 'long_break'
                                }"
                                :stroke-dasharray="2 * Math.PI * 42"
                                :stroke-dashoffset="ringOffset"
                                stroke-linecap="round"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold tabular-nums text-gray-900" x-text="formatTime(pomoTimeLeft)"></span>
                            <span class="text-[10px] font-medium uppercase tracking-wide mt-0.5"
                                :class="{
                                    'text-blue-600':   pomoPhase === 'work',
                                    'text-emerald-600': pomoPhase === 'short_break',
                                    'text-violet-600':  pomoPhase === 'long_break'
                                }"
                                x-text="pomoPhase === 'work' ? 'Focus' : pomoPhase === 'short_break' ? 'Short break' : 'Long break'"
                            ></span>
                        </div>
                    </div>

                    {{-- Controls --}}
                    <div class="flex items-center gap-2">
                        <button @click="resetPomo()" title="Reset"
                            class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                        <button @click="togglePomo()"
                            class="px-5 py-2 rounded-lg font-semibold text-sm transition-colors"
                            :class="pomoRunning ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-blue-600 text-white hover:bg-blue-700'">
                            <span x-text="pomoRunning ? 'Pause' : 'Start'"></span>
                        </button>
                        <button @click="skipPhase()" title="Skip"
                            class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    {{-- Session dots --}}
                    <div class="flex items-center gap-1.5">
                        <template x-for="i in settings.sessionsUntilLongBreak" :key="i">
                            <div class="w-2 h-2 rounded-full transition-colors"
                                :class="i <= pomoSessionsDone % settings.sessionsUntilLongBreak || (pomoSessionsDone > 0 && pomoSessionsDone % settings.sessionsUntilLongBreak === 0 && i === settings.sessionsUntilLongBreak) ? 'bg-blue-500' : 'bg-gray-200'">
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Queue column --}}
                <div class="flex-1 p-4 space-y-2 max-h-52 overflow-y-auto">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Queue</p>
                        <span class="text-xs text-gray-400" x-text="pomodoroQueue.length + ' task' + (pomodoroQueue.length !== 1 ? 's' : '')"></span>
                    </div>

                    <div x-show="pomodoroQueue.length === 0" class="text-xs text-gray-400 italic py-2">
                        Add tasks from your list using the 🍅 button
                    </div>

                    <template x-for="(taskId, qIdx) in pomodoroQueue" :key="taskId">
                        <div class="flex items-center gap-2 p-2 rounded-lg transition-colors"
                            :class="qIdx === currentPomoIndex && pomoPhase === 'work' ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50'">
                            <div class="w-1 h-1 rounded-full flex-shrink-0"
                                :class="qIdx === currentPomoIndex && pomoPhase === 'work' ? 'bg-blue-500' : 'bg-gray-300'">
                            </div>
                            <span class="text-sm flex-1 truncate text-gray-700"
                                x-text="getTaskById(taskId)?.title ?? '(deleted)'"
                                :class="getTaskById(taskId)?.completed ? 'line-through text-gray-400' : ''">
                            </span>
                            <button @click="removeFromQueue(qIdx)"
                                class="text-gray-300 hover:text-red-400 transition-colors shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ── Task list ── --}}
        <div class="flex-1 px-6 py-4 space-y-0.5" id="fp-task-list">

            <div x-show="items.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="text-4xl mb-3">📝</div>
                <p class="text-sm font-medium text-gray-700">Your today list is empty</p>
                <p class="text-xs text-gray-400 mt-1">Add tasks below to get started</p>
            </div>

            <template x-for="(item, index) in items" :key="item.id">

                {{-- HEADER row --}}
                <div x-show="item.type === 'header'"
                    class="fp-task-row group flex items-center gap-2 pt-5 pb-1 first:pt-2"
                    draggable="true"
                    @dragstart="dragStart($event, index)"
                    @dragover.prevent="dragOver($event, index)"
                    @drop.prevent="drop($event, index)"
                    @dragend="dragEnd()"
                    :class="{ 'fp-dragging': dragSrc === index, 'fp-drag-over': dragTarget === index && dragSrc !== index }">

                    <span class="fp-drag-handle text-gray-300 select-none text-xs">⠿⠿</span>

                    <input
                        x-model="item.title"
                        @change="saveTask(item)"
                        @keydown.enter="$event.target.blur()"
                        class="flex-1 text-xs font-bold uppercase tracking-widest text-gray-400 bg-transparent border-none outline-none placeholder-gray-300 min-w-0"
                        placeholder="Section header…"
                    >

                    <button @click="deleteItem(item)"
                        class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400 transition-all">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- TASK row --}}
                <div x-show="item.type === 'task'"
                    class="fp-task-row group flex items-center gap-2.5 px-2 py-2 rounded-xl hover:bg-gray-50 transition-colors"
                    draggable="true"
                    @dragstart="dragStart($event, index)"
                    @dragover.prevent="dragOver($event, index)"
                    @drop.prevent="drop($event, index)"
                    @dragend="dragEnd()"
                    :class="{ 'fp-dragging': dragSrc === index, 'fp-drag-over': dragTarget === index && dragSrc !== index }">

                    <span class="fp-drag-handle text-gray-300 select-none text-xs shrink-0">⠿⠿</span>

                    <input type="checkbox"
                        class="fp-check shrink-0"
                        :checked="item.completed"
                        @change="toggleComplete(item)">

                    <input
                        x-model="item.title"
                        @change="saveTask(item)"
                        @keydown.enter="$event.target.blur()"
                        class="flex-1 text-sm bg-transparent border-none outline-none placeholder-gray-300 min-w-0 transition-colors"
                        :class="item.completed ? 'line-through text-gray-400' : 'text-gray-800'"
                        placeholder="Task name…"
                    >

                    {{-- Pomodoro count (tasks only) --}}
                    <div class="flex items-center gap-0.5 shrink-0">
                        <button @click="item.estimated_pomodoros = Math.max(1, item.estimated_pomodoros - 1); saveTask(item)"
                            class="opacity-0 group-hover:opacity-100 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-all text-xs rounded">−</button>
                        <div class="flex items-center gap-0.5" title="Estimated Pomodoros">
                            <template x-for="p in item.estimated_pomodoros" :key="p">
                                <span class="text-xs" :class="p <= item.completed_pomodoros ? 'grayscale-0' : 'opacity-40'">🍅</span>
                            </template>
                        </div>
                        <button @click="item.estimated_pomodoros = Math.min(20, item.estimated_pomodoros + 1); saveTask(item)"
                            class="opacity-0 group-hover:opacity-100 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-all text-xs rounded">+</button>
                    </div>

                    {{-- Add to Pomodoro queue --}}
                    <button @click="addToQueue(item)"
                        :title="pomodoroQueue.includes(item.id) ? 'In queue' : 'Add to Pomodoro queue'"
                        class="shrink-0 transition-all"
                        :class="pomodoroQueue.includes(item.id) ? 'text-blue-500' : 'text-gray-200 group-hover:text-gray-400 hover:text-blue-500'">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2zm0 14a6 6 0 110-12 6 6 0 010 12zm0-9a1 1 0 011 1v3l2.5 1.5a1 1 0 11-1 1.72L10 12.27V8a1 1 0 011-1z" clip-rule="evenodd" fill-rule="evenodd"/></svg>
                    </button>

                    <button @click="deleteItem(item)"
                        class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400 transition-all shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

            </template>
        </div>

        {{-- ── Add buttons ── --}}
        <div class="flex items-center gap-2 px-6 pb-5 pt-2 border-t border-gray-100">
            <button @click="addTask()"
                class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add task
            </button>
            <span class="text-gray-200">·</span>
            <button @click="addHeader()"
                class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/></svg>
                Add header
            </button>
            <div class="ml-auto">
                <span x-show="saving" class="text-xs text-gray-400 flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </div>
        </div>

    </div>{{-- /planner --}}

    {{-- ══════════════════════════════════════════════════
         SETTINGS MODAL
    ══════════════════════════════════════════════════ --}}
    <div x-show="showSettings"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="showSettings = false">

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Settings</h3>
                <button @click="showSettings = false" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Work hours --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Work hours</label>
                <div class="flex items-center gap-2">
                    <input type="time" x-model="settings.workStart"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <span class="text-gray-400 text-sm">to</span>
                    <input type="time" x-model="settings.workEnd"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Pomodoro durations --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Pomodoro timer (minutes)</label>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-1">
                        <label class="text-xs text-gray-500">Focus</label>
                        <input type="number" x-model.number="settings.pomodoroDuration" min="1" max="120"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs text-gray-500">Short break</label>
                        <input type="number" x-model.number="settings.shortBreak" min="1" max="60"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs text-gray-500">Long break</label>
                        <input type="number" x-model.number="settings.longBreak" min="1" max="120"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div class="mt-2 space-y-1">
                    <label class="text-xs text-gray-500">Sessions before long break</label>
                    <input type="number" x-model.number="settings.sessionsUntilLongBreak" min="1" max="10"
                        class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Account --}}
            <div x-show="token" class="pt-2 border-t border-gray-100">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Account</label>
                <p class="text-xs text-gray-500 mb-2">Your planner is saved and synced to your email address.</p>
                <button @click="signOut()" class="text-xs text-red-500 hover:underline">Sign out and clear local data</button>
            </div>

            <button @click="saveSettings()"
                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition-colors">
                Save settings
            </button>
        </div>
    </div>

</div>

@push('scripts')
<script>
function focusPlanner() {
    return {
        // ── State ──────────────────────────────────
        screen:       'loading',
        token:        null,

        // Email gate
        email:        '',
        startError:   '',
        startMessage: '',
        starting:     false,
        showRecover:  false,
        recoverEmail: '',
        recoverSent:  false,
        recovering:   false,

        // Session
        settings: {
            workStart:              '09:00',
            workEnd:                '17:00',
            pomodoroDuration:       25,
            shortBreak:             5,
            longBreak:              15,
            sessionsUntilLongBreak: 4,
        },
        items: [],

        // Drag & drop
        dragSrc:    null,
        dragTarget: null,

        // Pomodoro
        pomodoroOpen:    false,
        pomodoroQueue:   [],
        currentPomoIndex: 0,
        pomoPhase:       'work',
        pomoTimeLeft:    25 * 60,
        pomoRunning:     false,
        pomoSessionsDone: 0,
        pomoInterval:    null,

        // UI
        showSettings: false,
        saving:       false,

        // ── Computed ───────────────────────────────
        get todayLabel() {
            return new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
        },

        get hoursRemaining() {
            const [h, m] = this.settings.workEnd.split(':').map(Number);
            const end = new Date();
            end.setHours(h, m, 0, 0);
            const diff = (end - Date.now()) / 3_600_000;
            return diff > 0 ? Math.round(diff * 10) / 10 : 0;
        },

        get ringOffset() {
            const total = this.pomoPhase === 'work'
                ? this.settings.pomodoroDuration * 60
                : this.pomoPhase === 'short_break'
                    ? this.settings.shortBreak * 60
                    : this.settings.longBreak * 60;
            const circumference = 2 * Math.PI * 42;
            return circumference * (1 - this.pomoTimeLeft / total);
        },

        // ── Init ──────────────────────────────────
        async init() {
            // Check URL for token (recovery link)
            const urlParams = new URLSearchParams(window.location.search);
            const urlToken  = urlParams.get('token');
            if (urlToken) {
                localStorage.setItem('fp_token', urlToken);
                window.history.replaceState({}, '', window.location.pathname);
            }

            const storedToken = localStorage.getItem('fp_token');
            if (storedToken) {
                await this.loadSession(storedToken);
            } else {
                this.screen = 'start';
            }
        },

        // ── Session ───────────────────────────────
        async startSession() {
            if (!this.email.trim()) return;
            this.starting    = true;
            this.startError  = '';
            this.startMessage = '';

            try {
                const res = await this.api('POST', '/fp-api/session', { email: this.email.trim() });

                if (res.status === 'existing') {
                    this.startMessage = res.message;
                    this.starting     = false;
                    return;
                }

                localStorage.setItem('fp_token', res.token);
                this.token    = res.token;
                this.settings = { ...this.settings, ...(res.settings ?? {}) };
                this.items    = res.tasks ?? [];
                this.screen   = 'planner';
            } catch (e) {
                this.startError = 'Something went wrong — please try again.';
            }

            this.starting = false;
        },

        async loadSession(token) {
            try {
                const res = await this.api('GET', '/fp-api/session/' + token);
                this.token    = res.token;
                this.settings = { ...this.settings, ...(res.settings ?? {}) };
                this.items    = res.tasks ?? [];
                this.screen   = 'planner';
                this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            } catch (e) {
                // Token invalid or expired — show start screen
                localStorage.removeItem('fp_token');
                this.screen = 'start';
            }
        },

        async sendRecovery() {
            if (!this.recoverEmail.trim()) return;
            this.recovering = true;
            try {
                await this.api('POST', '/fp-api/recover', { email: this.recoverEmail.trim() });
                this.recoverSent = true;
            } catch (e) { /* silent */ }
            this.recovering = false;
        },

        signOut() {
            if (!confirm('This will clear your local session. You can always recover your planner with your email. Continue?')) return;
            localStorage.removeItem('fp_token');
            this.token  = null;
            this.items  = [];
            this.screen = 'start';
        },

        // ── Tasks ─────────────────────────────────
        async addTask() {
            const tempId   = 'new-' + Date.now();
            const newItem  = { id: tempId, type: 'task', title: '', completed: false, sort_order: this.items.length, estimated_pomodoros: 1, completed_pomodoros: 0 };
            this.items.push(newItem);

            await this.$nextTick();
            const inputs = document.querySelectorAll('#fp-task-list input[type="text"], #fp-task-list input:not([type])');
            inputs[inputs.length - 1]?.focus();

            if (this.token) {
                try {
                    const created = await this.api('POST', `/fp-api/session/${this.token}/tasks`, { type: 'task', title: '', estimated_pomodoros: 1 });
                    const idx = this.items.findIndex(i => i.id === tempId);
                    if (idx !== -1) this.items[idx] = created;
                } catch (e) { /* keep local */ }
            }
        },

        async addHeader() {
            const tempId  = 'new-' + Date.now();
            const newItem = { id: tempId, type: 'header', title: '', completed: false, sort_order: this.items.length, estimated_pomodoros: 0, completed_pomodoros: 0 };
            this.items.push(newItem);

            await this.$nextTick();
            const inputs = document.querySelectorAll('#fp-task-list input[type="text"], #fp-task-list input:not([type])');
            inputs[inputs.length - 1]?.focus();

            if (this.token) {
                try {
                    const created = await this.api('POST', `/fp-api/session/${this.token}/tasks`, { type: 'header', title: '' });
                    const idx = this.items.findIndex(i => i.id === tempId);
                    if (idx !== -1) this.items[idx] = created;
                } catch (e) { /* keep local */ }
            }
        },

        async saveTask(item) {
            if (!this.token || String(item.id).startsWith('new-')) return;
            this.saving = true;
            try {
                await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, {
                    title:               item.title,
                    estimated_pomodoros: item.estimated_pomodoros,
                });
            } catch (e) { /* silent */ }
            this.saving = false;
        },

        async toggleComplete(item) {
            item.completed = !item.completed;
            if (!this.token || String(item.id).startsWith('new-')) return;
            this.saving = true;
            try {
                await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, {
                    completed: item.completed,
                });
            } catch (e) { /* silent */ }
            this.saving = false;
        },

        async deleteItem(item) {
            this.items = this.items.filter(i => i.id !== item.id);
            this.pomodoroQueue = this.pomodoroQueue.filter(id => id !== item.id);

            if (!this.token || String(item.id).startsWith('new-')) return;
            try {
                await this.api('DELETE', `/fp-api/session/${this.token}/tasks/${item.id}`);
            } catch (e) { /* silent */ }
        },

        async saveReorder() {
            if (!this.token) return;
            const ids = this.items.filter(i => !String(i.id).startsWith('new-')).map(i => i.id);
            this.saving = true;
            try {
                await this.api('POST', `/fp-api/session/${this.token}/tasks/reorder`, { ids });
            } catch (e) { /* silent */ }
            this.saving = false;
        },

        // ── Drag & drop ───────────────────────────
        dragStart(event, index) {
            this.dragSrc = index;
            event.dataTransfer.effectAllowed = 'move';
        },

        dragOver(event, index) {
            if (this.dragSrc === index) return;
            this.dragTarget = index;
        },

        drop(event, index) {
            if (this.dragSrc === null || this.dragSrc === index) {
                this.dragSrc    = null;
                this.dragTarget = null;
                return;
            }
            const [moved] = this.items.splice(this.dragSrc, 1);
            this.items.splice(index, 0, moved);
            this.dragSrc    = null;
            this.dragTarget = null;
            this.saveReorder();
        },

        dragEnd() {
            this.dragSrc    = null;
            this.dragTarget = null;
        },

        // ── Pomodoro ──────────────────────────────
        addToQueue(item) {
            if (this.pomodoroQueue.includes(item.id)) return;
            this.pomodoroQueue.push(item.id);
            this.pomodoroOpen = true;
        },

        removeFromQueue(qIdx) {
            this.pomodoroQueue.splice(qIdx, 1);
            if (this.currentPomoIndex >= this.pomodoroQueue.length) {
                this.currentPomoIndex = Math.max(0, this.pomodoroQueue.length - 1);
            }
        },

        getTaskById(id) {
            return this.items.find(i => i.id === id) ?? null;
        },

        togglePomo() {
            if (this.pomoRunning) {
                clearInterval(this.pomoInterval);
                this.pomoInterval = null;
                this.pomoRunning  = false;
            } else {
                this.pomoRunning = true;
                this.pomoInterval = setInterval(() => this.tick(), 1000);
            }
        },

        tick() {
            if (this.pomoTimeLeft > 0) {
                this.pomoTimeLeft--;
                return;
            }
            // Phase complete
            this.advancePhase();
        },

        advancePhase() {
            clearInterval(this.pomoInterval);
            this.pomoInterval = null;
            this.pomoRunning  = false;

            if (this.pomoPhase === 'work') {
                this.pomoSessionsDone++;

                // Mark current task's completed_pomodoros
                const task = this.getTaskById(this.pomodoroQueue[this.currentPomoIndex]);
                if (task) {
                    task.completed_pomodoros = Math.min(task.estimated_pomodoros, task.completed_pomodoros + 1);
                    if (this.token && !String(task.id).startsWith('new-')) {
                        this.api('PUT', `/fp-api/session/${this.token}/tasks/${task.id}`, {
                            completed_pomodoros: task.completed_pomodoros,
                        }).catch(() => {});
                    }
                }

                const isLongBreak = this.pomoSessionsDone % this.settings.sessionsUntilLongBreak === 0;
                this.pomoPhase    = isLongBreak ? 'long_break' : 'short_break';
                this.pomoTimeLeft = isLongBreak
                    ? this.settings.longBreak * 60
                    : this.settings.shortBreak * 60;
            } else {
                // Break done — advance to next task
                if (this.currentPomoIndex < this.pomodoroQueue.length - 1) {
                    this.currentPomoIndex++;
                }
                this.pomoPhase    = 'work';
                this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            }
        },

        skipPhase() {
            this.advancePhase();
        },

        resetPomo() {
            clearInterval(this.pomoInterval);
            this.pomoInterval    = null;
            this.pomoRunning     = false;
            this.pomoPhase       = 'work';
            this.pomoSessionsDone = 0;
            this.currentPomoIndex = 0;
            this.pomoTimeLeft    = this.settings.pomodoroDuration * 60;
        },

        // ── Settings ──────────────────────────────
        async saveSettings() {
            // Update timer if not running
            if (!this.pomoRunning) {
                this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            }

            if (this.token) {
                this.saving = true;
                try {
                    await this.api('PUT', `/fp-api/session/${this.token}/settings`, this.settings);
                } catch (e) { /* silent */ }
                this.saving = false;
            }
            this.showSettings = false;
        },

        // ── Helpers ───────────────────────────────
        formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },

        async api(method, url, body = null) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const options   = {
                method,
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-CSRF-TOKEN':     csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            };
            if (body && method !== 'GET') options.body = JSON.stringify(body);

            const response = await fetch(url, options);

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message ?? `HTTP ${response.status}`);
            }

            return response.json();
        },
    };
}
</script>
@endpush
