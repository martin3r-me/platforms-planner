<?php

namespace Platform\Planner\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Schema\ModelSchemaRegistry as Schemas;

class PlannerCommandService
{
    // Query generisch auf Basis Registry
    public function query(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
        $eloquent = Schemas::meta($modelKey, 'eloquent');
        if (!$eloquent || !class_exists($eloquent)) return ['ok' => false, 'message' => 'Unbekanntes Modell'];

        $q          = trim((string)($slots['q'] ?? ''));
        $sort       = Schemas::validateSort($modelKey, $slots['sort'] ?? null, 'id');
        $order      = strtolower((string)($slots['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $limit      = min(max((int)($slots['limit'] ?? 20), 1), 100);
        $fieldsReq  = array_map('trim', explode(',', (string)($slots['fields'] ?? '')));
        if (empty($fieldsReq) || $fieldsReq === ['']) {
            // Fallback: erste 6 selectable Felder
            $fieldsReq = array_slice(Schemas::get($modelKey)['selectable'] ?? [], 0, 6);
        }
        $fields     = Schemas::validateFields($modelKey, $fieldsReq, ['id']);

        /** @var Builder $query */
        $query = $eloquent::query();
        // Team-Scoped: falls Spalte team_id existiert, filtern
        if (Schema::hasColumn((new $eloquent)->getTable(), 'team_id') && auth()->check()) {
            $query->where('team_id', auth()->user()->currentTeam?->id);
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
        return ['ok' => true, 'data' => ['items' => $rows->toArray()], 'message' => 'Gefunden ('.$rows->count().')'];
    }

    // Open generisch auf Basis Registry
    public function open(array $slots): array
    {
        $modelKey = (string)($slots['model'] ?? '');
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
                $row = $eloquent::where($titleField, 'LIKE', '%'.$name.'%')
                    ->orderByRaw('CASE WHEN '.$titleField.' = ? THEN 0 ELSE 1 END', [$name])
                    ->orderBy($titleField)
                    ->first();
            }
        }
        if (!$row) return ['ok' => false, 'message' => 'Eintrag nicht gefunden'];
        $url = route($route, [$param => $row->id]);
        return ['ok' => true, 'navigate' => $url, 'message' => 'Navigation bereit'];
    }
}


