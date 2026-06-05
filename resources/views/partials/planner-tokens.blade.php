<style>
    :root {
        /* Priority colors */
        --planner-priority-high: #ef4444;
        --planner-priority-normal: #6366f1;
        --planner-priority-low: #94a3b8;

        /* Status colors */
        --planner-status-backlog: #94a3b8;
        --planner-status-active: #6366f1;
        --planner-status-done: #22c55e;
        --planner-status-overdue: #ef4444;

        /* Frog */
        --planner-frog: #22c55e;

        /* Card backgrounds */
        --planner-card-idle: var(--ui-surface);
        --planner-card-hover: #fafbfc;
        --planner-card-done: #f0fdf4;
        --planner-card-overdue: #fef2f2;
        --planner-card-frog: #f0fdf4;

        /* Progress track */
        --planner-track: #e2e8f0;
        --planner-track-fill: #6366f1;

        /* Column header badges */
        --planner-col-backlog: #94a3b8;
        --planner-col-default: #6366f1;
        --planner-col-done: #22c55e;

        /* MeisterTask-Style Tokens */
        --planner-canvas-bg: linear-gradient(135deg, #f6f4ef 0%, #eef0f5 100%);
        --planner-column-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.04);
        --planner-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        --planner-card-shadow-hover: 0 10px 28px rgba(15, 23, 42, 0.10), 0 2px 6px rgba(15, 23, 42, 0.04);

        /* === MeisterTask Section-Tones (Spalten-Akzentfarben) === */
        --tone-rose:   #ef4444;
        --tone-amber:  #f59e0b;
        --tone-emerald:#10b981;
        --tone-teal:   #06b6d4;
        --tone-sky:    #0ea5e9;
        --tone-indigo: #6366f1;
        --tone-violet: #8b5cf6;
        --tone-pink:   #ec4899;
        --tone-slate:  #94a3b8;
    }

    /* ═══════════════════════════════════════════════════════════
       MeisterTask-Style Polish — scoped to .planner-board-canvas
       ═══════════════════════════════════════════════════════════ */

    .planner-board-canvas {
        background: var(--planner-canvas-bg);
        background-image:
            radial-gradient(rgba(99, 102, 241, 0.04) 1px, transparent 1px),
            var(--planner-canvas-bg);
        background-size: 28px 28px, auto;
        background-position: 0 0, 0 0;
    }
    /* Optional Project-Color-Tint, injected via inline --planner-project-color */
    .planner-board-canvas[style*="--planner-project-color"] {
        background-image:
            radial-gradient(rgba(99, 102, 241, 0.04) 1px, transparent 1px),
            linear-gradient(135deg,
                color-mix(in srgb, var(--planner-project-color) 6%, #f6f4ef),
                color-mix(in srgb, var(--planner-project-color) 4%, #eef0f5));
    }

    /* Columns: weicher Schatten, runder */
    .planner-board-canvas .kanban-column > div {
        border-radius: 12px !important;
        border-color: transparent !important;
        box-shadow: var(--planner-column-shadow);
        background-color: #ffffff !important;
        overflow: hidden;
    }

    /* Column header neutral base — Akzentband kommt über tone-* Klassen oben drauf */
    .planner-board-canvas .kanban-column > div > div:first-child {
        background-color: rgba(248, 250, 252, 0.6) !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        position: relative;
    }

    /* MeisterTask-Signatur: farbiges Band oben auf jeder Spalte */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 6px;
        background-color: var(--col-tone, var(--planner-status-active));
    }
    /* Header mit mehr Tone-Hauch + Padding-Top für das Band */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child {
        background: linear-gradient(180deg,
            color-mix(in srgb, var(--col-tone, var(--planner-status-active)) 14%, white),
            #ffffff) !important;
        padding-top: 0.875rem !important;
        padding-bottom: 0.625rem !important;
    }
    /* Spalten-Title im Header etwas größer + bold */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child > span:first-child {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    /* Tone-Variable Mappings */
    .planner-board-canvas .col-tone-rose    > div > div:first-child { --col-tone: var(--tone-rose); }
    .planner-board-canvas .col-tone-amber   > div > div:first-child { --col-tone: var(--tone-amber); }
    .planner-board-canvas .col-tone-emerald > div > div:first-child { --col-tone: var(--tone-emerald); }
    .planner-board-canvas .col-tone-teal    > div > div:first-child { --col-tone: var(--tone-teal); }
    .planner-board-canvas .col-tone-sky     > div > div:first-child { --col-tone: var(--tone-sky); }
    .planner-board-canvas .col-tone-indigo  > div > div:first-child { --col-tone: var(--tone-indigo); }
    .planner-board-canvas .col-tone-violet  > div > div:first-child { --col-tone: var(--tone-violet); }
    .planner-board-canvas .col-tone-pink    > div > div:first-child { --col-tone: var(--tone-pink); }
    .planner-board-canvas .col-tone-slate   > div > div:first-child { --col-tone: var(--tone-slate); }

    /* Cards: rounded, soft shadow, plain white, mehr Atem */
    .planner-board-canvas .kanban-card {
        border-radius: 10px !important;
        background-color: #ffffff !important;
        box-shadow: var(--planner-card-shadow);
        border: 1px solid rgba(226, 232, 240, 0.45);
        transition: box-shadow 180ms ease, transform 180ms ease, border-color 180ms ease;
        position: relative;
        overflow: hidden;
        padding: 0.875rem 0.875rem !important;
        margin: 0.5rem 0.5rem !important;
    }
    .planner-board-canvas .kanban-card:hover {
        box-shadow: var(--planner-card-shadow-hover);
        transform: translateY(-2px);
        border-color: rgba(99, 102, 241, 0.30);
    }
    .planner-board-canvas .kanban-card.wire-dragging {
        box-shadow: var(--planner-card-shadow-hover);
        transform: rotate(1.5deg);
    }

    /* Board-Inneres: mehr Gap zwischen Spalten, mehr Padding am Canvas */
    .planner-board-canvas .kanban-column + .kanban-column,
    .planner-board-canvas > div > div > .kanban-column:not(:first-child) {
        margin-left: 0.5rem;
    }
    /* Innen-Container der Kanban-Spaltenwiege bekommt zusätzliche Atemluft */
    .planner-board-canvas > div > [x-show="view === 'board'"] {
        padding: 1.25rem !important;
        gap: 1.25rem !important;
    }

    /* Done-Strip (rechts) Polish */
    .planner-done-strip {
        border-radius: 14px !important;
        border: 1px solid rgba(34, 197, 94, 0.15) !important;
        box-shadow:
            0 2px 4px rgba(34, 197, 94, 0.06),
            -12px 0 24px -12px rgba(15, 23, 42, 0.18) !important;
    }
</style>
