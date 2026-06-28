<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\Recipe\RecipeResolver;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\Template\TemplateRegistry;
use Platform\Core\Verbalization\Verbalizer;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Verbalization\PlannerProjectSubjectCollector;

/**
 * Verbalisierung eines Planner-Projects als Prosa.
 *
 * Pipeline: PlannerProjectSubjectCollector -> Subject -> Verbalizer (Template + LLM) -> Prosa.
 * Test-Tool fuer die Sprachorgan-Architektur — gibt Prosa + factSheet + meta zurueck,
 * damit man Schichten getrennt sehen kann.
 */
class ProjectVerbalizeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project.verbalize';
    }

    public function getDescription(): string
    {
        return 'VERBALIZE /projects/{id} - Erzeugt faktentreue Prosa zum aktuellen Zustand eines Planner-Projekts. Parameter: project_id (required, integer), style (optional, "formal"|"collegial", default "formal"), provider (optional, "anthropic"|"openai", default config), model (optional, override), include_fact_sheet (optional, boolean, default false). Gibt Prosa zurueck plus optional die deterministische Faktenbasis (was das LLM bekommen hat).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Projekts (siehe planner.projects.GET).',
                ],
                'style' => [
                    'type' => 'string',
                    'enum' => ['formal', 'collegial'],
                    'description' => 'Stilprofil. Default "formal" (Sie, sachlich).',
                ],
                'provider' => [
                    'type' => 'string',
                    'description' => 'LLM-Provider-Key (z.B. "anthropic"). Wenn leer: Config-Default.',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Optional: Modell-Override (z.B. "claude-opus-4-7"). Wenn leer: Provider-Default.',
                ],
                'include_fact_sheet' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true: gibt die deterministische Faktenbasis mit zurueck. Hilfreich zum Debuggen.',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true: kein LLM-Call. Gibt nur Subject + Faktenbasis zurueck. Nuetzlich ohne API-Key oder zum Debuggen der Sammler-Pipeline.',
                ],
                'recipe_key' => [
                    'type' => 'string',
                    'description' => 'Optional: key einer Verbalization-Recipe (z.B. "customer_brief", "weekly_status", "wall_display"). Steuert Sammel-Tiefe + Style. Ohne Recipe wird die Default-Sammlung verwendet.',
                ],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
        }

        $projectId = (int) ($arguments['project_id'] ?? 0);
        if ($projectId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
        }

        $project = PlannerProject::find($projectId);
        if (! $project) {
            return ToolResult::error('PROJECT_NOT_FOUND', "Projekt {$projectId} nicht gefunden.");
        }

        try {
            Gate::forUser($context->user)->authorize('view', $project);
        } catch (AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Projekt (Policy).');
        }

        $style = match ($arguments['style'] ?? 'formal') {
            'collegial' => StyleProfile::collegial(),
            default => StyleProfile::formal(),
        };

        // Semantic Layer aus dem aktuellen Team-Kontext injizieren (BHG-Konstitution etc.).
        // Caller-Disziplin: Verbalizer bleibt domain-blind, das Tool kennt den Kontext.
        $semanticLayerInjected = false;
        $semanticLayerTokens = null;
        $semanticLayerError = null;
        try {
            $team = $context->team ?? $context->user->currentTeam ?? null;
            /** @var SemanticLayerResolver $resolver */
            $resolver = app(SemanticLayerResolver::class);
            $resolved = $resolver->resolveFor($team, 'planner');
            if ($resolved->rendered_block) {
                $style = $style->withSemanticLayer($resolved->rendered_block);
                $semanticLayerInjected = true;
                $semanticLayerTokens = $resolved->token_count ?? null;
            }
        } catch (\Throwable $e) {
            $semanticLayerError = $e->getMessage();
        }

        $providerKey = $arguments['provider'] ?? null;
        $model = $arguments['model'] ?? null;
        $includeFactSheet = (bool) ($arguments['include_fact_sheet'] ?? false);
        $dryRun = (bool) ($arguments['dry_run'] ?? false);
        $recipeKey = $arguments['recipe_key'] ?? null;

        // Recipe aufloesen, wenn Key angegeben.
        $recipe = null;
        $recipeError = null;
        if ($recipeKey) {
            try {
                $teamForRecipe = $context->team ?? $context->user->currentTeam ?? null;
                /** @var RecipeResolver $recipeResolver */
                $recipeResolver = app(RecipeResolver::class);
                $recipe = $recipeResolver->resolve($recipeKey, $teamForRecipe?->id, 'planner_project');
                if (! $recipe) {
                    return ToolResult::error(
                        'RECIPE_NOT_FOUND',
                        "Recipe '{$recipeKey}' fuer subject_type 'planner_project' nicht gefunden."
                    );
                }
            } catch (\Throwable $e) {
                $recipeError = $e->getMessage();
            }
        }

        try {
            /** @var PlannerProjectSubjectCollector $collector */
            $collector = app(PlannerProjectSubjectCollector::class);
            $subject = $collector->collectState($project, $recipe);

            if ($dryRun) {
                // Nur Faktenbasis bauen, kein LLM-Call.
                $templates = app(TemplateRegistry::class);
                $template = $templates->resolve($subject->type);
                $factSheet = $template
                    ? $template->renderFactSheet($subject)
                    : '(kein Template registriert — generisches Fallback aktiv)';

                return ToolResult::success([
                    'project_id' => $projectId,
                    'project_name' => $subject->identity->primaryName,
                    'dry_run' => true,
                    'fact_sheet' => $factSheet,
                    'meta' => [
                        'subject_type' => $subject->type,
                        'template_used' => $template ? get_class($template) : 'generic',
                        'freshness' => [
                            'source' => $subject->freshness->source->value,
                            'as_of' => $subject->freshness->asOf->format('c'),
                            'staleness_seconds' => $subject->freshness->stalenessSeconds,
                        ],
                        'subject' => [
                            'facts_count' => count($subject->facts),
                            'edges_count' => count($subject->edges),
                        ],
                        'semantic_layer' => [
                            'injected' => $semanticLayerInjected,
                            'token_count' => $semanticLayerTokens,
                            'error' => $semanticLayerError,
                        ],
                        'recipe' => $recipe ? [
                            'key' => $recipe->key,
                            'name' => $recipe->name,
                        ] : null,
                    ],
                ]);
            }

            /** @var Verbalizer $verbalizer */
            $verbalizer = app(Verbalizer::class);
            $result = $verbalizer->verbalize(
                subject: $subject,
                style: $style,
                rails: new GuardRails(),
                providerKey: $providerKey,
                modelOverride: $model,
                recipe: $recipe,
            );
        } catch (\Throwable $e) {
            return ToolResult::error('VERBALIZATION_FAILED', $e->getMessage());
        }

        $payload = [
            'project_id' => $projectId,
            'project_name' => $subject->identity->primaryName,
            'prose' => $result->prose,
            'meta' => array_merge($result->meta, [
                'model' => $result->model,
                'usage' => $result->usage,
                'freshness' => [
                    'source' => $subject->freshness->source->value,
                    'as_of' => $subject->freshness->asOf->format('c'),
                    'staleness_seconds' => $subject->freshness->stalenessSeconds,
                ],
                'subject' => [
                    'facts_count' => count($subject->facts),
                    'edges_count' => count($subject->edges),
                ],
                'semantic_layer' => [
                    'injected' => $semanticLayerInjected,
                    'token_count' => $semanticLayerTokens,
                    'error' => $semanticLayerError,
                ],
                'recipe' => $recipe ? [
                    'key' => $recipe->key,
                    'name' => $recipe->name,
                ] : null,
            ]),
        ];

        if ($includeFactSheet) {
            $payload['fact_sheet'] = $result->factSheet;
        }

        return ToolResult::success($payload);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['planner', 'project', 'verbalize', 'prosa', 'llm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => false,
        ];
    }
}
