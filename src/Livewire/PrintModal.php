<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;

class PrintModal extends Component
{
    public $modalShow = false;
    public $printTarget = 'printer';
    public $selectedPrinterId = null;
    public $selectedPrinterGroupId = null;
    public $printers;
    public $printerGroups;

    protected $listeners = ['openPrintModal'];

    public function mount()
    {
        $this->printers = collect();
        $this->printerGroups = collect();
    }

    public function openPrintModal()
    {
        $this->modalShow = true;
        // Hier könntest du die Drucker und Gruppen laden
        $this->printers = collect(); // Placeholder
        $this->printerGroups = collect(); // Placeholder
    }

    public function closePrintModal()
    {
        $this->modalShow = false;
        $this->reset(['printTarget', 'selectedPrinterId', 'selectedPrinterGroupId']);
    }

    public function printTaskConfirm()
    {
        // Hier die Druck-Logik implementieren
        $this->dispatch('task-printed', [
            'target' => $this->printTarget,
            'printerId' => $this->selectedPrinterId,
            'groupId' => $this->selectedPrinterGroupId
        ]);
        
        $this->closePrintModal();
    }

    public function render()
    {
        return view('planner::livewire.print-modal');
    }
}
