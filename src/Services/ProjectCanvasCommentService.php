<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Collection;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasComment;

class ProjectCanvasCommentService
{
    public function addComment(PlannerProjectCanvas $canvas, array $data): PlannerProjectCanvasComment
    {
        $content = $data['content'];
        $blockId = $data['building_block_id'] ?? null;
        $replyToId = $data['parent_id'] ?? null;

        if ($blockId) {
            $blockBelongsToCanvas = $canvas->blocks()
                ->where('id', $blockId)
                ->exists();

            if (! $blockBelongsToCanvas) {
                throw new \InvalidArgumentException('Block gehoert nicht zu diesem Canvas.');
            }
        }

        if ($replyToId) {
            $parentComment = $canvas->comments()
                ->whereNull('parent_id')
                ->where('id', $replyToId)
                ->first();

            if (! $parentComment) {
                throw new \InvalidArgumentException('Eltern-Kommentar nicht gefunden oder ist selbst eine Antwort.');
            }

            // Replies inherit block_id from parent
            $blockId = $parentComment->building_block_id;
        }

        return $canvas->comments()->create([
            'content' => $content,
            'building_block_id' => $blockId,
            'parent_id' => $replyToId,
        ]);
    }

    public function deleteComment(PlannerProjectCanvas $canvas, int $commentId): bool
    {
        $comment = $canvas->comments()->where('id', $commentId)->first();

        if (! $comment) {
            return false;
        }

        $comment->replies()->delete();
        $comment->delete();

        return true;
    }

    public function getCommentsForCanvas(PlannerProjectCanvas $canvas, ?int $filterBlockId = null): Collection
    {
        $query = $canvas->comments()
            ->rootComments()
            ->with(['replies.block', 'block'])
            ->orderBy('created_at', 'desc');

        if ($filterBlockId) {
            $query->where('building_block_id', $filterBlockId);
        }

        return $query->get();
    }
}
