<?php

namespace Platform\Planner\Export;

/**
 * Unterstützte Export-Formate.
 * Erweiterbar für zukünftige Formate (CSV, Excel etc.)
 */
enum ExportFormat: string
{
    case JSON = 'json';
    case PDF = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::JSON => 'JSON',
            self::PDF => 'PDF',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::JSON => 'application/json',
            self::PDF => 'application/pdf',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
