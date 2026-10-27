<?php

namespace Platform\Planner\Livewire\ProjectCanvas;

use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Services\ProjectCanvasVersioning;

/**
 * Versionsleiste des Projekt-Canvas: Kickoff, "Stand festhalten" (optional mit
 * Logbuch-Eintrag), Versionsverlauf mit Unterschieden und Wiederherstellen.
 * Versionen entstehen automatisch je Bearbeitungssitzung — hier wird nur kuratiert.
 */
class Versions extends Component
{
    public int $canvasId;

    /** Seitenpanel mit dem Versionsverlauf */
    public bool $panelOpen = false;

    /** Ausgewaehlte Version (Unterschiede zum aktuellen Stand) */
    public ?int $selectedId = null;

    /** Version, deren Wiederherstellung bestaetigt werden soll */
    public ?int $confirmRestoreId = null;

    public bool $captureOpen = false;

    public array $capture = [];

    public bool $kickoffOpen = false;

    public string $kickoffNote = '';

    public function mount(int $canvasId): void
    {
        $this->canvasId = $canvasId;
        $this->authorize('view', $this->canvas()->project);
        $this->resetCapture();
    }

    protected function canvas(): PlannerProjectCanvas
    {
        return PlannerProjectCanvas::with('project')->findOrFail($this->canvasId);
    }

    protected function snapshot(int $id): PlannerProjectCanvasSnapshot
    {
        return PlannerProjectCanvasSnapshot::where('canvas_id', $this->canvasId)->findOrFail($id);
    }

    protected function resetCapture(): void
    {
        $this->capture = ['label' => '', 'log' => false, 'type' => LogbookEntryType::DECISION->value, 'title' => '', 'rationale' => ''];
    }

    public function togglePanel(): void
    {
        $this->panelOpen = ! $this->panelOpen;
        $this->confirmRestoreId = null;
    }

    public function select(?int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
        $this->confirmRestoreId = null;
    }

    public function saveCapture(ProjectCanvasVersioning $versioning): void
    {
        $canvas = $this->canvas();
        $this->authorize('update', $canvas->project);
        $this->resetErrorBag();

        $logbook = null;
        if ($this->capture['log']) {
            if (trim((string) $this->capture['title']) === '') {
                $this->addError('capture', 'Für den Logbuch-Eintrag fehlt der Titel.');

                return;
            }
            $logbook = ['type' => $this->capture['type'], 'title' => $this->capture['title'], 'rationale' => $this->capture['rationale']];
        }

        try {
            $result = $versioning->capture($canvas, Auth::user(), trim((string) $this->capture['label']) ?: null, $logbook);
        } catch (InvalidArgumentException $e) {
            $this->addError('capture', $e->getMessage());

            return;
        }

        $this->captureOpen = false;
        $this->resetCapture();
        $this->notify('Stand festgehalten', 'Version v' . $result['snapshot']->version . ($result['entry'] ? ' · im Logbuch verknüpft' : '') . '.');
    }

    public function askRestore(int $id): void
    {
        $this->authorize('update', $this->canvas()->project);
        $this->confirmRestoreId = $id;
    }

    public function restore(ProjectCanvasVersioning $versioning): void
    {
        if (! $this->confirmRestoreId) {
            return;
        }
        $snapshot = $this->snapshot($this->confirmRestoreId);
        $this->authorize('update', $snapshot->canvas->project);

        $new = $versioning->restore($snapshot, Auth::id());

        $this->confirmRestoreId = null;
        $this->selectedId = null;
        $this->dispatch('canvas-version-restored', canvasId: $this->canvasId);
        $this->notify('Stand wiederhergestellt', "v{$snapshot->version} ist wieder aktuell (als v{$new->version}). Der vorherige Stand bleibt als Version erhalten.");
    }

    public function completeKickoff(ProjectCanvasVersioning $versioning): void
    {
        $canvas = $this->canvas();
        $this->authorize('update', $canvas->project);
        $this->resetErrorBag();

        try {
            $versioning->completeKickoff($canvas, Auth::user(), trim($this->kickoffNote) ?: null);
        } catch (InvalidArgumentException $e) {
            $this->addError('kickoff', $e->getMessage());

            return;
        }

        $this->kickoffOpen = false;
        $this->kickoffNote = '';
        $this->notify('Kickoff abgeschlossen', 'Der Kickoff-Stand ist gesichert und als Meilenstein im Logbuch festgehalten.');
    }

    #[On('canvas-version-restored')]
    public function refreshAfterRestore(): void
    {
        // re-render
    }

    protected function notify(string $title, string $message): void
    {
        $this->dispatch('notifications:store', [
            'title' => $title,
            'message' => $message,
            'notice_type' => 'success',
            'noticable_type' => PlannerProjectCanvas::class,
            'noticable_id' => $this->canvasId,
        ]);
    }

    public function render(ProjectCanvasVersioning $versioning)
    {
        $canvas = $this->canvas();
        $user = Auth::user();

        $snapshots = $canvas->snapshots()
            ->with('createdByUser:id,name')
            ->orderByDesc('version')
            ->limit(100)
            ->get(['id', 'canvas_id', 'version', 'source', 'label', 'created_by_user_id', 'created_at']);

        $latest = $snapshots->first();
        $kickoffSnapshot = $snapshots->firstWhere('source', ProjectCanvasVersioning::SOURCE_KICKOFF);

        $selected = null;
        $diff = null;
        if ($this->selectedId && ($selected = $snapshots->firstWhere('id', $this->selectedId))) {
            $diff = $versioning->diffToCurrent($this->snapshot($selected->id));
        }

        // Was hat sich seit dem Kickoff getan? (fuer die Leiste)
        $sinceKickoff = $kickoffSnapshot ? $versioning->diffToCurrent($this->snapshot($kickoffSnapshot->id))['summary'] : null;

        return view('planner::livewire.project-canvas.versions', [
            'canvas' => $canvas,
            'snapshots' => $snapshots,
            'latest' => $latest,
            'kickoffSnapshot' => $kickoffSnapshot,
            'sinceKickoff' => $sinceKickoff,
            'selected' => $selected,
            'diff' => $diff,
            'canUpdate' => $user?->can('update', $canvas->project) ?? false,
            'types' => LogbookEntryType::cases(),
            'sourceLabels' => ProjectCanvasVersioning::SOURCE_LABELS,
            'sessionGap' => ProjectCanvasVersioning::SESSION_GAP_MINUTES,
        ]);
    }
}
