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
        --planner-card-hover: #f8fafc;
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
        --planner-canvas-bg: linear-gradient(135deg, #f5f7fb 0%, #eef2f9 100%);
        --planner-column-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 2px 8px rgba(15, 23, 42, 0.03);
        --planner-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        --planner-card-shadow-hover: 0 8px 24px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
        --planner-dashboard-bg: linear-gradient(180deg, #eef2ff 0%, #f5f7ff 50%, #ffffff 100%);
        --planner-dashboard-accent: linear-gradient(90deg, #6366f1, #818cf8, #a5b4fc);
    }

    /* ═══════════════════════════════════════════════════════════
       MeisterTask-Style Polish — scoped to .planner-board-canvas
       so nichts in anderen Modulen ungewollt verändert wird
       ═══════════════════════════════════════════════════════════ */

    .planner-board-canvas {
        background: var(--planner-canvas-bg);
        background-image:
            radial-gradient(rgba(99, 102, 241, 0.05) 1px, transparent 1px),
            var(--planner-canvas-bg);
        background-size: 28px 28px, auto;
        background-position: 0 0, 0 0;
    }

    /* Columns: weicher Schatten statt harter Border, runder */
    .planner-board-canvas .kanban-column > div {
        border-radius: 12px !important;
        border-color: transparent !important;
        box-shadow: var(--planner-column-shadow);
        background-color: #ffffff !important;
        overflow: hidden;
    }

    /* Column header subtle base */
    .planner-board-canvas .kanban-column > div > div:first-child {
        background-color: rgba(248, 250, 252, 0.6) !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    }

    /* Cards: rounded, soft shadow, plain white surface (status-Farben in
       task-preview-card überschreiben das via !bg- Klassen) */
    .planner-board-canvas .kanban-card {
        border-radius: 10px !important;
        background-color: #ffffff !important;
        box-shadow: var(--planner-card-shadow);
        border: 1px solid rgba(226, 232, 240, 0.45);
        transition: box-shadow 180ms ease, transform 180ms ease, border-color 180ms ease;
    }
    .planner-board-canvas .kanban-card:hover {
        box-shadow: var(--planner-card-shadow-hover);
        transform: translateY(-1px);
        border-color: rgba(99, 102, 241, 0.25);
    }
    /* Card-Status-Backgrounds aus task-preview-card haben Vorrang */
    .planner-board-canvas .kanban-card.wire-dragging {
        box-shadow: var(--planner-card-shadow-hover);
        transform: rotate(1.5deg);
    }

    /* Dashboard-Column distinct identity */
    .planner-dashboard-column {
        background: var(--planner-dashboard-bg) !important;
        border-radius: 14px !important;
        border: 1px solid rgba(99, 102, 241, 0.15) !important;
        box-shadow:
            0 2px 4px rgba(99, 102, 241, 0.06),
            0 12px 28px rgba(99, 102, 241, 0.08),
            12px 0 24px -12px rgba(15, 23, 42, 0.18) !important;
        position: relative;
    }
    .planner-dashboard-column::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--planner-dashboard-accent);
        border-radius: 14px 14px 0 0;
    }

    /* Dashboard collapsed strip — gleiches Akzent-Token */
    .planner-dashboard-strip {
        background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%) !important;
        border: 1px solid rgba(99, 102, 241, 0.15) !important;
        border-radius: 14px !important;
        box-shadow:
            0 2px 4px rgba(99, 102, 241, 0.06),
            12px 0 24px -12px rgba(15, 23, 42, 0.18) !important;
        position: relative;
    }
    .planner-dashboard-strip::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--planner-dashboard-accent);
        border-radius: 14px 14px 0 0;
    }

    /* Done-Strip (rechts) bekommt analog einen leichten Polish */
    .planner-done-strip {
        border-radius: 14px !important;
        border: 1px solid rgba(34, 197, 94, 0.15) !important;
        box-shadow:
            0 2px 4px rgba(34, 197, 94, 0.06),
            -12px 0 24px -12px rgba(15, 23, 42, 0.18) !important;
    }
</style>
