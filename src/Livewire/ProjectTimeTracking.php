<?php

namespace Platform\Planner\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTimeEntry;

class ProjectTimeTracking extends Component
{
    use AuthorizesRequests;

    public PlannerProject $project;

    public string $workDate;

    public int $minutes = 60;

    public ?string $rate = null;

    public ?string $note = null;

    protected array $minuteOptions = [15, 30, 45, 60, 90, 120, 180, 240, 300, 360, 420, 480];

    public function mount(PlannerProject $project): void
    {
        $this->project = $project;
        $this->workDate = now()->toDateString();

        $defaultRate = $this->resolveDefaultRate();
        if ($defaultRate !== null) {
            $this->rate = number_format($defaultRate / 100, 2, ',', '');
        }
    }

    #[Computed]
    public function entries()
    {
        return $this->project
            ->timeEntries()
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function totalMinutes(): int
    {
        return (int) $this->entries->sum('minutes');
    }

    #[Computed]
    public function totalAmountCents(): int
    {
        return (int) $this->entries->sum(fn ($entry) => $entry->amount_cents ?? 0);
    }

    public function getMinuteOptionsProperty(): array
    {
        return $this->minuteOptions;
    }

    protected function rules(): array
    {
        return [
            'workDate' => ['required', 'date'],
            'minutes' => ['required', 'integer', Rule::in($this->minuteOptions)],
            'rate' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function updatedMinutes($value): void
    {
        $this->minutes = (int) $value;
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);
        $this->validate();

        $rateCents = $this->rateToCents($this->rate);
        if ($this->rate && $rateCents === null) {
            $this->addError('rate', 'Bitte einen gültigen Betrag eingeben.');
            return;
        }
        if ($rateCents === null) {
            $rateCents = $this->resolveDefaultRate();
        }

        $minutes = max(1, (int) $this->minutes);
        $amountCents = $rateCents !== null
            ? (int) round($rateCents * ($minutes / 60))
            : null;

        PlannerTimeEntry::create([
            'team_id' => $this->project->team_id,
            'user_id' => Auth::id(),
            'project_id' => $this->project->id,
            'task_id' => null,
            'work_date' => $this->workDate,
            'minutes' => $minutes,
            'rate_cents' => $rateCents,
            'amount_cents' => $amountCents,
            'currency_code' => 'EUR',
            'note' => $this->note,
        ]);

        $this->project->refresh();

        $this->note = null;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Arbeitszeit gespeichert',
        ]);
    }

    protected function resolveDefaultRate(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $rate = $user->hourly_rate_cents ?? null;

        return $rate !== null ? (int) $rate : null;
    }

    protected function rateToCents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace([' ', "'"], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;
        if ($float <= 0) {
            return null;
        }

        return (int) round($float * 100);
    }

    public function render()
    {
        return view('planner::livewire.project-time-tracking');
    }
}


