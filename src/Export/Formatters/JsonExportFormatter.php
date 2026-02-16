<?php

namespace Platform\Planner\Export\Formatters;

use Platform\Planner\Export\Contracts\ExportFormatter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON Export-Formatter.
 *
 * Exportiert Aufgaben und Projekte als formatiertes JSON.
 */
class JsonExportFormatter implements ExportFormatter
{
    public function exportTask(array $data, string $filename): Response|StreamedResponse
    {
        $export = [
            'export_type' => 'task',
            'export_format' => 'json',
            'exported_at' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => $data,
        ];

        return $this->jsonResponse($export, $filename);
    }

    public function exportProject(array $data, string $filename): Response|StreamedResponse
    {
        $export = [
            'export_type' => 'project',
            'export_format' => 'json',
            'exported_at' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => $data,
        ];

        return $this->jsonResponse($export, $filename);
    }

    protected function jsonResponse(array $data, string $filename): Response
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
