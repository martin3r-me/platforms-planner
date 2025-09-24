<?php

namespace Platform\Planner\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Schema\ModelSchemaRegistry as Schemas;
use Platform\Core\Services\ForeignKeyResolver;

class PlannerCommandService
{
    // Query generisch auf Basis Registry
    public function query(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        if ($modelKey === '') {
            // Generische Auswahl anbieten (alle Planner-Modelle)
            $choices = array_map(function($k){
                return ['key' => $k, 'label' => $k];
            }, \Platform\Core\Schema\ModelSchemaRegistry::keysByPrefix('planner.'));
            return ['ok' => false, 'message' => 'Modell wählen', 'needResolve' => true, 'choices' => $choices];
        }
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) return ['ok' => false, 'message' => 'Unbekanntes Modell'];

        $q          = trim((string)($slots['q'] ?? ''));
        $sort       = Schemas::validateSort($modelKey, $slots['sort'] ?? null, 'id');
        $order      = strtolower((string)($slots['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $limit      = min(max((int)($slots['limit'] ?? 20), 1), 100);
        $fieldsReq  = array_map('trim', explode(',', (string)($slots['fields'] ?? '')));
        if (empty($fieldsReq) || $fieldsReq === ['']) {
            // Vollständig dynamischer Fallback: id + alle selectable Felder
            $schema = Schemas::get($modelKey);
            $selectable = $schema['selectable'] ?? [];
            $fieldsReq = array_merge(['id'], $selectable);
        }
        $fields     = Schemas::validateFields($modelKey, $fieldsReq, ['id']);

        /** @var Builder $query */
        $query = $eloquent::query();
        // Team-Scoped: falls Spalte team_id existiert, filtern
        if (Schema::hasColumn((new $eloquent)->getTable(), 'team_id') && auth()->check()) {
            $query->where('team_id', auth()->user()->currentTeam?->id);
        }

        // Standard-Sichtbarkeit: für Tasks nur eigene Aufgaben (owner oder in charge)
        if ($modelKey === 'planner.tasks' && auth()->check()) {
            $query->where(function($q){
                $uid = auth()->id();
                $q->where('user_id', $uid)->orWhere('user_in_charge_id', $uid);
            });
        }

        // Volltext: wenn title/name vorhanden
        if ($q !== '') {
            if (in_array('title', Schemas::get($modelKey)['fields'] ?? [], true)) {
                $query->where('title', 'LIKE', '%'.$q.'%');
            } elseif (in_array('name', Schemas::get($modelKey)['fields'] ?? [], true)) {
                $query->where('name', 'LIKE', '%'.$q.'%');
            }
        }

        // Filter (einfach, nur erlaubte Keys)
        $filters = Schemas::validateFilters($modelKey, (array)($slots['filters'] ?? []));
        foreach ($filters as $k => $v) {
            if ($v === null || $v === '') continue;
            $query->where($k, $v);
        }

        $rows = $query->orderBy($sort, $order)->limit($limit)->get($fields);
        
        // Debug: Log die abgefragten Felder und Ergebnisse
        \Log::info("PlannerCommandService: Abgefragte Felder: " . implode(', ', $fields));
        \Log::info("PlannerCommandService: Anzahl Ergebnisse: " . $rows->count());
        if ($rows->count() > 0) {
            $firstRow = $rows->first()->toArray();
            \Log::info("PlannerCommandService: Erstes Ergebnis: " . json_encode($firstRow));
            \Log::info("PlannerCommandService: Titel im ersten Ergebnis: " . ($firstRow['title'] ?? 'FEHLT'));
        }
        
        return ['ok' => true, 'data' => ['items' => $rows->toArray()], 'message' => 'Gefunden ('.$rows->count().')'];
    }

    // Open generisch auf Basis Registry
    public function open(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        if ($modelKey === '') {
            $choices = array_map(function($k){
                return ['key' => $k, 'label' => $k];
            }, \Platform\Core\Schema\ModelSchemaRegistry::keysByPrefix('planner.'));
            return ['ok' => false, 'message' => 'Modell wählen', 'needResolve' => true, 'choices' => $choices];
        }
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        $route    = Schemas::meta($modelKey, 'show_route');
        $param    = Schemas::meta($modelKey, 'route_param');
        if (!$eloquent || !$route || !$param) return ['ok' => false, 'message' => 'Navigation für Modell nicht verfügbar'];

        $id = $slots['id'] ?? null;
        $uuid = $slots['uuid'] ?? null;
        $name = $slots['name'] ?? null;
        $row = null;
        if ($id) {
            $row = $eloquent::find($id);
        } elseif ($uuid && in_array('uuid', Schemas::get($modelKey)['fields'] ?? [], true)) {
            $row = $eloquent::where('uuid', $uuid)->first();
        } elseif ($name) {
            $titleField = in_array('title', Schemas::get($modelKey)['fields'] ?? [], true) ? 'title' : (in_array('name', Schemas::get($modelKey)['fields'] ?? [], true) ? 'name' : null);
            if ($titleField) {
                $matches = $eloquent::where($titleField, 'LIKE', '%'.$name.'%')
                    ->orderByRaw('CASE WHEN '.$titleField.' = ? THEN 0 ELSE 1 END', [$name])
                    ->orderBy($titleField)
                    ->limit(5)
                    ->get(['id', $titleField]);
                if ($matches->count() === 1) {
                    $row = $matches->first();
                } elseif ($matches->count() > 1) {
                    $labelKey = Schemas::meta($modelKey, 'label_key') ?: $titleField;
                    $choices = $matches->map(function($m) use ($labelKey){
                        return ['id' => $m->id, 'label' => $m->{$labelKey} ?? (string)$m->id];
                    })->toArray();
                    return ['ok' => false, 'message' => 'Bitte wählen', 'needResolve' => true, 'choices' => $choices];
                }
            }
        }
        if (!$row) return ['ok' => false, 'message' => 'Eintrag nicht gefunden', 'needResolve' => true];
        $url = route($route, [$param => $row->id]);
        return ['ok' => true, 'navigate' => $url, 'message' => 'Navigation bereit'];
    }

    // Create generisch (schema-validiert, confirmRequired via Command)
    public function create(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $data = (array)($slots['data'] ?? []);
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) return ['ok' => false, 'message' => 'Unbekanntes Modell'];

        $required = Schemas::required($modelKey);
        $writable = Schemas::writable($modelKey);
        // Fremdschlüssel generisch auflösen (Labels -> IDs)
        $coercion = (new ForeignKeyResolver())->coerce($modelKey, $data);
        $data = $coercion['data'];
        if (!empty($coercion['needResolve'])) {
            $nr = $coercion['needResolve'];
            if (!empty($nr['choices'] ?? [])) {
                return [
                    'ok' => false,
                    'message' => $nr['message'] ?? 'Bitte wählen',
                    'needResolve' => true,
                    'choices' => $nr['choices'],
                ];
            }
            return [
                'ok' => false,
                'message' => $nr['message'] ?? 'Referenz nicht gefunden',
                'needResolve' => true,
            ];
        }
        foreach ($required as $f) {
            if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '') {
                return ['ok' => false, 'message' => 'Pflichtfeld fehlt: '.$f, 'needResolve' => true, 'missing' => $required];
            }
        }
        $payload = [];
        foreach ($writable as $f) {
            if (array_key_exists($f, $data)) {
                $payload[$f] = $data[$f];
            }
        }
        if (Schema::hasColumn((new $eloquent)->getTable(), 'team_id') && auth()->check()) {
            $payload['team_id'] = auth()->user()->currentTeam?->id;
        }
        /** @var \Illuminate\Database\Eloquent\Model $row */
        $row = new $eloquent();
        $row->fill($payload);
        $row->save();
        $route = Schemas::meta($modelKey, 'show_route');
        $param = Schemas::meta($modelKey, 'route_param');
        $navigate = ($route && $param) ? route($route, [$param => $row->id]) : null;
        return ['ok' => true, 'message' => 'Angelegt', 'data' => ['id' => $row->id], 'navigate' => $navigate];
    }

    public function update(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $id = (int)($slots['id'] ?? 0);
        $data = (array)($slots['data'] ?? []);
        $confirmed = (bool)($slots['confirmed'] ?? false);
        
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) {
            return ['ok' => false, 'message' => 'Unbekanntes Modell'];
        }

        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID erforderlich'];
        }

        $row = $eloquent::find($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden'];
        }

        $writable = Schemas::writable($modelKey);

        // Fremdschlüssel generisch auflösen (Labels -> IDs)
        $coercion = (new \Platform\Core\Services\ForeignKeyResolver())->coerce($modelKey, $data);
        $data = $coercion['data'];
        if (!empty($coercion['needResolve'])) {
            $nr = $coercion['needResolve'];
            if (!empty($nr['choices'] ?? [])) {
                return [
                    'ok' => false,
                    'message' => $nr['message'] ?? 'Bitte wählen',
                    'needResolve' => true,
                    'choices' => $nr['choices'],
                ];
            }
            return [
                'ok' => false,
                'message' => $nr['message'] ?? 'Referenz nicht gefunden',
                'needResolve' => true,
            ];
        }
        
        // Sanitize einfache Textfelder
        if (isset($data['title'])) {
            $data['title'] = trim((string) $data['title']);
        }
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (isset($data['description'])) {
            $data['description'] = trim((string) $data['description']);
        }

        // Due-Date Parsing
        if (!empty($data['due_date'])) {
            $data['due_date'] = $this->parseDueDate((string)$data['due_date']);
        }

        // Confirm-Gate: ohne bestätigtes Flag keine Speicherung
        if ($confirmed !== true) {
            return [
                'ok' => false,
                'message' => 'Bestätigung erforderlich',
                'needResolve' => true,
                'confirmRequired' => true,
                'data' => ['proposed' => $data],
            ];
        }

        $payload = [];
        foreach ($writable as $f) {
            if (array_key_exists($f, $data)) {
                $payload[$f] = $data[$f];
            }
        }

        $row->fill($payload);
        $row->save();
        
        $route = Schemas::meta($modelKey, 'show_route');
        $param = Schemas::meta($modelKey, 'route_param');
        $navigate = ($route && $param) ? route($route, [$param => $row->id]) : null;
        return ['ok' => true, 'message' => 'Aktualisiert', 'data' => ['id' => $row->id], 'navigate' => $navigate];
    }

    public function delete(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $id = (int)($slots['id'] ?? 0);
        $name = (string)($slots['name'] ?? '');
        
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) {
            return ['ok' => false, 'message' => 'Unbekanntes Modell'];
        }

        $query = $eloquent::query();
        
        if ($id > 0) {
            $query->where('id', $id);
        } elseif (!empty($name)) {
            $labelKey = Schemas::meta($modelKey, 'label_key') ?: 'name';
            $query->where($labelKey, 'LIKE', '%' . $name . '%');
        } else {
            return ['ok' => false, 'message' => 'ID oder Name erforderlich'];
        }

        $row = $query->first();
        if (!$row) {
            return ['ok' => false, 'message' => 'Eintrag nicht gefunden'];
        }

        $row->delete();
        return ['ok' => true, 'message' => 'Gelöscht', 'data' => ['id' => $row->id]];
    }

    // keine Normalisierung: LLM soll das Modell explizit setzen oder nachfragen
}


