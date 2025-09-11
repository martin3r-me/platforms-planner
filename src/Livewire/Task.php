<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Printing\Contracts\PrintingServiceInterface;


class Task extends Component
{
	public $task;
    public $printModalShow = false;
    public $printTarget = 'printer'; // 'printer' oder 'group'
    public $selectedPrinterId = null;
    public $selectedPrinterGroupId = null;

	protected $rules = [
        'task.title' => 'required|string|max:255',
        'task.description' => 'nullable|string',
        'task.is_frog' => 'boolean',
        'task.is_done' => 'boolean',
        'task.due_date' => 'nullable|date',
        'task.user_in_charge_id' => 'nullable|integer',
        'task.priority' => 'required|in:low,normal,high',
        'task.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'task.project_id' => 'nullable|integer',
    ];

    public function mount(PlannerTask $plannerTask)
    {
        $this->authorize('view', $plannerTask);
        $this->task = $plannerTask;
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->task),                                // z. B. 'Platform\Planner\Models\PlannerTask'
            'modelId' => $this->task->id,
            'subject' => $this->task->title,
            'description' => $this->task->description ?? '',
            'url' => route('planner.tasks.show', $this->task),                // absolute URL zum Task
            'source' => 'planner.task.view',                                 // eindeutiger Quell-Identifier (frei wählbar)
            'recipients' => [$this->task->user_in_charge_id],                // falls vorhanden, sonst leer
            'meta' => [
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date,
                'story_points' => $this->task->story_points,
            ],
        ]);
    }

    public function updatedTask($property, $value)
    {
        $this->validateOnly("task.$property");
        $this->task->save();
    }

    public function deleteTask()
    {
        $this->task->delete();
        return $this->redirect('/', navigate: true);
    }

    public function deleteTaskAndReturnToDashboard()
    {
        $this->authorize('delete', $this->task);
        $this->task->delete();
        return $this->redirect(route('planner.my-tasks'), navigate: true);
    }

    public function deleteTaskAndReturnToProject()
    {
        $this->authorize('delete', $this->task);
        
        if (!$this->task->project) {
            // Fallback zu MyTasks wenn kein Projekt vorhanden
            $this->task->delete();
            return $this->redirect(route('planner.my-tasks'), navigate: true);
        }
        
        $this->task->delete();
        return $this->redirect(route('planner.projects.show', $this->task->project), navigate: true);
    }

    public function printTask()
    {
        $this->printModalShow = true;
    }

    public function closePrintModal()
    {
        $this->printModalShow = false;
        $this->resetPrintSelection();
    }

    public function updatedPrintTarget()
    {
        // Reset Auswahl wenn Typ gewechselt wird
        $this->resetPrintSelection();
    }

    private function resetPrintSelection()
    {
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;
    }

    public function printTaskConfirm()
    {
        if (!$this->selectedPrinterId && !$this->selectedPrinterGroupId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Bitte wählen Sie einen Drucker oder eine Gruppe',
            ]);
            return;
        }

        /** @var PrintingServiceInterface $printing */
        $printing = app(PrintingServiceInterface::class);

        $printing->createJob(
            printable: $this->task,
            // template: null, // Automatische Template-Auswahl: planner-task
            data: [
                'requested_by' => Auth::user()?->name,
            ],
            printerId: $this->selectedPrinterId ? (int) $this->selectedPrinterId : null,
            printerGroupId: $this->selectedPrinterGroupId ? (int) $this->selectedPrinterGroupId : null,
        );

        $this->closePrintModal();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Druckauftrag wurde erstellt',
        ]);
    }

	public function render()
    {        
        /** @var PrintingServiceInterface $printing */
        $printing = app(PrintingServiceInterface::class);

        $printers = $printing->listPrinters();
        $groups   = $printing->listPrinterGroups();

        return view('planner::livewire.task', [
            'printers' => $printers,
            'printerGroups' => $groups,
        ])->layout('platform::layouts.app');
    }
}
