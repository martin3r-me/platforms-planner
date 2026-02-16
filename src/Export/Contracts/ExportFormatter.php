<?php

namespace Platform\Planner\Export\Contracts;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Interface für Export-Formatter.
 * Jedes Format (JSON, PDF, CSV, Excel...) implementiert dieses Interface.
 */
interface ExportFormatter
{
    /**
     * Exportiert eine einzelne Aufgabe.
     */
    public function exportTask(array $data, string $filename): Response|StreamedResponse;

    /**
     * Exportiert ein ganzes Projekt.
     */
    public function exportProject(array $data, string $filename): Response|StreamedResponse;
}
