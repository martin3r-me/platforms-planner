<?php

namespace Platform\Planner\Export\Formatters;

use Platform\Planner\Export\Contracts\ExportFormatter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF Export-Formatter.
 *
 * Generiert druckfertige PDFs für Aufgaben und Projekte.
 * Nutzt HTML-to-PDF Rendering über eine Blade-View.
 */
class PdfExportFormatter implements ExportFormatter
{
    public function exportTask(array $data, string $filename): Response|StreamedResponse
    {
        $html = view('planner::export.task-pdf', [
            'task' => $data,
            'exportedAt' => now()->format('d.m.Y H:i'),
        ])->render();

        return $this->pdfResponse($html, $filename);
    }

    public function exportProject(array $data, string $filename): Response|StreamedResponse
    {
        $html = view('planner::export.project-pdf', [
            'project' => $data,
            'exportedAt' => now()->format('d.m.Y H:i'),
        ])->render();

        return $this->pdfResponse($html, $filename);
    }

    /**
     * Erzeugt eine PDF-Response aus HTML.
     *
     * Strategie: Versucht zuerst DomPDF (falls installiert),
     * dann Fallback auf druckoptimiertes HTML mit Print-CSS.
     */
    protected function pdfResponse(string $html, string $filename): Response
    {
        // Strategie 1: DomPDF (falls als Dependency verfügbar)
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait');

            return new Response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        // Strategie 2: Snappy/wkhtmltopdf (falls verfügbar)
        if (class_exists(\Barryvdh\Snappy\Facades\SnappyPdf::class)) {
            $pdf = \Barryvdh\Snappy\Facades\SnappyPdf::loadHTML($html)
                ->setPaper('a4')
                ->setOrientation('portrait');

            return new Response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        // Fallback: Druckoptimiertes HTML (Browser-PDF-Druck via window.print())
        $htmlFilename = str_replace('.pdf', '.html', $filename);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . $htmlFilename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
