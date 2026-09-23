<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum EdgeType: string
{
    case PanelRegistersResource = 'panel_registers_resource';
    case ResourceUsesModel = 'resource_uses_model';
    case LivewireUsesModel = 'livewire_uses_model';
    case ModelRelation = 'model_relation';
    case RouteHandledByController = 'route_handled_by_controller';
    case RouteRendersLivewire = 'route_renders_livewire';
    case ControllerValidatesWith = 'controller_validates_with';
    case ControllerUsesModel = 'controller_uses_model';
    case ModelGuardedByPolicy = 'model_guarded_by_policy';
    case EventHandledByListener = 'event_handled_by_listener';
    case Dispatches = 'dispatches';
    case RendersView = 'renders_view';
    case ViewExtends = 'view_extends';
    case ViewIncludes = 'view_includes';
    case ViewUsesComponent = 'view_uses_component';
    case ViewRendersLivewire = 'view_renders_livewire';
    case ViewReferencesDynamic = 'view_references_dynamic';
}
