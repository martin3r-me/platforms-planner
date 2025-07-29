<div class="h-full">
    <x-ui-kanban-board wire:sortable="updateTaskGroupOrder" wire:sortable-group="updateTaskOrder">
    	<x-ui-kanban-column
                :title="'INBOX'"
        >

                <x-slot name="extra">
                	<div class = "d-flex gap-1">
    			        <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTask()">+ Neue Aufgabe</x-ui-button>
    			        <x-ui-button variant="primary-outline" size="sm" class="w-full" wire:click="createTaskGroup">+ Neue Spalte</x-ui-button>
    			    </div>
    		    </x-slot>

    		 	@foreach ($groups->first()->tasks as $task)
    		 	    <livewire:planner.task-preview-card 
                        :task="$task"
                        wire:key="task-preview-{{ $task->uuid }}"
                    />
    		 	@endforeach

        </x-ui-kanban-column>

        @foreach($groups->filter(fn ($g) => !($g->isInbox || ($g->isDoneGroup ?? false))) as $column)
            <x-ui-kanban-column
                :title="$column->label"
                :sortable-id="$column->id"
            >

                <x-slot name="extra">
                    <div class = "d-flex gap-1">
        		        <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTask('{{$column->id}}')">+ Neue Aufgabe</x-ui-button>
                        <x-ui-button variant="primary-outline" size="sm" class="w-full" @click="$dispatch('open-modal-task-group-settings', { taskGroupId: {{ $column->id }} })">Settings</x-ui-button>
                    </div>
    		    </x-slot>

                @foreach($column->tasks as $task)
                
                    <livewire:planner.task-preview-card 
                        :task="$task"
                        wire:key="task-preview-{{ $task->uuid }}"
                    />

                @endforeach

            </x-ui-kanban-column>
        @endforeach

        <!-- Erledigt -->
        @php $completedGroup = $groups->last(); @endphp

        @if ($completedGroup->isDoneGroup ?? false)
        <x-ui-kanban-column
                :title="'Erledigt'"
        >
        	@forelse ($completedGroup->tasks as $task)
        	
        	   <livewire:planner.task-preview-card 
                    :task="$task"
                    wire:key="task-preview-{{ $task->uuid }}"
                />

        	@empty
                <div class="text-xs text-slate-400 italic">Noch keine erledigten Aufgaben</div>
            @endforelse
        </x-ui-kanban-column>
        @endif

        
    </x-ui-kanban-board>
    <livewire:planner.task-group-settings-modal/>
</div>