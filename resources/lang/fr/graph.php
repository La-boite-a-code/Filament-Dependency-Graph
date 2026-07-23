<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Graphe de dependances',
    'title' => 'Graphe de dependances',

    'toolbar' => [
        'scope' => 'Portee',
        'scope_filament' => 'Filament',
        'scope_laravel' => 'Laravel',
        'view_graph' => 'Graphe',
        'view_tree' => 'Arbre',
        'view_table' => 'Table',
        'search_placeholder' => 'Rechercher modeles, resources, panels...',
        'export' => 'Exporter',
        'export_json' => 'Exporter en JSON',
        'export_mermaid' => 'Exporter en Mermaid',
        'reset' => 'Reinitialiser',
    ],

    'explorer' => [
        'title' => 'Explorateur',
        'panels' => 'Panels',
        'node_types' => 'Types de noeuds',
        'relation_types' => 'Types de relations',
        'filters' => 'Filtres',
        'namespace' => 'Namespace contient',
        'ownership' => 'Provenance',
        'ownership_all' => 'Tous les modeles',
        'ownership_application' => 'Modeles applicatifs',
        'ownership_vendor' => 'Modeles vendor',
        'show_orphans' => 'Afficher les modeles orphelins',
        'only_orphans' => 'Orphelins uniquement',
        'only_cycles' => 'Dependances circulaires uniquement',
        'only_without_resource' => 'Modeles sans resource uniquement',
        'focus' => 'Focus',
        'focus_depth' => 'Profondeur',
        'focus_depth_unlimited' => 'Illimitee',
        'focus_direction' => 'Direction',
        'direction_incoming' => 'Entrantes',
        'direction_outgoing' => 'Sortantes',
        'direction_both' => 'Les deux',
        'exit_focus' => 'Quitter le focus',
    ],

    'node_types' => [
        'panel' => 'Panels',
        'resource' => 'Resources',
        'model' => 'Modeles',
        'polymorphic_target' => 'Cibles polymorphes',
    ],

    'workspace' => [
        'empty' => 'Aucun noeud ne correspond aux filtres actuels.',
        'error' => 'Le graphe n\'a pas pu etre construit :',
        'stats' => ':nodes noeuds, :edges liens',
        'layout' => 'Disposition',
        'layout_hierarchical' => 'Hierarchique',
        'layout_force' => 'Force dirigee',
        'fit' => 'Ajuster a la vue',
    ],

    'inspector' => [
        'title' => 'Inspecteur',
        'close' => 'Fermer l\'inspecteur',
        'focus_node' => 'Focus sur ce noeud',
        'empty_section' => 'Rien a afficher.',
    ],

    'search' => [
        'no_results' => 'Aucun resultat.',
    ],

    'tree' => [
        'already_shown' => 'deja affiche',
        'empty' => 'Rien a afficher avec les filtres actuels.',
    ],

    'table' => [
        'models' => 'Modeles',
        'model' => 'Modele',
        'namespace' => 'Namespace',
        'database_table' => 'Table',
        'resources_count' => 'Resources',
        'outgoing' => 'Sortantes',
        'incoming' => 'Entrantes',
        'soft_deletes' => 'SoftDeletes',
        'status' => 'Statut',
        'relations' => 'Relations',
        'source' => 'Modele source',
        'method' => 'Methode',
        'type' => 'Type',
        'target' => 'Modele cible',
        'foreign_key' => 'Cle etrangere',
        'pivot' => 'Pivot',
        'nullable' => 'Nullable',
        'resources' => 'Resources',
        'resource' => 'Resource',
        'panels' => 'Panels',
        'navigation_group' => 'Groupe de navigation',
        'pages' => 'Pages',
        'relation_managers' => 'Relation managers',
        'yes' => 'Oui',
        'no' => 'Non',
        'unknown' => 'Inconnu',
    ],
];
