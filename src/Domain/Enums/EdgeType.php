<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum EdgeType: string
{
    case PanelRegistersResource = 'panel_registers_resource';
    case ResourceUsesModel = 'resource_uses_model';
    case LivewireUsesModel = 'livewire_uses_model';
    case ModelRelation = 'model_relation';
}
