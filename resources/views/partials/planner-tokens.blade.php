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

    /* Neutraler weißer Background — Frame der Page bestimmt den Ton */
    .planner-board-canvas {
        background: #ffffff;
    }

    /* Spalten-Wrapper komplett ohne Fläche */
    .planner-board-canvas .kanban-column,
    .planner-board-canvas .kanban-column > div,
    .planner-board-canvas .kanban-column > div > div {
        background-color: transparent !important;
        background: transparent !important;
    }
    .planner-board-canvas .kanban-column > div {
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        overflow: visible !important;
    }

    /* Column header: nur das Tone-Band + sanfter Glow, kein Surface */
    .planner-board-canvas .kanban-column > div > div:first-child {
        border-bottom: none !important;
        position: relative;
        padding-top: 1rem !important;
        padding-bottom: 0.625rem !important;
    }

    /* MeisterTask-Signatur: Pille-förmiges Tone-Band oben */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child::before {
        content: "";
        position: absolute;
        top: 0.25rem; left: 0.5rem; right: 0.5rem;
        height: 4px;
        border-radius: 999px;
        background-color: var(--col-tone, var(--planner-status-active));
        opacity: 0.95;
    }
    /* Sanfter Tone-Glow hinter dem Header (statt fester Tint-Fläche) */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child::after {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; height: 100%;
        background: radial-gradient(ellipse at top,
            color-mix(in srgb, var(--col-tone, var(--planner-status-active)) 22%, transparent) 0%,
            transparent 70%);
        pointer-events: none;
        z-index: -1;
    }
    /* Spalten-Title in dunklem Tone-Mix, gut lesbar auf Weiß */
    .planner-board-canvas .kanban-column[class*="col-tone-"] > div > div:first-child > span:first-child {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--col-tone, var(--planner-status-active)) 70%, #1e293b) !important;
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

    /* Cards: weicher Schatten, klarer Rand auf hellem Background */
    .planner-board-canvas .kanban-card {
        border-radius: 12px !important;
        background-color: #ffffff !important;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.05),
            0 4px 12px rgba(15, 23, 42, 0.06) !important;
        border: 1px solid rgba(226, 232, 240, 0.7) !important;
        transition: box-shadow 200ms ease, transform 200ms ease, border-color 200ms ease;
        position: relative;
        overflow: hidden;
        padding: 0.875rem 0.875rem !important;
        margin: 0.625rem 0.5rem !important;
    }
    .planner-board-canvas .kanban-card:hover {
        box-shadow:
            0 2px 4px rgba(15, 23, 42, 0.06),
            0 10px 26px rgba(15, 23, 42, 0.10) !important;
        transform: translateY(-2px);
        border-color: rgba(99, 102, 241, 0.30) !important;
    }
    .planner-board-canvas .kanban-card.wire-dragging {
        transform: rotate(1.5deg);
    }

    /* Board-Inneres: deutlich mehr Gap + Canvas-Padding */
    .planner-board-canvas > div > [x-show="view === 'board'"] {
        padding: 1.75rem !important;
        gap: 1.5rem !important;
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
