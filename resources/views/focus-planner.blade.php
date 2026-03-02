{{--
  Focus Planner — drag-and-drop daily planner with Pomodoro timer + Projects.
  Server-synced via /fp-api/* routes. No auth required — email-based sessions.
--}}

@push('head')
<style>
    [x-cloak] { display: none !important; }

    /* Drag and drop */
    .fp-drag-over { border-top: 2px solid #0284c7 !important; }
    .fp-dragging  { opacity: 0.4; }
    .fp-task-row:hover .fp-drag-handle { opacity: 1; }
    .fp-drag-handle { opacity: 0; transition: opacity 0.15s; cursor: grab; flex-shrink: 0; }
    .fp-drag-handle:active { cursor: grabbing; }

    /* Pomodoro ring colours */
    .fp-phase-work  { stroke: #0284c7; }
    .fp-phase-short { stroke: #10b981; }
    .fp-phase-long  { stroke: #8b5cf6; }

    /* Smooth circular checkbox */
    .fp-check { width:18px; height:18px; border-radius:50%; border:2px solid #d1d5db; appearance:none; cursor:pointer; transition:all 0.15s; flex-shrink:0; }
    .fp-check:checked { background:#0284c7; border-color:#0284c7; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 12 10' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 5l4 4L11 1' stroke='white' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-size:70%; background-position:center; background-repeat:no-repeat; }

    /* Hide scrollbar on project rail */
    .fp-no-scrollbar { scrollbar-width: none; -webkit-overflow-scrolling: touch; }
    .fp-no-scrollbar::-webkit-scrollbar { display: none; }

    /* Full-screen overlay */
    .fp-fullscreen {
        position: fixed !important; inset: 0 !important; z-index: 9999 !important;
        background: #fff !important; border-radius: 0 !important;
        overflow-y: auto; margin: 0 !important;
        display: flex; flex-direction: column;
    }
    .fp-fullscreen .fp-scroll-area { flex: 1; overflow-y: auto; }
</style>
@endpush

<div x-data="focusPlanner()" x-init="init()" x-cloak class="-m-6">

    {{-- LOADING --}}
    <div x-show="screen === 'loading'" class="flex items-center justify-center h-48">
        <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
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

        <div x-show="!showRecover" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5 uppercase tracking-wide">Your email address</label>
                <input x-model="email" type="email" placeholder="you@example.com" @keydown.enter="startSession()"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none">
            </div>
            <div x-show="startError" x-text="startError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
            <div x-show="startMessage" x-text="startMessage" class="text-sm text-primary-700 bg-primary-50 border border-primary-200 rounded-lg px-3 py-2"></div>
            <button @click="startSession()" :disabled="starting || !email.trim()"
                class="w-full py-3 px-4 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors text-sm">
                <span x-show="!starting">Get my planner →</span>
                <span x-show="starting">Setting up…</span>
            </button>
            <p class="text-center text-xs text-gray-400">
                Already have one?
                <button @click="showRecover = true" class="text-primary-600 hover:underline font-medium">Recover your planner</button>
            </p>
        </div>

        <div x-show="showRecover" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5 uppercase tracking-wide">Enter your email to recover</label>
                <input x-model="recoverEmail" type="email" placeholder="you@example.com" @keydown.enter="sendRecovery()"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none">
            </div>
            <div x-show="recoverSent" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                Check your inbox — we sent a link to sign you back in.
            </div>
            <button @click="sendRecovery()" :disabled="recovering || !recoverEmail.trim()"
                class="w-full py-3 px-4 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors text-sm">
                <span x-show="!recovering">Send recovery link</span>
                <span x-show="recovering">Sending…</span>
            </button>
            <p class="text-center text-xs text-gray-400">
                <button @click="showRecover = false" class="text-primary-600 hover:underline">← Back</button>
            </p>
        </div>

        <div class="bg-primary-50 border border-primary-200 rounded-xl px-4 py-3 text-xs text-primary-700">
            <strong>How it works:</strong> Enter your email to create a personal planner that syncs to any device.
            Your email is only used to send recovery links — we never send marketing email.
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         MAIN PLANNER
    ══════════════════════════════════════════════════ --}}
    <div x-show="screen === 'planner'"
        class="flex flex-col min-h-[480px]"
        :class="{ 'fp-fullscreen': fullscreen }">

        {{-- ── Top bar ── --}}
        <div class="flex items-center justify-between gap-2 px-4 sm:px-6 pt-4 pb-3 border-b border-gray-100 shrink-0">
            <div class="font-bold text-gray-900 text-sm sm:text-base leading-tight" x-text="todayLabel"></div>

            <div class="flex items-center gap-1.5 shrink-0">
                {{-- Pomodoro toggle --}}
                <button @click="pomodoroOpen = !pomodoroOpen"
                    class="flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg border transition-colors"
                    :class="pomodoroOpen ? 'bg-red-50 border-red-200 text-red-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                    <span>🍅</span>
                    <span class="hidden sm:inline" x-text="pomodoroOpen ? 'Close' : 'Pomodoro'"></span>
                </button>

                {{-- Settings --}}
                <button @click="showSettings = true"
                    class="p-1.5 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors" title="Settings">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>

                {{-- Full-screen (desktop only) --}}
                <button @click="fullscreen = !fullscreen"
                    class="hidden sm:flex p-1.5 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"
                    :title="fullscreen ? 'Exit full screen' : 'Full screen'">
                    <template x-if="!fullscreen">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </template>
                    <template x-if="fullscreen">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4v5h5zm0 0L4 4m11 5h5V4h-5v5zm0 0l5-5M9 15H4v5h5v-5zm0 0l-5 5m11-5v5h5v-5h-5zm0 0l5 5"/></svg>
                    </template>
                </button>
            </div>
        </div>

        {{-- ── Nav + Project rail ── --}}
        {{-- Outer div is `relative` so the dropdown can escape the overflow-x-auto scroll container --}}
        <div class="shrink-0 border-b border-gray-100 relative" @click.outside="showProjectDropdown = false; projectSearch = ''">
            <div class="fp-no-scrollbar flex items-center gap-1.5 overflow-x-auto px-4 sm:px-6 py-2">

                {{-- Today / Upcoming view selector --}}
                <button @click="currentView = 'today'"
                    class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap transition-all"
                    :class="currentView === 'today' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                    Today
                </button>
                <button @click="currentView = 'upcoming'"
                    class="flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap transition-all"
                    :class="currentView === 'upcoming' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                    Upcoming
                    <span x-show="upcomingCount > 0" x-text="upcomingCount"
                        class="rounded-full w-4 h-4 flex items-center justify-center text-[10px] font-bold"
                        :class="currentView === 'upcoming' ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-700'"></span>
                </button>

                {{-- Divider --}}
                <div class="w-px h-4 bg-gray-200 mx-1 flex-shrink-0"></div>

                {{-- Project filter trigger — dropdown panel lives OUTSIDE this overflow container below --}}
                <button @click="showProjectDropdown = !showProjectDropdown"
                    class="flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap transition-all flex-shrink-0 max-w-[180px]"
                    :class="currentProjectId !== 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                    <span x-show="currentProjectId === 'all'">All Projects</span>
                    <span x-show="currentProjectId === 'inbox'">📥 Inbox</span>
                    <template x-if="currentProjectId !== 'all' && currentProjectId !== 'inbox'">
                        <span class="flex items-center gap-1.5 truncate min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0"
                                :style="`background-color: ${getProjectById(currentProjectId)?.color ?? '#ccc'}`"></span>
                            <span class="truncate" x-text="getProjectById(currentProjectId)?.name ?? ''"></span>
                        </span>
                    </template>
                    <svg class="h-3 w-3 flex-shrink-0 opacity-60 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>

            {{-- Dropdown panel — direct child of the `relative` outer div, NOT inside overflow-x-auto --}}
            <div x-show="showProjectDropdown"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="absolute top-full left-4 sm:left-6 mt-0 w-60 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">

                {{-- Search --}}
                <div class="p-2 border-b border-gray-100">
                    <input x-model="projectSearch" placeholder="Search projects…" @click.stop
                        x-ref="projectSearchInput"
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>

                {{-- List --}}
                <div class="max-h-56 overflow-y-auto p-1 space-y-0.5">
                    {{-- All --}}
                    <button x-show="!projectSearch"
                        @click="currentProjectId = 'all'; projectPickerItemId = null; showProjectDropdown = false; projectSearch = ''"
                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs rounded-lg transition-all text-left"
                        :class="currentProjectId === 'all' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50 text-gray-700'">
                        All tasks
                    </button>
                    {{-- Inbox --}}
                    <button x-show="!projectSearch || 'inbox'.includes(projectSearch.toLowerCase())"
                        @click="currentProjectId = 'inbox'; projectPickerItemId = null; showProjectDropdown = false; projectSearch = ''"
                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs rounded-lg transition-all text-left"
                        :class="currentProjectId === 'inbox' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50 text-gray-700'">
                        📥 Inbox
                    </button>
                    {{-- Separator --}}
                    <div x-show="projects.length > 0 && !projectSearch" class="h-px bg-gray-100 my-1"></div>
                    {{-- Project rows --}}
                    <template x-for="project in filteredProjects" :key="project.id">
                        <div class="flex items-center rounded-lg group/prow"
                            :class="currentProjectId === project.id ? 'bg-gray-900' : 'hover:bg-gray-50'">
                            <button
                                @click="currentProjectId = project.id; projectPickerItemId = null; showProjectDropdown = false; projectSearch = ''"
                                class="flex-1 flex items-center gap-2 px-2.5 py-1.5 text-xs text-left min-w-0"
                                :class="currentProjectId === project.id ? 'text-white' : 'text-gray-700'">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="`background-color: ${project.color}`"></span>
                                <span class="truncate" x-text="project.name"></span>
                            </button>
                            <button @click.stop="deleteProject(project)"
                                class="opacity-0 group-hover/prow:opacity-100 px-2 py-1.5 text-sm transition-all flex-shrink-0"
                                :class="currentProjectId === project.id ? 'text-white/50 hover:text-white' : 'text-gray-300 hover:text-red-400'"
                                title="Delete project">×</button>
                        </div>
                    </template>
                    {{-- Empty search state --}}
                    <div x-show="projectSearch && filteredProjects.length === 0"
                        class="px-2.5 py-4 text-xs text-gray-400 text-center">
                        No projects match
                    </div>
                </div>

                {{-- New project footer --}}
                <div class="border-t border-gray-100 p-1">
                    <button @click="showNewProject = true; showProjectDropdown = false; projectSearch = ''; newProjectName = ''; newProjectColor = nextProjectColor()"
                        class="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs text-gray-500 hover:bg-gray-50 hover:text-primary-600 rounded-lg transition-all font-medium">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New project
                    </button>
                </div>
            </div>

            {{-- Inline new project form --}}
            <div x-show="showNewProject" x-transition class="px-4 sm:px-6 pb-2 flex items-center gap-2 flex-wrap">
                {{-- Color swatches --}}
                <template x-for="color in projectColors" :key="color">
                    <button @click="newProjectColor = color"
                        class="w-5 h-5 rounded-full transition-all flex-shrink-0"
                        :style="`background-color: ${color}`"
                        :class="newProjectColor === color ? 'ring-2 ring-offset-1 ring-gray-500 scale-110' : 'opacity-70 hover:opacity-100'">
                    </button>
                </template>
                <input x-model="newProjectName" placeholder="Project name…"
                    @keydown.enter="createProject()" @keydown.escape="showNewProject = false"
                    x-ref="newProjectInput"
                    class="flex-1 min-w-32 px-3 py-1 text-xs border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-primary-500">
                <button @click="createProject()" :disabled="!newProjectName.trim()"
                    class="px-3 py-1 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-40 transition-colors">
                    Add
                </button>
                <button @click="showNewProject = false" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
            </div>
        </div>

        {{-- ── Pomodoro panel ── --}}
        <div x-show="pomodoroOpen" x-transition class="mx-4 sm:mx-6 mt-4 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm shrink-0">
            <div class="flex flex-col sm:flex-row items-stretch">

                {{-- Timer --}}
                <div class="flex flex-col items-center justify-center gap-3 p-5 sm:border-r border-gray-100 sm:w-48 shrink-0">
                    <div class="relative w-24 h-24">
                        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="8" stroke="#e5e7eb"/>
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="8"
                                :class="{ 'fp-phase-work': pomoPhase==='work', 'fp-phase-short': pomoPhase==='short_break', 'fp-phase-long': pomoPhase==='long_break' }"
                                :stroke-dasharray="2 * Math.PI * 42"
                                :stroke-dashoffset="ringOffset"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-0.5">
                            <span class="text-xl font-bold tabular-nums leading-none text-gray-900" x-text="formatTime(pomoTimeLeft)"></span>
                            <span class="text-[9px] font-semibold uppercase tracking-wide leading-none"
                                :class="{ 'text-primary-600': pomoPhase==='work', 'text-emerald-600': pomoPhase==='short_break', 'text-violet-600': pomoPhase==='long_break' }"
                                x-text="pomoPhase==='work' ? 'Focus' : pomoPhase==='short_break' ? 'Short break' : 'Long break'">
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="resetPomo()" title="Reset" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                        <button @click="togglePomo()"
                            class="px-4 py-1.5 rounded-lg font-semibold text-sm transition-colors"
                            :class="pomoRunning ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-primary-600 text-white hover:bg-primary-700'">
                            <span x-text="pomoRunning ? 'Pause' : 'Start'"></span>
                        </button>
                        <button @click="skipPhase()" title="Skip" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <button @click="pomoFocus = true" title="Focus mode" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 hover:text-primary-600 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <template x-for="i in settings.sessionsUntilLongBreak" :key="i">
                            <div class="w-2 h-2 rounded-full transition-colors"
                                :class="(pomoSessionsDone % settings.sessionsUntilLongBreak) >= i ? 'bg-primary-500' : 'bg-gray-200'">
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Queue — accepts drops from task list; items are reorderable --}}
                <div class="flex-1 p-4 space-y-1.5 max-h-52 overflow-y-auto rounded-xl transition-all"
                    @dragover.prevent="if (dragSrc !== null) queueDropZone = true"
                    @dragleave.self="queueDropZone = false"
                    @drop.prevent="dropOnQueue(); queueDropZone = false"
                    :class="queueDropZone ? 'bg-primary-50 outline outline-2 outline-primary-400' : ''">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Queue</p>
                        <span class="text-xs text-gray-400" x-text="pomodoroQueue.length + ' task' + (pomodoroQueue.length !== 1 ? 's' : '')"></span>
                    </div>
                    <div x-show="pomodoroQueue.length === 0 && !queueDropZone" class="text-xs text-gray-400 italic py-2">
                        Add tasks using the 🍅 button or drag them here
                    </div>
                    <div x-show="queueDropZone && pomodoroQueue.length === 0" class="text-xs text-primary-600 font-medium py-2 text-center">
                        Drop to add to queue
                    </div>
                    <template x-for="(taskId, qIdx) in pomodoroQueue" :key="taskId">
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors"
                            draggable="true"
                            @dragstart.stop="qDragStart($event, qIdx)"
                            @dragover.prevent="qDragSrc !== null ? qDragTarget = qIdx : null"
                            @drop.prevent.stop="qDragSrc !== null ? qDrop(qIdx) : (dropOnQueue(), queueDropZone = false)"
                            @dragend.stop="qDragEnd()"
                            :class="{
                                'bg-primary-50 border border-primary-200': qIdx === currentPomoIndex && pomoPhase === 'work',
                                'bg-gray-50': !(qIdx === currentPomoIndex && pomoPhase === 'work'),
                                'opacity-40': qDragSrc === qIdx,
                                'border-t-2 !border-t-primary-400': qDragTarget === qIdx && qDragSrc !== qIdx,
                            }">
                            <button @click="markDoneInQueue(taskId)"
                                :title="getTaskById(taskId)?.completed ? 'Mark incomplete' : 'Mark complete'"
                                class="w-4 h-4 rounded-full border-2 flex-shrink-0 transition-all"
                                :class="getTaskById(taskId)?.completed ? 'bg-primary-500 border-primary-500' : 'border-gray-300 hover:border-primary-400'">
                            </button>
                            <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-primary-500"
                                x-show="qIdx === currentPomoIndex && pomoPhase === 'work'"></div>
                            <span class="text-sm flex-1 truncate"
                                :class="getTaskById(taskId)?.completed ? 'line-through text-gray-400' : qIdx === currentPomoIndex && pomoPhase === 'work' ? 'text-primary-700 font-medium' : 'text-gray-700'"
                                x-text="getTaskById(taskId)?.title || '(deleted)'"></span>
                            <span class="text-[10px] text-gray-400 flex-shrink-0 tabular-nums" x-show="(getTaskById(taskId)?.estimated_pomodoros ?? 0) > 0"
                                x-text="(getTaskById(taskId)?.completed_pomodoros ?? 0) + '/' + (getTaskById(taskId)?.estimated_pomodoros ?? 1) + '🍅'"></span>
                            <button @click="removeFromQueue(qIdx)" class="text-gray-300 hover:text-red-400 transition-colors flex-shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ── TODAY task list ── --}}
        <div x-show="currentView === 'today'"
            class="fp-scroll-area flex-1 overflow-y-auto px-4 sm:px-6 py-3 space-y-0.5"
            @click="projectPickerItemId = null">

            {{-- Empty state --}}
            <div x-show="todayVisibleCount === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="text-4xl mb-3">📝</div>
                <p class="text-sm font-medium text-gray-700"
                    x-text="currentProjectId === 'inbox' ? 'No inbox tasks for today' : currentProjectId !== 'all' ? 'No tasks in this project for today' : 'Your today list is empty'">
                </p>
                <p class="text-xs text-gray-400 mt-1">Add tasks below to get started</p>
            </div>

            <template x-for="(item, index) in items" :key="item.id">
                {{-- Outer wrapper: single root — contains the task row + optional project picker --}}
                <div x-show="isShownInTodayView(item)"
                    class="fp-task-row"
                    :class="{ 'fp-dragging': dragSrc === index, 'fp-drag-over': dragTarget === index && dragSrc !== index }"
                    draggable="true"
                    @dragstart="dragStart($event, index)"
                    @dragover.prevent="dragOver($event, index)"
                    @drop.prevent="drop($event, index)"
                    @dragend="dragEnd()">

                    {{-- Main row (flex, styled per type) --}}
                    <div :class="{
                        'flex items-center gap-2 pt-5 pb-1':                                                    item.type === 'header',
                        'flex items-center gap-2 px-1 py-1.5 rounded-xl hover:bg-gray-50 transition-colors group': item.type === 'task',
                    }">
                        {{-- Drag handle --}}
                        <span class="fp-drag-handle text-gray-300 select-none">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor">
                                <circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/>
                                <circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/>
                                <circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                            </svg>
                        </span>

                        {{-- Project dot (tasks only, when viewing all projects) --}}
                        {{-- Filled+visible when task has a project; faint on hover when unassigned --}}
                        <button x-show="item.type === 'task' && currentProjectId === 'all' && projects.length > 0"
                            @click.stop="projectPickerItemId = (projectPickerItemId === item.id ? null : item.id)"
                            :title="getProjectById(item.project_id)?.name ?? 'Assign to project'"
                            class="w-2.5 h-2.5 rounded-full flex-shrink-0 transition-all hover:scale-125"
                            :class="item.project_id ? 'opacity-100' : 'opacity-0 group-hover:opacity-30'"
                            :style="item.project_id ? `background-color: ${getProjectById(item.project_id)?.color ?? '#d1d5db'}` : 'background-color: #d1d5db'">
                        </button>

                        {{-- Checkbox --}}
                        <input x-show="item.type === 'task'" type="checkbox" class="fp-check"
                            :checked="item.completed" @change="toggleComplete(item)">

                        {{-- Title --}}
                        <input class="fp-title-input flex-1 bg-transparent border-none outline-none placeholder-gray-300 min-w-0"
                            :class="item.type === 'header'
                                ? 'text-xs font-bold uppercase tracking-widest text-gray-400'
                                : (item.completed ? 'text-sm text-gray-400 line-through' : 'text-sm text-gray-800')"
                            :placeholder="item.type === 'header' ? 'Section header…' : 'Task name…'"
                            x-model="item.title"
                            @change="saveTask(item)"
                            @keydown.enter="$event.target.blur()">

                        {{-- Schedule date picker (inline when open) --}}
                        <input x-show="item.type === 'task' && schedulingItemId === item.id"
                            type="date" :min="tomorrow()" :value="item.scheduled_for || ''"
                            @change="setSchedule(item, $event.target.value); schedulingItemId = null"
                            @click.stop
                            class="text-xs border border-violet-300 rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-violet-400">

                        {{-- Pomodoro count --}}
                        <div x-show="item.type === 'task'" class="flex items-center gap-0.5 shrink-0">
                            <button @click.stop="item.estimated_pomodoros = Math.max(1,(item.estimated_pomodoros||1)-1); saveTask(item)"
                                class="opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-all text-sm">−</button>
                            <span class="text-xs opacity-50" x-text="'🍅'.repeat(item.estimated_pomodoros||1)"></span>
                            <button @click.stop="item.estimated_pomodoros = Math.min(20,(item.estimated_pomodoros||1)+1); saveTask(item)"
                                class="opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-700 transition-all text-sm">+</button>
                        </div>

                        {{-- Schedule button --}}
                        <button x-show="item.type === 'task'"
                            @click.stop="schedulingItemId = (schedulingItemId === item.id ? null : item.id)"
                            :title="item.scheduled_for ? 'Scheduled: ' + formatDate(item.scheduled_for) : 'Schedule for later'"
                            class="shrink-0 transition-all"
                            :class="item.scheduled_for ? 'text-violet-500' : 'opacity-0 group-hover:opacity-100 text-gray-300 hover:text-violet-500'">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </button>

                        {{-- Add to queue --}}
                        <button x-show="item.type === 'task'"
                            @click.stop="addToQueue(item)"
                            :title="pomodoroQueue.includes(item.id) ? 'In queue' : 'Add to queue'"
                            class="shrink-0 transition-all"
                            :class="pomodoroQueue.includes(item.id) ? 'text-primary-500' : 'opacity-0 group-hover:opacity-100 text-gray-300 hover:text-primary-500'">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>

                        {{-- Delete --}}
                        <button @click.stop="deleteItem(item)"
                            class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400 transition-all shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        {{-- Detail button (tasks only) --}}
                        <button x-show="item.type === 'task'"
                            @click.stop="openDetail(item)"
                            class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-primary-600 transition-all shrink-0"
                            title="Task details">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    {{-- Project picker (drops below task row when open) --}}
                    <div x-show="item.type === 'task' && projectPickerItemId === item.id" @click.stop
                        class="flex flex-wrap gap-1.5 px-8 pb-2 pt-1">
                        <button @click="assignProject(item, null)"
                            class="flex items-center gap-1 px-2.5 py-1 text-xs rounded-full transition-all"
                            :class="!item.project_id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                            No project
                        </button>
                        <template x-for="p in projects" :key="p.id">
                            <button @click="assignProject(item, p.id)"
                                class="flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-full transition-all"
                                :class="item.project_id === p.id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="`background-color: ${p.color}`"></span>
                                <span x-text="p.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- ── UPCOMING view ── --}}
        <div x-show="currentView === 'upcoming'" class="fp-scroll-area flex-1 overflow-y-auto px-4 sm:px-6 py-3">
            <div x-show="upcomingGroups.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="text-4xl mb-3">📅</div>
                <p class="text-sm font-medium text-gray-700">Nothing scheduled yet</p>
                <p class="text-xs text-gray-400 mt-1">Use the calendar icon on any task to schedule it for a future date</p>
            </div>
            <div class="space-y-6">
                <template x-for="group in upcomingGroups" :key="group.date">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-gray-400 pb-2 mb-1 border-b border-gray-100"
                            x-text="formatDate(group.date)"></div>
                        <template x-for="item in group.items" :key="item.id">
                            <div class="flex items-center gap-2 px-1 py-1.5 rounded-xl hover:bg-gray-50 transition-colors group">
                                {{-- Project dot --}}
                                <span x-show="item.project_id && currentProjectId === 'all'"
                                    class="w-2 h-2 rounded-full flex-shrink-0"
                                    :style="`background-color: ${getProjectById(item.project_id)?.color ?? '#d1d5db'}`"></span>
                                <input type="checkbox" class="fp-check" :checked="item.completed" @change="toggleComplete(item)">
                                <span class="flex-1 text-sm min-w-0 truncate"
                                    :class="item.completed ? 'line-through text-gray-400' : 'text-gray-800'"
                                    x-text="item.title || '(untitled)'"></span>
                                <span x-show="item.estimated_pomodoros" class="text-xs opacity-40 shrink-0"
                                    x-text="'🍅'.repeat(item.estimated_pomodoros||1)"></span>
                                <button @click="setSchedule(item, null)" title="Move back to Today"
                                    class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-violet-500 transition-all shrink-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── Add buttons (today only) ── --}}
        <div x-show="currentView === 'today'" class="flex items-center gap-2 px-4 sm:px-6 pb-4 pt-2 border-t border-gray-100 shrink-0">
            <button @click="addTask()" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition-colors font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add task
            </button>
            <span class="text-gray-200">·</span>
            <button @click="addHeader()" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition-colors font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/></svg>
                Add header
            </button>
            <div class="ml-auto flex items-center gap-3">
                {{-- Completed toggle --}}
                <button x-show="completedTodayCount > 0 || showCompleted"
                    @click="showCompleted = !showCompleted"
                    class="flex items-center gap-1.5 text-xs transition-colors"
                    :class="showCompleted ? 'text-gray-500 hover:text-gray-700' : 'text-gray-400 hover:text-gray-600'">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="showCompleted ? 'Hide completed' : completedTodayCount + ' completed'"></span>
                </button>
                <span x-show="saving" class="text-xs text-gray-400 flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════
             FOCUS MODE — lives INSIDE the planner div so it
             shares the same stacking context whether the tool
             is in its own fullscreen mode or not.
        ══════════════════════════════════════════════════ --}}
        <div x-show="pomoFocus" x-cloak
            @keydown.escape.window="pomoFocus = false"
            class="fixed inset-0 z-[10000] bg-gray-950 flex flex-col items-center justify-center gap-7 p-8 overflow-y-auto">

            {{-- Exit button --}}
            <button @click="pomoFocus = false"
                class="absolute top-5 right-5 p-2 rounded-lg text-gray-600 hover:text-white hover:bg-white/10 transition-colors"
                title="Exit focus mode (Esc)">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4v5h5zm0 0L4 4m11 5h5V4h-5v5zm0 0l5-5M9 15H4v5h5v-5zm0 0l-5 5m11-5v5h5v-5h-5zm0 0l5 5"/></svg>
            </button>

            {{-- Big timer ring --}}
            <div class="relative w-52 h-52 sm:w-64 sm:h-64 flex-shrink-0">
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <circle cx="50" cy="50" r="42" fill="none" stroke-width="5" stroke="#1f2937"/>
                    <circle cx="50" cy="50" r="42" fill="none" stroke-width="5"
                        :class="{ 'fp-phase-work': pomoPhase==='work', 'fp-phase-short': pomoPhase==='short_break', 'fp-phase-long': pomoPhase==='long_break' }"
                        :stroke-dasharray="2 * Math.PI * 42"
                        :stroke-dashoffset="ringOffset"
                        stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5">
                    <span class="text-5xl sm:text-6xl font-bold tabular-nums text-white leading-none" x-text="formatTime(pomoTimeLeft)"></span>
                    <span class="text-xs font-semibold uppercase tracking-widest"
                        :class="{ 'text-primary-400': pomoPhase==='work', 'text-emerald-400': pomoPhase==='short_break', 'text-violet-400': pomoPhase==='long_break' }"
                        x-text="pomoPhase==='work' ? 'Focus' : pomoPhase==='short_break' ? 'Short break' : 'Long break'">
                    </span>
                </div>
            </div>

            {{-- Session dots --}}
            <div class="flex items-center gap-2">
                <template x-for="i in settings.sessionsUntilLongBreak" :key="i">
                    <div class="w-2.5 h-2.5 rounded-full transition-colors"
                        :class="(pomoSessionsDone % settings.sessionsUntilLongBreak) >= i ? 'bg-primary-500' : 'bg-gray-800'">
                    </div>
                </template>
            </div>

            {{-- Controls --}}
            <div class="flex items-center gap-3">
                <button @click="resetPomo()" title="Reset"
                    class="p-2.5 rounded-xl border border-gray-800 hover:border-gray-600 text-gray-500 hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
                <button @click="togglePomo()"
                    class="px-10 py-3 rounded-xl font-bold text-base transition-colors"
                    :class="pomoRunning ? 'bg-red-900/50 text-red-300 hover:bg-red-900/80' : 'bg-primary-600 text-white hover:bg-primary-700'">
                    <span x-text="pomoRunning ? 'Pause' : 'Start'"></span>
                </button>
                <button @click="skipPhase()" title="Skip phase"
                    class="p-2.5 rounded-xl border border-gray-800 hover:border-gray-600 text-gray-500 hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            {{-- Current task (work phase) --}}
            <div x-show="pomodoroQueue.length > 0 && pomoPhase === 'work'" class="text-center max-w-lg px-4">
                <p class="text-xs text-gray-600 uppercase tracking-widest mb-2">Now focusing on</p>
                <p class="text-2xl sm:text-3xl font-semibold text-white leading-snug"
                    x-text="getTaskById(pomodoroQueue[currentPomoIndex])?.title || '(untitled)'"></p>
                <p class="text-xs text-gray-600 mt-2 tabular-nums"
                    x-show="(getTaskById(pomodoroQueue[currentPomoIndex])?.estimated_pomodoros ?? 0) > 1"
                    x-text="(getTaskById(pomodoroQueue[currentPomoIndex])?.completed_pomodoros ?? 0) + ' of ' + (getTaskById(pomodoroQueue[currentPomoIndex])?.estimated_pomodoros ?? 1) + ' pomodoros done'">
                </p>
            </div>

            {{-- Break message --}}
            <div x-show="pomoPhase !== 'work'" class="text-center">
                <p class="text-xl font-semibold"
                    :class="pomoPhase === 'short_break' ? 'text-emerald-400' : 'text-violet-400'"
                    x-text="pomoPhase === 'short_break' ? 'Take a short break ☕' : 'Long break — you earned it 🌿'"></p>
            </div>

            {{-- Queue --}}
            <div x-show="pomodoroQueue.length > 0" class="w-full max-w-xs space-y-1">
                <p class="text-[10px] text-gray-700 uppercase tracking-widest text-center mb-2">Queue</p>
                <template x-for="(taskId, qIdx) in pomodoroQueue" :key="taskId">
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all"
                        :class="qIdx === currentPomoIndex && pomoPhase === 'work' ? 'bg-white/10' : ''">
                        <button @click="markDoneInQueue(taskId)"
                            class="w-4 h-4 rounded-full border-2 flex-shrink-0 transition-all"
                            :class="getTaskById(taskId)?.completed ? 'bg-primary-500 border-primary-500' : 'border-gray-600 hover:border-primary-500'">
                        </button>
                        <span class="text-sm flex-1 truncate"
                            :class="getTaskById(taskId)?.completed ? 'line-through text-gray-600' : 'text-gray-300'"
                            x-text="getTaskById(taskId)?.title || '(deleted)'"></span>
                        <span class="text-[10px] text-gray-600 flex-shrink-0 tabular-nums"
                            x-show="(getTaskById(taskId)?.estimated_pomodoros ?? 0) > 0"
                            x-text="(getTaskById(taskId)?.completed_pomodoros ?? 0) + '/' + (getTaskById(taskId)?.estimated_pomodoros ?? 1) + '🍅'"></span>
                    </div>
                </template>
            </div>

            {{-- Empty queue hint --}}
            <div x-show="pomodoroQueue.length === 0" class="text-center text-gray-600 text-sm">
                Add tasks to the Pomodoro queue to begin a focus session
            </div>
        </div>

    </div>{{-- /planner --}}

    {{-- ══════════════════════════════════════════════════
         SETTINGS MODAL
    ══════════════════════════════════════════════════ --}}
    <div x-show="showSettings" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="showSettings = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Settings</h3>
                <button @click="showSettings = false" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Work hours</label>
                <div class="flex items-center gap-2">
                    <input type="time" x-model="settings.workStart" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                    <span class="text-gray-400 text-sm">to</span>
                    <input type="time" x-model="settings.workEnd" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Pomodoro timer (minutes)</label>
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-1"><label class="text-xs text-gray-500">Focus</label>
                        <input type="number" x-model.number="settings.pomodoroDuration" min="1" max="120" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary-500 outline-none"></div>
                    <div class="space-y-1"><label class="text-xs text-gray-500">Short break</label>
                        <input type="number" x-model.number="settings.shortBreak" min="1" max="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary-500 outline-none"></div>
                    <div class="space-y-1"><label class="text-xs text-gray-500">Long break</label>
                        <input type="number" x-model.number="settings.longBreak" min="1" max="120" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary-500 outline-none"></div>
                </div>
                <div class="mt-2 space-y-1">
                    <label class="text-xs text-gray-500">Sessions before long break</label>
                    <input type="number" x-model.number="settings.sessionsUntilLongBreak" min="1" max="10" class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            <div x-show="token" class="pt-2 border-t border-gray-100">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Account</label>
                <p class="text-xs text-gray-500 mb-2">Your planner is saved to your email address.</p>
                <button @click="signOut()" class="text-xs text-red-500 hover:underline">Sign out and clear local data</button>
            </div>
            <button @click="saveSettings()" class="w-full py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl text-sm transition-colors">Save settings</button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         TASK DETAIL PANEL
    ══════════════════════════════════════════════════ --}}
    <div x-show="taskDetail !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 px-0 sm:px-4"
        @click.self="closeDetail()">

        <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl shadow-xl flex flex-col max-h-[85vh] rounded-t-2xl"
            @keydown.escape.window="closeDetail()">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-2">
                    {{-- Completion toggle --}}
                    <button @click="toggleComplete(taskDetail)"
                        class="w-5 h-5 rounded-full border-2 flex-shrink-0 transition-all"
                        :class="taskDetail?.completed ? 'bg-primary-500 border-primary-500' : 'border-gray-300 hover:border-primary-400'">
                    </button>
                    <span class="text-xs text-gray-400" x-text="taskDetail?.completed ? 'Completed' : 'Not completed'"></span>
                </div>
                <button @click="closeDetail()" class="text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                {{-- Title --}}
                <textarea x-model="taskDetail.title"
                    @change="saveTask(taskDetail)"
                    rows="2"
                    placeholder="Task name…"
                    class="w-full text-lg font-semibold text-gray-900 border-none outline-none resize-none placeholder-gray-300 leading-snug bg-transparent"></textarea>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Notes</label>
                    <textarea x-model="taskDetail.notes"
                        @change="saveNotes(taskDetail)"
                        rows="5"
                        placeholder="Add notes, links, or context…"
                        class="w-full text-sm text-gray-700 border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none placeholder-gray-300 leading-relaxed"></textarea>
                </div>

                {{-- Meta row --}}
                <div class="grid grid-cols-2 gap-3">

                    {{-- Project --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Project</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button @click="assignProject(taskDetail, null)"
                                class="flex items-center gap-1 px-2.5 py-1 text-xs rounded-full transition-all"
                                :class="!taskDetail?.project_id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                Inbox
                            </button>
                            <template x-for="p in projects" :key="p.id">
                                <button @click="assignProject(taskDetail, p.id)"
                                    class="flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-full transition-all"
                                    :class="taskDetail?.project_id === p.id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background-color: ${p.color}`"></span>
                                    <span x-text="p.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Schedule --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Schedule</label>
                        <input type="date"
                            :value="taskDetail?.scheduled_for || ''"
                            @change="setSchedule(taskDetail, $event.target.value || null)"
                            class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-gray-700">
                        <button x-show="taskDetail?.scheduled_for"
                            @click="setSchedule(taskDetail, null)"
                            class="mt-1 text-xs text-gray-400 hover:text-gray-700">
                            Clear date
                        </button>
                    </div>
                </div>

                {{-- Pomodoro estimate --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Pomodoro estimate</label>
                    <div class="flex items-center gap-2">
                        <button @click="taskDetail.estimated_pomodoros = Math.max(1,(taskDetail.estimated_pomodoros||1)-1); saveTask(taskDetail)"
                            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors font-medium">−</button>
                        <span class="text-sm font-semibold text-gray-700 w-6 text-center" x-text="taskDetail?.estimated_pomodoros || 1"></span>
                        <button @click="taskDetail.estimated_pomodoros = Math.min(20,(taskDetail.estimated_pomodoros||1)+1); saveTask(taskDetail)"
                            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors font-medium">+</button>
                        <span class="text-xs text-gray-400 ml-1" x-text="'🍅'.repeat(taskDetail?.estimated_pomodoros || 1)"></span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="shrink-0 px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                <button @click="deleteItem(taskDetail); closeDetail()"
                    class="text-xs text-red-400 hover:text-red-600 transition-colors">
                    Delete task
                </button>
                <button @click="closeDetail()"
                    class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-700 transition-colors">
                    Done
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function focusPlanner() {
    return {
        // ── State ──────────────────────────────────
        screen: 'loading',
        token:  null,

        // Email gate
        email: '', startError: '', startMessage: '', starting: false,
        showRecover: false, recoverEmail: '', recoverSent: false, recovering: false,

        // Session
        settings: {
            workStart: '09:00', workEnd: '17:00',
            pomodoroDuration: 25, shortBreak: 5, longBreak: 15, sessionsUntilLongBreak: 4,
        },
        items:    [],
        projects: [],

        // Views & project filter
        currentView:       'today',
        currentProjectId:  'all',
        schedulingItemId:  null,
        projectPickerItemId: null,

        // Project dropdown
        showProjectDropdown: false,
        projectSearch:       '',

        // New project form
        showNewProject:  false,
        newProjectName:  '',
        newProjectColor: '#0284c7',
        projectColors:   ['#0284c7','#10b981','#f97316','#8b5cf6','#ef4444','#14b8a6','#f59e0b','#ec4899'],

        // Drag & drop (task list)
        dragSrc: null, dragTarget: null,
        // Drag & drop (queue — drop from list + internal reorder)
        queueDropZone: false, qDragSrc: null, qDragTarget: null,

        // Pomodoro
        pomodoroOpen: false, pomodoroQueue: [], currentPomoIndex: 0,
        pomoPhase: 'work', pomoTimeLeft: 25 * 60, pomoRunning: false,
        pomoSessionsDone: 0, pomoInterval: null,

        // Task detail panel
        taskDetail: null,

        // Completed visibility
        showCompleted: false,

        // UI
        showSettings: false, fullscreen: false, pomoFocus: false, saving: false,

        // ── Computed ───────────────────────────────
        get todayLabel() {
            return new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
        },
        get hoursRemaining() {
            const [h, m] = this.settings.workEnd.split(':').map(Number);
            const end = new Date(); end.setHours(h, m, 0, 0);
            const diff = (end - Date.now()) / 3_600_000;
            return diff > 0 ? Math.round(diff * 10) / 10 : 0;
        },
        get todayVisibleCount() {
            return this.items.filter(i => this.isShownInTodayView(i)).length;
        },
        get upcomingCount() {
            const today = new Date().toISOString().split('T')[0];
            return this.items.filter(i => i.scheduled_for && i.scheduled_for > today).length;
        },
        get upcomingGroups() {
            const today = new Date().toISOString().split('T')[0];
            let future  = this.items.filter(i => i.scheduled_for && i.scheduled_for > today);
            if (this.currentProjectId === 'inbox') future = future.filter(i => !i.project_id);
            else if (this.currentProjectId !== 'all') future = future.filter(i => i.project_id === this.currentProjectId);
            future.sort((a, b) => (a.scheduled_for||'').localeCompare(b.scheduled_for||'') || (a.sort_order - b.sort_order));
            const groups = {};
            for (const item of future) {
                if (!groups[item.scheduled_for]) groups[item.scheduled_for] = [];
                groups[item.scheduled_for].push(item);
            }
            return Object.entries(groups).map(([date, items]) => ({ date, items }));
        },
        get completedTodayCount() {
            return this.items.filter(i => {
                if (i.type !== 'task' || !i.completed || !this.isToday(i)) return false;
                if (this.currentProjectId === 'all')   return true;
                if (this.currentProjectId === 'inbox') return !i.project_id;
                return i.project_id === this.currentProjectId;
            }).length;
        },
        get filteredProjects() {
            if (!this.projectSearch) return this.projects;
            const q = this.projectSearch.toLowerCase();
            return this.projects.filter(p => p.name.toLowerCase().includes(q));
        },
        get ringOffset() {
            const total = this.pomoPhase === 'work'
                ? this.settings.pomodoroDuration * 60
                : this.pomoPhase === 'short_break' ? this.settings.shortBreak * 60 : this.settings.longBreak * 60;
            return (2 * Math.PI * 42) * (1 - this.pomoTimeLeft / total);
        },

        // ── Init ──────────────────────────────────
        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            const urlToken  = urlParams.get('token');
            if (urlToken) {
                localStorage.setItem('fp_token', urlToken);
                window.history.replaceState({}, '', window.location.pathname);
            }
            const storedToken = localStorage.getItem('fp_token');
            if (storedToken) await this.loadSession(storedToken);
            else this.screen = 'start';
        },

        // ── Session ───────────────────────────────
        async startSession() {
            if (!this.email.trim()) return;
            this.starting = true; this.startError = ''; this.startMessage = '';
            try {
                const res = await this.api('POST', '/fp-api/session', { email: this.email.trim() });
                if (res.status === 'existing') { this.startMessage = res.message; this.starting = false; return; }
                localStorage.setItem('fp_token', res.token);
                this.token    = res.token;
                this.settings = { ...this.settings, ...(res.settings ?? {}) };
                this.items    = res.tasks ?? [];
                this.projects = res.projects ?? [];
                this.screen   = 'planner';
            } catch (e) { this.startError = 'Something went wrong — please try again.'; }
            this.starting = false;
        },
        async loadSession(token) {
            try {
                const res = await this.api('GET', '/fp-api/session/' + token);
                this.token    = res.token;
                this.settings = { ...this.settings, ...(res.settings ?? {}) };
                this.items    = res.tasks ?? [];
                this.projects = res.projects ?? [];
                this.screen   = 'planner';
                this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            } catch (e) { localStorage.removeItem('fp_token'); this.screen = 'start'; }
        },
        async sendRecovery() {
            if (!this.recoverEmail.trim()) return;
            this.recovering = true;
            try { await this.api('POST', '/fp-api/recover', { email: this.recoverEmail.trim() }); this.recoverSent = true; }
            catch (e) {}
            this.recovering = false;
        },
        signOut() {
            if (!confirm('This will clear your local session. You can always recover your planner with your email. Continue?')) return;
            localStorage.removeItem('fp_token');
            this.token = null; this.items = []; this.projects = []; this.screen = 'start';
        },

        // ── View helpers ──────────────────────────
        isToday(item) {
            const today = new Date().toISOString().split('T')[0];
            return !item.scheduled_for || item.scheduled_for <= today;
        },
        isUpcoming(item) {
            const today = new Date().toISOString().split('T')[0];
            return !!(item.scheduled_for && item.scheduled_for > today);
        },
        isShownInTodayView(item) {
            if (!this.isToday(item)) return false;
            if (item.type === 'task' && item.completed && !this.showCompleted) return false;
            if (this.currentProjectId === 'all')   return true;
            if (this.currentProjectId === 'inbox') return !item.project_id;
            return item.project_id === this.currentProjectId;
        },
        tomorrow() {
            const d = new Date(); d.setDate(d.getDate() + 1);
            return d.toISOString().split('T')[0];
        },
        formatDate(dateStr) {
            const d = new Date(dateStr + 'T12:00:00');
            const today = new Date(); today.setHours(0,0,0,0);
            const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
            if (d.getTime() === today.getTime())    return 'Today';
            if (d.getTime() === tomorrow.getTime()) return 'Tomorrow';
            return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
        },
        async setSchedule(item, date) {
            item.scheduled_for = date || null;
            if (!this.token || String(item.id).startsWith('tmp-')) return;
            try { await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, { scheduled_for: item.scheduled_for }); }
            catch (e) {}
        },

        // ── Projects ──────────────────────────────
        getProjectById(id) {
            return this.projects.find(p => p.id === id) ?? null;
        },
        nextProjectColor() {
            return this.projectColors[this.projects.length % this.projectColors.length];
        },
        async createProject() {
            if (!this.newProjectName.trim() || !this.token) return;
            try {
                const project = await this.api('POST', `/fp-api/session/${this.token}/projects`, {
                    name: this.newProjectName.trim(), color: this.newProjectColor,
                });
                this.projects.push(project);
                this.currentProjectId = project.id;
                this.newProjectName   = '';
                this.showNewProject   = false;
            } catch (e) {}
        },
        async deleteProject(project) {
            if (!confirm(`Delete "${project.name}"? Tasks will move to Inbox.`)) return;
            this.projects = this.projects.filter(p => p.id !== project.id);
            this.items.forEach(i => { if (i.project_id === project.id) i.project_id = null; });
            if (this.currentProjectId === project.id) this.currentProjectId = 'all';
            try { await this.api('DELETE', `/fp-api/session/${this.token}/projects/${project.id}`); }
            catch (e) {}
        },
        async assignProject(item, projectId) {
            item.project_id = projectId;
            this.projectPickerItemId = null;
            if (!this.token || String(item.id).startsWith('tmp-')) return;
            try { await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, { project_id: projectId }); }
            catch (e) {}
        },

        // ── Task detail ───────────────────────────
        openDetail(item) {
            this.taskDetail = item;
            this.projectPickerItemId = null;
        },
        closeDetail() {
            this.taskDetail = null;
        },
        async saveNotes(item) {
            if (!this.token || String(item.id).startsWith('tmp-')) return;
            try { await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, { notes: item.notes ?? null }); }
            catch (e) {}
        },

        // ── Tasks ─────────────────────────────────
        addTask() {
            const projectId = ['all', 'inbox'].includes(this.currentProjectId) ? null : this.currentProjectId;
            this.items.push({
                id: 'tmp-' + Date.now(), type: 'task', title: '', notes: null, completed: false,
                sort_order: this.items.length, estimated_pomodoros: 1, completed_pomodoros: 0,
                scheduled_for: null, project_id: projectId,
            });
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('.fp-title-input');
                inputs[inputs.length - 1]?.focus();
            });
        },
        addHeader() {
            this.items.push({
                id: 'tmp-' + Date.now(), type: 'header', title: '', completed: false,
                sort_order: this.items.length, estimated_pomodoros: 0, completed_pomodoros: 0,
                scheduled_for: null, project_id: null,
            });
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('.fp-title-input');
                inputs[inputs.length - 1]?.focus();
            });
        },
        async saveTask(item) {
            if (!this.token) return;
            this.saving = true;
            if (String(item.id).startsWith('tmp-')) {
                try {
                    const created = await this.api('POST', `/fp-api/session/${this.token}/tasks`, {
                        type: item.type, title: item.title,
                        estimated_pomodoros: item.estimated_pomodoros || 1,
                        scheduled_for: item.scheduled_for || null,
                        project_id: item.project_id || null,
                    });
                    const idx = this.items.findIndex(i => i.id === item.id);
                    if (idx !== -1) this.items.splice(idx, 1, created);
                } catch (e) {}
            } else {
                try {
                    await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, {
                        title: item.title, estimated_pomodoros: item.estimated_pomodoros,
                    });
                } catch (e) {}
            }
            this.saving = false;
        },
        async toggleComplete(item) {
            if (!item) return;
            item.completed = !item.completed;
            if (!this.token || String(item.id).startsWith('tmp-')) return;
            this.saving = true;
            try { await this.api('PUT', `/fp-api/session/${this.token}/tasks/${item.id}`, { completed: item.completed }); }
            catch (e) {}
            this.saving = false;
        },
        async deleteItem(item) {
            this.items         = this.items.filter(i => i.id !== item.id);
            this.pomodoroQueue = this.pomodoroQueue.filter(id => id !== item.id);
            if (!this.token || String(item.id).startsWith('tmp-')) return;
            try { await this.api('DELETE', `/fp-api/session/${this.token}/tasks/${item.id}`); }
            catch (e) {}
        },
        async saveReorder() {
            if (!this.token) return;
            const ids = this.items.filter(i => !String(i.id).startsWith('tmp-')).map(i => i.id);
            this.saving = true;
            try { await this.api('POST', `/fp-api/session/${this.token}/tasks/reorder`, { ids }); }
            catch (e) {}
            this.saving = false;
        },

        // ── Drag & drop ───────────────────────────
        dragStart(event, index) { this.dragSrc = index; event.dataTransfer.effectAllowed = 'move'; },
        dragOver(event, index)  { if (this.dragSrc !== index) this.dragTarget = index; },
        drop(event, index) {
            if (this.dragSrc === null || this.dragSrc === index) { this.dragSrc = this.dragTarget = null; return; }
            const [moved] = this.items.splice(this.dragSrc, 1);
            this.items.splice(index, 0, moved);
            this.dragSrc = this.dragTarget = null;
            this.saveReorder();
        },
        dragEnd() { this.dragSrc = this.dragTarget = null; this.queueDropZone = false; },

        // ── Queue drag (drop from list + internal reorder) ────────────────────
        dropOnQueue() {
            if (this.dragSrc === null) return;
            const item = this.items[this.dragSrc];
            if (item && item.type === 'task') this.addToQueue(item);
            this.dragSrc = this.dragTarget = null;
        },
        qDragStart(event, qIdx) {
            this.qDragSrc = qIdx;
            event.dataTransfer.effectAllowed = 'move';
        },
        qDrop(qIdx) {
            if (this.qDragSrc === null || this.qDragSrc === qIdx) { this.qDragSrc = this.qDragTarget = null; return; }
            const from = this.qDragSrc;
            const [moved] = this.pomodoroQueue.splice(from, 1);
            this.pomodoroQueue.splice(qIdx, 0, moved);
            // Keep currentPomoIndex pointing at the same task after reorder
            if (this.currentPomoIndex === from)                          this.currentPomoIndex = qIdx;
            else if (from < qIdx && this.currentPomoIndex > from && this.currentPomoIndex <= qIdx) this.currentPomoIndex--;
            else if (from > qIdx && this.currentPomoIndex < from && this.currentPomoIndex >= qIdx) this.currentPomoIndex++;
            this.qDragSrc = this.qDragTarget = null;
        },
        qDragEnd() { this.qDragSrc = this.qDragTarget = null; },

        // ── Pomodoro ──────────────────────────────
        addToQueue(item) {
            if (this.pomodoroQueue.includes(item.id)) return;
            this.pomodoroQueue.push(item.id);
            this.pomodoroOpen = true;
        },
        removeFromQueue(qIdx) {
            this.pomodoroQueue.splice(qIdx, 1);
            if (this.currentPomoIndex >= this.pomodoroQueue.length)
                this.currentPomoIndex = Math.max(0, this.pomodoroQueue.length - 1);
        },
        markDoneInQueue(taskId) {
            const item = this.getTaskById(taskId);
            if (item) this.toggleComplete(item);
        },
        getTaskById(id) { return this.items.find(i => i.id === id) ?? null; },
        togglePomo() {
            if (this.pomoRunning) {
                clearInterval(this.pomoInterval); this.pomoInterval = null; this.pomoRunning = false;
            } else {
                this.pomoRunning = true;
                this.pomoInterval = setInterval(() => this.tick(), 1000);
            }
        },
        tick() { if (this.pomoTimeLeft > 0) { this.pomoTimeLeft--; } else { this.advancePhase(); } },
        advancePhase() {
            clearInterval(this.pomoInterval); this.pomoInterval = null; this.pomoRunning = false;
            if (this.pomoPhase === 'work') {
                this.playChime('work');
                this.pomoSessionsDone++;
                const task = this.getTaskById(this.pomodoroQueue[this.currentPomoIndex]);
                if (task) {
                    task.completed_pomodoros = Math.min(task.estimated_pomodoros, (task.completed_pomodoros||0) + 1);
                    if (this.token && !String(task.id).startsWith('tmp-'))
                        this.api('PUT', `/fp-api/session/${this.token}/tasks/${task.id}`, { completed_pomodoros: task.completed_pomodoros }).catch(() => {});
                }
                const isLongBreak = this.pomoSessionsDone % this.settings.sessionsUntilLongBreak === 0;
                this.pomoPhase    = isLongBreak ? 'long_break' : 'short_break';
                this.pomoTimeLeft = isLongBreak ? this.settings.longBreak * 60 : this.settings.shortBreak * 60;
            } else {
                this.playChime('break');
                if (this.currentPomoIndex < this.pomodoroQueue.length - 1) this.currentPomoIndex++;
                this.pomoPhase    = 'work';
                this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            }
        },
        skipPhase() { this.advancePhase(); },
        resetPomo() {
            clearInterval(this.pomoInterval);
            this.pomoInterval = null; this.pomoRunning = false; this.pomoPhase = 'work';
            this.pomoSessionsDone = 0; this.currentPomoIndex = 0;
            this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
        },

        // ── Sound ─────────────────────────────────
        playChime(type = 'work') {
            try {
                const ctx   = new (window.AudioContext || window.webkitAudioContext)();
                const notes = type === 'work'
                    ? [{ f: 523.25, t: 0 }, { f: 659.25, t: 0.15 }, { f: 783.99, t: 0.30 }]
                    : [{ f: 659.25, t: 0 }, { f: 523.25, t: 0.20 }];
                for (const { f, t } of notes) {
                    const osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine'; osc.frequency.value = f;
                    const s = ctx.currentTime + t;
                    gain.gain.setValueAtTime(0, s);
                    gain.gain.linearRampToValueAtTime(0.25, s + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.001, s + 0.5);
                    osc.start(s); osc.stop(s + 0.55);
                }
            } catch (e) {}
        },

        // ── Settings ──────────────────────────────
        async saveSettings() {
            if (!this.pomoRunning) this.pomoTimeLeft = this.settings.pomodoroDuration * 60;
            if (this.token) {
                this.saving = true;
                try { await this.api('PUT', `/fp-api/session/${this.token}/settings`, this.settings); }
                catch (e) {}
                this.saving = false;
            }
            this.showSettings = false;
        },

        // ── Helpers ───────────────────────────────
        formatTime(seconds) {
            return `${Math.floor(seconds/60).toString().padStart(2,'0')}:${(seconds%60).toString().padStart(2,'0')}`;
        },
        async api(method, url, body = null) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const options = {
                method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            };
            if (body && method !== 'GET') options.body = JSON.stringify(body);
            const response = await fetch(url, options);
            if (!response.ok) { const err = await response.json().catch(() => ({})); throw new Error(err.message ?? `HTTP ${response.status}`); }
            return response.json();
        },
    };
}
</script>
@endpush
