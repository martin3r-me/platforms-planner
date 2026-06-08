<?php

namespace Platform\Planner\Livewire\ProjectCanvas;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Events\ProjectCanvasWorkshopNoteChanged;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasWorkshopNote;
use Platform\Planner\Services\ProjectCanvasAnalysisService;
use Platform\Planner\Services\ProjectCanvasCommentService;
use Platform\Core\Services\ContextFileService;

class Show extends Component
{
    use WithFileUploads;

    public PlannerProject $project;
    public PlannerProjectCanvas $canvas;

    public string $viewMode = 'list'; // 'list' | 'workshop'

    public string $commentContent = '';
    public ?int $commentBlockId = null;
    public ?int $replyToId = null;
    public ?int $filterBlockId = null;

    public $workshopFile;

    public function mount(PlannerProject $plannerProject, PlannerProjectCanvas $canvas): void
    {
        $this->project = $plannerProject;

        abort_unless($canvas->project_id === $plannerProject->id, 404);
        abort_unless($canvas->team_id === Auth::user()->currentTeam->id, 403);
        abort_unless($canvas->isVisibleTo(Auth::user()), 403);

        $this->canvas = $canvas;
    }

    public function getListeners(): array
    {
        $listeners = [];

        try {
            if (auth()->check() && $this->canvas) {
                $canvasId = $this->canvas->id;
                $listeners["echo-private:canvas.workshop.{$canvasId},.note.changed"] = 'onNoteChanged';
            }
        } catch (\Throwable $e) {
            // Fail silently
        }

        return $listeners;
    }

    public function onNoteChanged($payload): void
    {
        if (($payload['senderId'] ?? null) === Auth::id()) {
            return;
        }

        $this->dispatch('workshop-note-changed', $payload);
    }

    private function broadcastNoteChange(string $action, int $noteId, array $data = []): void
    {
        try {
            ProjectCanvasWorkshopNoteChanged::dispatch(
                $this->canvas->id,
                Auth::id(),
                $action,
                $noteId,
                $data,
            );
        } catch (\Throwable $e) {
            // Broadcasting failure should not break the CRUD operation
        }
    }

    public function toggleVisibility(): void
    {
        $this->canvas->update([
            'visibility' => $this->canvas->visibility === PlannerProjectCanvas::VISIBILITY_PRIVATE
                ? PlannerProjectCanvas::VISIBILITY_TEAM
                : PlannerProjectCanvas::VISIBILITY_PRIVATE,
        ]);
    }

    public function rendered(): void
    {
        $this->dispatch('comms', [
            'model' => get_class($this->canvas),
            'modelId' => $this->canvas->id,
            'subject' => $this->canvas->name,
            'description' => 'Project Canvas',
            'url' => route('planner.projects.canvas.show', [$this->project, $this->canvas]),
            'source' => 'planner.projects.canvas.show',
            'recipients' => [],
            'meta' => ['view_type' => 'show'],
        ]);
    }

    public function createPublicLink(): void
    {
        if (! $this->project->public_token) {
            $this->project->generatePublicToken();
        }

        $this->canvas->generatePublicToken();
        $this->project->refresh();
    }

    public function togglePublicLink(): void
    {
        $this->canvas->update(['is_public' => ! $this->canvas->is_public]);
    }

    // ─── Comments ──────────────────────────────────────────

    public function addComment(): void
    {
        $this->validate([
            'commentContent' => 'required|string|min:1|max:5000',
            'commentBlockId' => 'nullable|integer|exists:planner_project_canvas_blocks,id',
            'replyToId' => 'nullable|integer|exists:planner_project_canvas_comments,id',
        ]);

        try {
            (new ProjectCanvasCommentService())->addComment($this->canvas, [
                'content' => $this->commentContent,
                'building_block_id' => $this->replyToId ? null : $this->commentBlockId,
                'parent_id' => $this->replyToId,
            ]);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $this->reset('commentContent', 'commentBlockId', 'replyToId');
    }

    public function setReplyTo(int $commentId): void
    {
        $this->replyToId = $commentId;
        $this->commentBlockId = null;
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
    }

    public function deleteComment(int $commentId): void
    {
        (new ProjectCanvasCommentService())->deleteComment($this->canvas, $commentId);
    }

    public function filterByBlock(?int $blockId): void
    {
        $this->filterBlockId = $blockId;
    }

    // ─── File Upload (Workshop) ────────────────────────────

    public function updatedWorkshopFile(): void
    {
        $this->validate([
            'workshopFile' => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,mov,avi',
        ]);

        $service = new ContextFileService();
        $result = $service->uploadForContext(
            $this->workshopFile,
            PlannerProjectCanvas::class,
            $this->canvas->id,
            [
                'generate_variants' => false,
                'user_id' => Auth::id(),
                'team_id' => Auth::user()->currentTeam->id,
            ]
        );

        $this->dispatch('workshop-file-uploaded', [
            'contextFileId' => $result['id'],
            'url' => $result['url'],
            'mimeType' => $result['mime_type'],
            'width' => $result['width'],
            'height' => $result['height'],
            'originalName' => $result['original_name'],
        ]);

        $this->workshopFile = null;
    }

    public function refreshFileUrl(int $contextFileId): array
    {
        $contextFile = \Platform\Core\Models\ContextFile::find($contextFileId);
        abort_unless($contextFile && $contextFile->team_id === Auth::user()->currentTeam->id, 403);

        $url = ContextFileService::generateUrl(
            $contextFile->disk,
            $contextFile->path,
            $contextFile->token,
            'core.context-files.show',
            60
        );

        return ['url' => $url];
    }

    // ─── View Mode ──────────────────────────────────────────

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'list' ? 'workshop' : 'list';
    }

    // ─── Workshop CRUD (WorkshopNote-based) ────────────────

    public function addWorkshopNote(array $position = [], string $type = 'note'): void
    {
        if (!in_array($type, PlannerProjectCanvasWorkshopNote::allowedTypes())) {
            $type = 'note';
        }

        $existingCount = $this->canvas->workshopNotes()->count();
        $offset = $existingCount * 25;

        $layout = config('planner.canvas_layout', []);
        $cols = (int) ($layout['columns'] ?? 3);
        $rows = (int) ($layout['rows'] ?? 3);
        $defaultX = (5000 - max(1200, $cols * 300)) / 2 + 100;
        $defaultY = (3000 - max(800, $rows * 300)) / 2 + 100;

        $defaults = match ($type) {
            'text' => ['width' => 300, 'height' => 40, 'color' => 'yellow', 'metadata' => null],
            'section' => ['width' => 500, 'height' => 400, 'color' => 'yellow', 'metadata' => null],
            'shape' => ['width' => 120, 'height' => 120, 'color' => 'blue', 'metadata' => ['shape' => 'rect']],
            'connector' => ['width' => 0, 'height' => 0, 'color' => 'blue', 'metadata' => null],
            'kanban' => ['width' => 600, 'height' => 400, 'color' => 'blue', 'metadata' => [
                'columns' => [
                    ['id' => 'col_' . base_convert(time(), 10, 36) . 'a', 'title' => 'To Do', 'wipLimit' => 0, 'cards' => []],
                    ['id' => 'col_' . base_convert(time(), 10, 36) . 'b', 'title' => 'In Progress', 'wipLimit' => 3, 'cards' => []],
                    ['id' => 'col_' . base_convert(time(), 10, 36) . 'c', 'title' => 'Done', 'wipLimit' => 0, 'cards' => []],
                ],
            ]],
            'image' => ['width' => 300, 'height' => 300, 'color' => 'yellow', 'metadata' => null],
            'image_grid' => ['width' => 500, 'height' => 400, 'color' => 'yellow', 'metadata' => ['images' => [], 'columns' => 2, 'gap' => 4]],
            'video' => ['width' => 480, 'height' => 300, 'color' => 'blue', 'metadata' => null],
            default => ['width' => 200, 'height' => 150, 'color' => 'yellow', 'metadata' => null],
        };

        $note = PlannerProjectCanvasWorkshopNote::create([
            'canvas_id' => $this->canvas->id,
            'title' => '',
            'content' => '',
            'color' => $defaults['color'],
            'type' => $type,
            'position_x' => ($position['x'] ?? $defaultX) + $offset,
            'position_y' => ($position['y'] ?? $defaultY) + $offset,
            'width' => $defaults['width'],
            'height' => $defaults['height'],
            'metadata' => $defaults['metadata'],
            'created_by_user_id' => Auth::id(),
        ]);

        $this->broadcastNoteChange('created', $note->id, [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->content ?? '',
            'color' => $note->color,
            'type' => $note->type ?? 'note',
            'x' => $note->position_x,
            'y' => $note->position_y,
            'width' => $note->width,
            'height' => $note->height,
            'metadata' => $note->metadata,
        ]);
    }

    public function updateNotePosition(int $noteId, array $pos): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        $blockId = isset($pos['blockId']) ? (int) $pos['blockId'] : null;

        if ($blockId) {
            $block = \Platform\Planner\Models\PlannerProjectCanvasBlock::find($blockId);
            if (!$block || $block->canvas_id !== $this->canvas->id) {
                $blockId = null;
            }
        }

        $note->update([
            'position_x' => $pos['x'] ?? $note->position_x,
            'position_y' => $pos['y'] ?? $note->position_y,
            'width' => isset($pos['width']) ? (int) $pos['width'] : $note->width,
            'height' => isset($pos['height']) ? (int) $pos['height'] : $note->height,
            'building_block_id' => $blockId,
        ]);

        $this->broadcastNoteChange('moved', $noteId, [
            'x' => $note->position_x,
            'y' => $note->position_y,
            'width' => $note->width,
            'height' => $note->height,
        ]);
    }

    public function updateNoteText(int $noteId, string $title, string $content): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        $note->update([
            'title' => $title,
            'content' => $content,
        ]);

        $this->broadcastNoteChange('text_updated', $noteId, [
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function updateNoteColor(int $noteId, string $color): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        if (!in_array($color, PlannerProjectCanvasWorkshopNote::allowedColors())) return;

        $note->update(['color' => $color]);

        $this->broadcastNoteChange('color_updated', $noteId, [
            'color' => $color,
        ]);
    }

    public function addConnector(int $fromNoteId, int $toNoteId): void
    {
        $fromNote = PlannerProjectCanvasWorkshopNote::find($fromNoteId);
        $toNote = PlannerProjectCanvasWorkshopNote::find($toNoteId);
        abort_unless($fromNote && $fromNote->canvas_id === $this->canvas->id, 403);
        abort_unless($toNote && $toNote->canvas_id === $this->canvas->id, 403);

        $midX = ($fromNote->position_x + $toNote->position_x) / 2;
        $midY = ($fromNote->position_y + $toNote->position_y) / 2;

        $connector = PlannerProjectCanvasWorkshopNote::create([
            'canvas_id' => $this->canvas->id,
            'title' => '',
            'content' => '',
            'color' => 'blue',
            'type' => 'connector',
            'position_x' => $midX,
            'position_y' => $midY,
            'width' => 0,
            'height' => 0,
            'metadata' => [
                'fromNoteId' => $fromNoteId,
                'toNoteId' => $toNoteId,
                'style' => 'solid',
                'arrowHead' => 'end',
            ],
            'created_by_user_id' => Auth::id(),
        ]);

        $this->broadcastNoteChange('created', $connector->id, [
            'id' => $connector->id,
            'title' => '',
            'content' => '',
            'color' => 'blue',
            'type' => 'connector',
            'x' => $connector->position_x,
            'y' => $connector->position_y,
            'width' => 0,
            'height' => 0,
            'metadata' => $connector->metadata,
        ]);
    }

    public function deleteWorkshopNote(int $noteId): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        // Cascade: delete connectors referencing this note
        if ($note->type !== 'connector') {
            $this->canvas->workshopNotes()
                ->where('type', 'connector')
                ->get()
                ->filter(function (PlannerProjectCanvasWorkshopNote $c) use ($noteId) {
                    $meta = $c->metadata ?? [];
                    return ($meta['fromNoteId'] ?? null) === $noteId
                        || ($meta['toNoteId'] ?? null) === $noteId;
                })
                ->each(function (PlannerProjectCanvasWorkshopNote $c) {
                    $this->broadcastNoteChange('deleted', $c->id);
                    $c->delete();
                });
        }

        $note->delete();

        $this->broadcastNoteChange('deleted', $noteId);
    }

    public function updateNoteMetadata(int $noteId, array $meta): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        $note->update([
            'metadata' => array_merge($note->metadata ?? [], $meta),
        ]);

        $this->broadcastNoteChange('metadata_updated', $noteId, [
            'metadata' => $note->fresh()->metadata,
        ]);
    }

    public function updateWorkshopSettings(array $settings): void
    {
        $allowed = ['gridWidth', 'gridHeight'];
        $current = $this->canvas->workshop_settings ?? [];
        $merged = array_merge($current, array_intersect_key($settings, array_flip($allowed)));

        $this->canvas->update(['workshop_settings' => $merged]);
    }

    public function adoptNote(int $noteId, int $blockId): void
    {
        $note = PlannerProjectCanvasWorkshopNote::find($noteId);
        abort_unless($note && $note->canvas_id === $this->canvas->id, 403);

        $block = \Platform\Planner\Models\PlannerProjectCanvasBlock::find($blockId);
        abort_unless($block && $block->canvas_id === $this->canvas->id, 403);

        $existingCount = $block->entries()->count();

        \Platform\Planner\Models\PlannerProjectCanvasEntry::create([
            'block_id' => $block->id,
            'title' => $note->title,
            'content' => $note->content ?? '',
            'entry_type' => 'text',
            'position' => $existingCount + 1,
            'created_by_user_id' => $note->created_by_user_id,
        ]);

        $note->delete();

        $this->broadcastNoteChange('deleted', $noteId);
    }

    // ─── Activities ────────────────────────────────────────

    #[Computed]
    public function activities()
    {
        if (!$this->canvas) {
            return collect();
        }

        return $this->canvas->activities()
            ->with('user')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'title' => $this->formatActivityTitle($activity),
                    'time' => $activity->created_at->diffForHumans(),
                    'user' => $activity->user?->name ?? 'System',
                    'type' => $activity->activity_type,
                    'name' => $activity->name,
                ];
            });
    }

    private function formatActivityTitle($activity): string
    {
        $userName = $activity->user?->name ?? 'System';
        $activityName = $activity->name;

        $translations = [
            'created' => 'erstellt',
            'updated' => 'aktualisiert',
            'deleted' => 'geloescht',
            'manual' => 'hat eine Nachricht hinzugefuegt',
        ];

        $translatedName = $translations[$activityName] ?? $activityName;

        if ($activity->message) {
            return "{$userName}: {$activity->message}";
        }

        if ($activity->properties && !empty($activity->properties)) {
            $props = $activity->properties;
            $changedFields = [];

            if (isset($props['old']) || isset($props['new'])) {
                $changedFields = array_keys($props['new'] ?? $props['old'] ?? []);
            } else {
                $changedFields = array_keys($props);
            }

            if (!empty($changedFields)) {
                $fieldNames = array_map(function ($field) {
                    $map = [
                        'name' => 'Name',
                        'description' => 'Beschreibung',
                        'status' => 'Status',
                        'is_public' => 'Oeffentlich',
                        'public_token' => 'Public Token',
                    ];
                    return $map[$field] ?? $field;
                }, $changedFields);

                $fields = implode(', ', $fieldNames);
                return "{$userName} hat {$fields} {$translatedName}";
            }
        }

        return "{$userName} hat das Canvas {$translatedName}";
    }

    // ─── Render ────────────────────────────────────────────

    public function render()
    {
        $this->canvas->load(['blocks.entries', 'createdByUser', 'snapshots']);

        $canvasData = $this->canvas->toCanvasArray();
        $analysisData = (new ProjectCanvasAnalysisService())->analyze($this->canvas);
        $layout = config('planner.canvas_layout', [
            'type' => 'grid',
            'columns' => 3,
            'rows' => 3,
            'areas' => '',
            'area_map' => [],
        ]);
        $blockDefs = collect(config('planner.canvas_block_types', []))->map(function ($def, $key) {
            return array_merge($def, ['key' => $key]);
        })->values()->toArray();

        $commentService = new ProjectCanvasCommentService();
        $comments = $commentService->getCommentsForCanvas($this->canvas, $this->filterBlockId);
        $allComments = $this->canvas->comments()->get();

        // Workshop: load notes for the notes layer
        $workshopNotes = [];
        if ($this->viewMode === 'workshop') {
            $workshopNotes = $this->canvas->workshopNotes()
                ->orderBy('created_at')
                ->get()
                ->map(fn (PlannerProjectCanvasWorkshopNote $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'content' => $n->content ?? '',
                    'color' => $n->color,
                    'type' => $n->type ?? 'note',
                    'x' => $n->position_x,
                    'y' => $n->position_y,
                    'width' => $n->width,
                    'height' => $n->height,
                    'metadata' => $n->metadata,
                ])
                ->values()
                ->toArray();
        }

        return view('planner::livewire.project-canvas.show', [
            'canvasData' => $canvasData,
            'analysisData' => $analysisData,
            'layout' => $layout,
            'blockDefs' => $blockDefs,
            'comments' => $comments,
            'allComments' => $allComments,
            'activities' => $this->activities,
            'workshopNotes' => $workshopNotes,
        ])->layout('platform::layouts.app');
    }
}
