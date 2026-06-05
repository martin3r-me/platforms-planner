<?php

namespace Platform\Planner\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;

class ProjectCanvasPdfController extends Controller
{
    public function __invoke(PlannerProject $plannerProject, PlannerProjectCanvas $canvas)
    {
        abort_unless(
            Auth::check() && $canvas->team_id === Auth::user()->currentTeam?->id,
            403,
            'Zugriff verweigert'
        );

        abort_unless($canvas->project_id === $plannerProject->id, 404);

        $canvas->load(['blocks.entries', 'createdByUser']);

        $canvasData = $canvas->toCanvasArray();
        $blockDefs = collect(config('planner.canvas_block_types', []))->map(function ($def, $key) {
            return array_merge($def, ['key' => $key]);
        })->values()->toArray();

        $fontScale = $this->calculateFontScale($canvasData);

        $html = view('planner::export.canvas-pdf', [
            'canvas' => $canvas,
            'canvasData' => $canvasData,
            'blockDefs' => $blockDefs,
            'fontScale' => $fontScale,
        ])->render();

        $filename = str($canvas->name ?: 'project-canvas')
            ->slug('-')
            ->append('.pdf')
            ->toString();

        return Pdf::loadHTML($html)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function calculateFontScale(array $canvasData): string
    {
        $totalChars = 0;
        $totalEntries = 0;

        foreach ($canvasData['blocks'] ?? [] as $block) {
            foreach ($block['entries'] ?? [] as $entry) {
                $totalEntries++;
                $totalChars += mb_strlen($entry['title'] ?? '');
                $totalChars += mb_strlen($entry['content'] ?? '');
            }
        }

        if ($totalChars < 800 && $totalEntries <= 18) {
            return 'lg';
        }

        if ($totalChars < 1800 && $totalEntries <= 36) {
            return 'md';
        }

        if ($totalChars < 3500 && $totalEntries <= 60) {
            return 'sm';
        }

        return 'xs';
    }
}
