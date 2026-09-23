<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum NodeType: string
{
    case Panel = 'panel';
    case Resource = 'resource';
    case LivewireComponent = 'livewire_component';
    case Model = 'model';
    case PolymorphicTarget = 'polymorphic_target';
    case Route = 'route';
    case Controller = 'controller';
    case FormRequest = 'form_request';
    case Policy = 'policy';
    case Event = 'event';
    case Listener = 'listener';
    case Job = 'job';
    case Mailable = 'mailable';
    case Notification = 'notification';
    case View = 'view';
    case BladeComponent = 'blade_component';
    case FilamentComponent = 'filament_component';
    case ExternalView = 'external_view';
    case DynamicView = 'dynamic_view';

    /**
     * Node types that only exist in the HTTP scope.
     *
     * @return list<self>
     */
    public static function httpTypes(): array
    {
        return [
            self::Route,
            self::Controller,
            self::FormRequest,
            self::Policy,
            self::Event,
            self::Listener,
            self::Job,
            self::Mailable,
            self::Notification,
        ];
    }

    public function isHttp(): bool
    {
        return in_array($this, self::httpTypes(), true);
    }

    /**
     * Node types that only exist in the Views scope (and as leaves of the
     * HTTP scope for rendered views).
     *
     * @return list<self>
     */
    public static function viewTypes(): array
    {
        return [
            self::View,
            self::BladeComponent,
            self::FilamentComponent,
            self::ExternalView,
            self::DynamicView,
        ];
    }

    public function isView(): bool
    {
        return in_array($this, self::viewTypes(), true);
    }

    /**
     * Lower values are ordered first when sorting nodes deterministically.
     */
    public function sortPriority(): int
    {
        return match ($this) {
            self::Panel => 0,
            self::Resource => 1,
            self::LivewireComponent => 2,
            self::Model => 3,
            self::PolymorphicTarget => 4,
            self::Route => 5,
            self::Controller => 6,
            self::FormRequest => 7,
            self::Policy => 8,
            self::Event => 9,
            self::Listener => 10,
            self::Job => 11,
            self::Mailable => 12,
            self::Notification => 13,
            self::View => 14,
            self::BladeComponent => 15,
            self::FilamentComponent => 16,
            self::ExternalView => 17,
            self::DynamicView => 18,
        };
    }
}
