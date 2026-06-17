<?php

return [
    'routing' => [
        'mode' => env('PLANNER_MODE', 'path'),
        'prefix' => 'planner',
    ],
    'guard' => 'web',

    'kind_prefix' => [
        'run' => 'RUN',
        'project' => 'PROJECT',
    ],

    'navigation' => [
        'route' => 'planner.dashboard',
        'icon'  => 'heroicon-o-clipboard-document-check',
        'order' => 20,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'planner.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Meine Aufgaben',
                    'route' => 'planner.my-tasks',
                    'icon'  => 'heroicon-o-clipboard-document-check',
                ],
                [
                    'label' => 'Delegierte Aufgaben',
                    'route' => 'planner.delegated-tasks',
                    'icon'  => 'heroicon-o-user-group',
                ],
                [
                    'label' => 'Projekt anlegen',
                    'route' => 'planner.projects.create',
                    'icon'  => 'heroicon-o-plus',
                ],
                [
                    'label' => 'Export',
                    'route' => 'planner.export',
                    'icon'  => 'heroicon-o-arrow-down-tray',
                ],
            ],
        ],
        [
            'group' => 'Projekte',
            'dynamic' => [
                // Nur Model und Parameter, keine Closures
                'model'     => \Platform\Planner\Models\PlannerProject::class,
                'team_based' => true, // sagt der Sidebar, nach aktuellem Team filtern
                'order_by'  => 'name',
                'route'     => 'planner.projects.show', // Basisroute
                'icon'      => 'heroicon-o-folder',
                'label_key' => 'name', // Feldname, das als Label genutzt wird
            ],
        ],
    ],
    'canvas_analysis_config' => [
        'strategy' => 'traffic_light',
        'risk_block' => 'risks',
        'milestone_block' => 'milestones',
        'critical_blocks' => ['project_goal', 'scope', 'milestones', 'risks'],
        'thresholds' => ['green' => 70, 'yellow' => 40],
        'weights' => [
            'completeness' => 40,
            'critical_blocks' => 30,
            'risk_assessment' => 15,
            'milestone_health' => 15,
        ],
    ],

    'canvas_layout' => [
        'type' => 'grid',
        'columns' => 3,
        'rows' => 3,
        'areas' => '',
        'area_map' => [],
    ],

    'canvas_block_types' => [
        'project_goal' => [
            'key' => 'project_goal',
            'label' => 'Project Goal',
            'description' => 'The overarching objective and purpose of the project.',
            'position' => 1,
            'guiding_questions' => [
                'What is the main goal of this project?',
                'What problem does this project solve?',
                'How does this project align with organizational strategy?',
                'What does success look like?',
            ],
        ],
        'scope' => [
            'key' => 'scope',
            'label' => 'Scope',
            'description' => 'What is included in and excluded from the project.',
            'position' => 2,
            'guiding_questions' => [
                'What deliverables are included?',
                'What is explicitly out of scope?',
                'What are the key constraints (time, budget, quality)?',
                'What assumptions are we making?',
            ],
        ],
        'stakeholders' => [
            'key' => 'stakeholders',
            'label' => 'Stakeholders',
            'description' => 'Key people and groups affected by or influencing the project.',
            'position' => 3,
            'guiding_questions' => [
                'Who are the project sponsors?',
                'Who are the end users / beneficiaries?',
                'Who needs to be consulted or informed?',
                'Who has decision-making authority?',
            ],
        ],
        'risks' => [
            'key' => 'risks',
            'label' => 'Risks',
            'description' => 'Potential threats and uncertainties that could impact the project.',
            'position' => 4,
            'guiding_questions' => [
                'What could go wrong?',
                'What external factors could impact the project?',
                'What dependencies exist?',
                'What is the impact and likelihood of each risk?',
            ],
        ],
        'milestones' => [
            'key' => 'milestones',
            'label' => 'Milestones',
            'description' => 'Key dates and deliverables marking project progress.',
            'position' => 5,
            'guiding_questions' => [
                'What are the key deadlines?',
                'What deliverables mark each phase?',
                'What are the decision gates?',
                'When is the final delivery date?',
            ],
        ],
        'resources' => [
            'key' => 'resources',
            'label' => 'Resources',
            'description' => 'People, tools, and capabilities needed for the project.',
            'position' => 6,
            'guiding_questions' => [
                'What team members are needed?',
                'What skills and expertise are required?',
                'What tools and infrastructure are needed?',
                'Are there external resources or vendors involved?',
            ],
        ],
        'budget' => [
            'key' => 'budget',
            'label' => 'Budget',
            'description' => 'Financial planning and cost tracking for the project.',
            'position' => 7,
            'guiding_questions' => [
                'What is the total budget?',
                'How is the budget allocated across phases?',
                'What are the major cost drivers?',
                'Is there a contingency reserve?',
            ],
        ],
        'communication' => [
            'key' => 'communication',
            'label' => 'Communication',
            'description' => 'How project information is shared with stakeholders.',
            'position' => 8,
            'guiding_questions' => [
                'How often are status updates provided?',
                'What communication channels are used?',
                'Who receives which information?',
                'How are decisions documented and communicated?',
            ],
        ],
        'governance' => [
            'key' => 'governance',
            'label' => 'Governance',
            'description' => 'Decision-making structures and escalation paths.',
            'position' => 9,
            'guiding_questions' => [
                'Who makes which decisions?',
                'What is the escalation path?',
                'How are changes to scope managed?',
                'What approval processes exist?',
            ],
        ],
    ],

    'billables' => [
        [
            // Pflicht: Das zu überwachende Model
            'model' => \Platform\Planner\Models\PlannerTask::class,

            // Abrechnungsart: Einzelobjekt pro Zeitraum (alternativ: 'flat_fee')
            'type' => 'per_item',

            // Für UI, Listen, Erklärungen:
            'label' => 'Aufgabe',
            'description' => 'Jede erstellte Aufgabe verursacht tägliche Kosten nach Nutzung.',

            // PREISSTAFFELUNG: Ein Array mit mehreren Preisstufen!
            'pricing' => [
                [
                    'cost_per_day' => 0.0025,           // 5 Cent pro Aufgabe pro Tag (alt)
                    'start_date' => '2025-01-01',
                    'end_date' => null,
                ]
            ],

            // Kostenloses Kontingent (z.B. pro Tag)
            'free_quota' => null,               
            'min_cost' => null,               
            'max_cost' => null,              

            // Abrechnung & Zeitraum (idR identisch mit Pricing, aber für UI/Backend)
            'billing_period' => 'daily',      
            // Optional, falls ganzes Billable irgendwann endet
            'start_date' => '2026-01-01',
            'end_date' => null,               

            // Sonderlogik
            'trial_period_days' => 0,         
            'discount_percent' => 0,          
            'exempt_team_ids' => [],          

            // Interne Ordnung/Hilfen
            'priority' => 100,                
            'active' => true,                 
        ],
        [
            // Pflicht: Das zu überwachende Model
            'model' => \Platform\Planner\Models\PlannerProject::class,

            // Abrechnungsart: Einzelobjekt pro Zeitraum (alternativ: 'flat_fee')
            'type' => 'per_item',

            // Für UI, Listen, Erklärungen:
            'label' => 'Projekt',
            'description' => 'Jedes erstellte Projekt verursacht tägliche Kosten nach Nutzung.',

            // PREISSTAFFELUNG: Ein Array mit mehreren Preisstufen!
            'pricing' => [
                [
                    'cost_per_day' => 0.005,           // 5 Cent pro Aufgabe pro Tag (alt)
                    'start_date' => '2025-01-01',
                    'end_date' => null,
                ]
            ],

            // Kostenloses Kontingent (z.B. pro Tag)
            'free_quota' => null,               
            'min_cost' => null,               
            'max_cost' => null,              

            // Abrechnung & Zeitraum (idR identisch mit Pricing, aber für UI/Backend)
            'billing_period' => 'daily',      
            // Optional, falls ganzes Billable irgendwann endet
            'start_date' => '2026-01-01',
            'end_date' => null,               

            // Sonderlogik
            'trial_period_days' => 0,         
            'discount_percent' => 0,          
            'exempt_team_ids' => [],          

            // Interne Ordnung/Hilfen
            'priority' => 100,                
            'active' => true,                 
        ]
    ]
];