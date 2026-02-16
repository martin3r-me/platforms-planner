<?php

return [
    'routing' => [
        'mode' => env('PLANNER_MODE', 'path'),
        'prefix' => 'planner',
    ],
    'guard' => 'web',

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
    'billables' => [
        [
            // Pflicht: Das zu überwachende Model
            'model' => \Platform\Planner\Models\PlannerTask::class,

            // Abrechnungsart: Einzelobjekt pro Zeitraum (alternativ: 'flat_fee')
            'type' => 'per_item',

            // Für UI, Listen, Erklärungen:
            'label' => 'Planner-Aufgabe',
            'description' => 'Jede erstellte Aufgabe im Planner verursacht tägliche Kosten nach Nutzung.',

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
            'label' => 'Planner-Projekt',
            'description' => 'Jede erstelltes Projekt im Planner verursacht tägliche Kosten nach Nutzung.',

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