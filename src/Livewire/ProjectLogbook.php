<?php

namespace Platform\Planner\Livewire;

use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Services\ProjectLogbookService;

/**
 * Projekt-Logbuch: chronologischer, append-only Verlauf (Entscheidung/Meilenstein/Risiko).
 * Kein Bearbeiten/Loeschen — Korrekturen sind neue Eintraege, die den alten ersetzen.
 */
class ProjectLogbook extends Component
{
    public PlannerProject $project;

    public string $typeFilter = '';

    public bool $showSuperseded = false;

    public bool $formOpen = false;

    public array $form = [];

    /** Entwurf aus der Faktenlage (planner.project.verbalize-Pipeline) */
    public ?array $draft = null;

    public function mount(PlannerProject $plannerProject): void
    {
        $this->authorize('view', $plannerProject);
        $this->project = $plannerProject;
        $this->resetForm();

        // Einstieg aus Board/Sidebar: ?neu=decision|milestone|risk oeffnet das Formular,
        // ?entwurf=1 erzeugt direkt einen Entwurf aus der Faktenlage.
        if (! auth()->user()->can('update', $plannerProject)) {
            return;
        }
        if (request()->boolean('entwurf')) {
            $this->generateDraft(app(ProjectLogbookService::class));
        } elseif ($neu = request()->query('neu')) {
            $this->openForm();
            if ($type = LogbookEntryType::tryFrom((string) $neu)) {
                $this->form['type'] = $type->value;
            }
        }
    }

    public function openForm(?int $supersedesId = null): void
    {
        $this->authorize('update', $this->project);
        $this->resetForm();

        if ($supersedesId) {
            $old = PlannerProjectLogbookEntry::forProject($this->project->id)->findOrFail($supersedesId);
            $this->form = [
                ...$this->form,
                'type' => $old->type->value,
                'title' => $old->title,
                'rationale' => (string) $old->rationale,
                'occurred_on' => $old->occurred_on->toDateString(),
                'refs' => $old->refs ?? [],
                'supersedes_entry_id' => $old->id,
            ];
        }

        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->draft = null;
        $this->resetForm();
    }

    public function generateDraft(ProjectLogbookService $service): void
    {
        $this->authorize('update', $this->project);

        $this->draft = $service->draft($this->project);
        $this->form['source'] = 'draft';

        // Verweise als Vorschlag uebernehmen — der Mensch streicht, was nicht passt.
        $known = collect($this->form['refs'])->map(fn ($r) => $r['type'] . ':' . $r['ref'])->all();
        foreach ($this->draft['suggested_refs'] as $ref) {
            if (! in_array($ref['type'] . ':' . $ref['ref'], $known, true)) {
                $this->form['refs'][] = $ref;
            }
        }
        $this->formOpen = true;
    }

    public function addRef(): void
    {
        $type = $this->form['new_ref_type'] ?? 'commit';
        $ref = trim((string) ($this->form['new_ref'] ?? ''));
        if ($ref === '') {
            return;
        }
        $this->form['refs'][] = ['type' => $type, 'ref' => $ref, 'label' => null];
        $this->form['new_ref'] = '';
    }

    public function removeRef(int $index): void
    {
        unset($this->form['refs'][$index]);
        $this->form['refs'] = array_values($this->form['refs']);
    }

    public function save(ProjectLogbookService $service): void
    {
        $this->authorize('update', $this->project);
        $this->resetErrorBag();

        try {
            $service->append($this->project, auth()->user(), $this->form);
        } catch (InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->closeForm();
        $this->dispatch('notifications:store', [
            'title' => 'Im Logbuch festgehalten',
            'message' => 'Der Eintrag ist angehängt.',
            'notice_type' => 'success',
            'noticable_type' => PlannerProject::class,
            'noticable_id' => $this->project->id,
        ]);
    }

    protected function resetForm(): void
    {
        $this->resetErrorBag();
        $this->form = [
            'type' => LogbookEntryType::DECISION->value,
            'title' => '',
            'rationale' => '',
            'occurred_on' => now()->toDateString(),
            'refs' => [],
            'supersedes_entry_id' => null,
            'source' => 'manual',
            'new_ref_type' => 'commit',
            'new_ref' => '',
        ];
    }

    public function refUrl(array $ref): ?string
    {
        return match ($ref['type'] ?? null) {
            'task' => route('planner.tasks.show', (int) $ref['ref']),
            'canvas' => route('planner.projects.canvas.show', [$this->project, (int) $ref['ref']]),
            'url' => $ref['ref'],
            default => null,
        };
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $query = PlannerProjectLogbookEntry::with(['createdBy:id,name', 'supersededBy:id,supersedes_entry_id'])
            ->forProject($this->project->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        if ($this->typeFilter !== '' && LogbookEntryType::tryFrom($this->typeFilter)) {
            $query->where('type', $this->typeFilter);
        }
        if (! $this->showSuperseded) {
            $query->current();
        }

        $entries = $query->get();

        $counts = PlannerProjectLogbookEntry::forProject($this->project->id)
            ->current()
            ->selectRaw('type, count(*) as n')
            ->groupBy('type')
            ->pluck('n', 'type');

        return view('planner::livewire.project-logbook', [
            'entries' => $entries,
            'grouped' => $entries->groupBy(fn ($e) => $e->occurred_on->format('Y-m')),
            'counts' => $counts,
            'types' => LogbookEntryType::cases(),
            'canUpdate' => auth()->user()->can('update', $this->project),
            'lastEntryAt' => PlannerProjectLogbookEntry::forProject($this->project->id)->max('created_at'),
        ]);
    }
}
