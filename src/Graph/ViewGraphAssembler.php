<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewOwnerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;

/**
 * Turns the view map into nodes and edges. Owners that already have a node
 * (Livewire components, routes, controllers, dispatched mail) keep it; the
 * repeated references between two views collapse into one edge listing
 * every line.
 */
final class ViewGraphAssembler
{
    private const REFERENCE_EDGES = [
        ViewReference::TYPE_EXTENDS => EdgeType::ViewExtends,
        ViewReference::TYPE_INCLUDE => EdgeType::ViewIncludes,
        ViewReference::TYPE_COMPONENT => EdgeType::ViewUsesComponent,
        ViewReference::TYPE_LIVEWIRE => EdgeType::ViewRendersLivewire,
        ViewReference::TYPE_DYNAMIC => EdgeType::ViewReferencesDynamic,
    ];

    /**
     * Owners whose node is built by another assembler.
     */
    private const OWNERS_WITH_NODES = [
        ViewOwnerData::TYPE_LIVEWIRE,
        ViewOwnerData::TYPE_ROUTE,
        ViewOwnerData::TYPE_CONTROLLER,
    ];

    public function __construct(
        private readonly NodeFactory $nodes,
        private readonly EdgeFactory $edges,
    ) {}

    /**
     * @return array{0: list<Node>, 1: list<Edge>}
     */
    public function assemble(ViewMapData $views): array
    {
        $nodes = [];
        $edges = [];
        $referenced = [];

        $ownerNodes = [];

        foreach ($views->owners as $owner) {
            if (! in_array($owner->ownerType, self::OWNERS_WITH_NODES, true)) {
                $ownerNodes[] = $owner;
            }

            $grouped = [];

            foreach ($owner->renders as $rendered) {
                $grouped[$rendered->targetId]['hows'][$rendered->how] = true;
                $grouped[$rendered->targetId]['name'] = $rendered->name;

                if ($rendered->method !== null) {
                    $grouped[$rendered->targetId]['methods'][$rendered->method] = true;
                }
            }

            foreach ($grouped as $targetId => $group) {
                $referenced[$targetId] = true;
                $edges[] = $this->edges->http(
                    EdgeType::RendersView,
                    $owner->id,
                    $targetId,
                    implode(', ', array_keys($group['hows'])),
                    [
                        'view' => $group['name'],
                        'hows' => array_keys($group['hows']),
                        'methods' => array_keys($group['methods'] ?? []),
                    ],
                );
            }
        }

        foreach ($views->views as $view) {
            $grouped = [];

            foreach ($view->references as $reference) {
                if ($reference->targetId === null) {
                    continue;
                }

                $key = $reference->type . '|' . $reference->targetId;
                $grouped[$key]['type'] = $reference->type;
                $grouped[$key]['target'] = $reference->targetId;
                $grouped[$key]['directives'][$reference->directive] = true;
                $grouped[$key]['lines'][$reference->line] = true;
            }

            foreach ($grouped as $group) {
                $referenced[$group['target']] = true;
                $edges[] = $this->edges->http(
                    self::REFERENCE_EDGES[$group['type']],
                    $view->id,
                    $group['target'],
                    implode(', ', array_keys($group['directives'])),
                    [
                        'directives' => array_keys($group['directives']),
                        'lines' => array_keys($group['lines']),
                        'file' => $view->file,
                    ],
                );
            }
        }

        foreach ($views->views as $view) {
            $nodes[] = $this->nodes->forView($view, isset($referenced[$view->id]) ? [] : ['No reference found']);
        }

        foreach ($ownerNodes as $owner) {
            // A Blade component only exists through the tags using it.
            $unused = $owner->ownerType === ViewOwnerData::TYPE_BLADE_COMPONENT && ! isset($referenced[$owner->id]);
            $nodes[] = $this->nodes->forViewOwner($owner, $unused ? ['No reference found'] : []);
        }

        foreach ($views->externals as $external) {
            $nodes[] = $this->nodes->forExternalView($external);
        }

        foreach ($views->dynamics as $dynamic) {
            $nodes[] = $this->nodes->forDynamicView($dynamic);
        }

        return [$nodes, $edges];
    }
}
