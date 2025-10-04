# Planner Policy Usage Examples

## Verwendung in Views

### Projekt-Views
```blade
<!-- Projekt anzeigen -->
@can('view', $project)
    <div class="project-details">
        <h2>{{ $project->name }}</h2>
        <p>{{ $project->description }}</p>
    </div>
@endcan

<!-- Projekt bearbeiten -->
@can('update', $project)
    <button @click="editProject">Projekt bearbeiten</button>
@endcan

<!-- Projekt löschen -->
@can('delete', $project)
    <button @click="deleteProject" class="text-red-500">Projekt löschen</button>
@endcan

<!-- Mitglieder einladen -->
@can('invite', $project)
    <button @click="inviteMember">Mitglied einladen</button>
@endcan

<!-- Rollen ändern -->
@can('changeRole', $project)
    <button @click="changeRole">Rolle ändern</button>
@endcan
```

### Aufgaben-Views
```blade
<!-- Aufgabe anzeigen -->
@can('view', $task)
    <div class="task-item">
        <h3>{{ $task->title }}</h3>
        <p>{{ $task->description }}</p>
    </div>
@endcan

<!-- Aufgabe bearbeiten -->
@can('update', $task)
    <button @click="editTask">Aufgabe bearbeiten</button>
@endcan

<!-- Aufgabe löschen -->
@can('delete', $task)
    <button @click="deleteTask" class="text-red-500">Aufgabe löschen</button>
@endcan

<!-- Aufgabe zuweisen -->
@can('assign', $task)
    <select wire:model="assignedUserId">
        <option value="">Nicht zugewiesen</option>
        @foreach($project->members as $member)
            <option value="{{ $member->user_id }}">{{ $member->user->name }}</option>
        @endforeach
    </select>
@endcan

<!-- Aufgabe abschließen -->
@can('complete', $task)
    <button @click="completeTask" class="text-green-500">Aufgabe abschließen</button>
@endcan
```

## Verwendung in Controllers

### Projekt-Controller
```php
public function show(PlannerProject $project)
{
    $this->authorize('view', $project);
    
    return view('planner.projects.show', compact('project'));
}

public function update(Request $request, PlannerProject $project)
{
    $this->authorize('update', $project);
    
    $project->update($request->validated());
    return redirect()->back();
}

public function destroy(PlannerProject $project)
{
    $this->authorize('delete', $project);
    
    $project->delete();
    return redirect()->route('planner.projects.index');
}
```

### Aufgaben-Controller
```php
public function show(PlannerTask $task)
{
    $this->authorize('view', $task);
    
    return view('planner.tasks.show', compact('task'));
}

public function update(Request $request, PlannerTask $task)
{
    $this->authorize('update', $task);
    
    $task->update($request->validated());
    return redirect()->back();
}

public function assign(Request $request, PlannerTask $task)
{
    $this->authorize('assign', $task);
    
    $task->update(['assigned_user_id' => $request->user_id]);
    return redirect()->back();
}

public function complete(PlannerTask $task)
{
    $this->authorize('complete', $task);
    
    $task->update(['is_done' => true, 'completed_at' => now()]);
    return redirect()->back();
}
```

## Verwendung in Livewire-Komponenten

### Projekt-Komponente
```php
class ProjectShow extends Component
{
    public PlannerProject $project;

    public function mount(PlannerProject $project)
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function delete()
    {
        $this->authorize('delete', $this->project);
        
        $this->project->delete();
        return redirect()->route('planner.projects.index');
    }

    public function inviteMember($userId)
    {
        $this->authorize('invite', $this->project);
        
        $this->project->projectUsers()->create([
            'user_id' => $userId,
            'role' => 'member'
        ]);
    }
}
```

### Aufgaben-Komponente
```php
class TaskShow extends Component
{
    public PlannerTask $task;

    public function mount(PlannerTask $task)
    {
        $this->authorize('view', $task);
        $this->task = $task;
    }

    public function complete()
    {
        $this->authorize('complete', $this->task);
        
        $this->task->update([
            'is_done' => true,
            'completed_at' => now()
        ]);
    }

    public function assign($userId)
    {
        $this->authorize('assign', $this->task);
        
        $this->task->update(['assigned_user_id' => $userId]);
    }
}
```

## Berechtigungslogik

### Aufgaben ohne Projekt
- ✅ **Owner** hat vollen Zugriff
- ❌ **Andere** haben keinen Zugriff

### Aufgaben mit Projekt
- ✅ **Owner** hat vollen Zugriff
- ✅ **Zugewiesener User** kann abschließen
- ✅ **Projekt-Mitglieder** haben Zugriff basierend auf Projekt-Rolle:
  - **Owner/Admin**: Vollzugriff
  - **Member**: Schreibzugriff
  - **Viewer**: Leszugriff

### Projekte
- ✅ **Team-Mitglieder** können Projekte erstellen
- ✅ **Projekt-Mitglieder** haben Zugriff basierend auf Rolle:
  - **Owner**: Vollzugriff + Ownership-Übertragung
  - **Admin**: Vollzugriff + Einladungen
  - **Member**: Schreibzugriff
  - **Viewer**: Leszugriff
