<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchableData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Turns dispatch references into jobs, mailables and notifications. Jobs
 * are read in turn, so a job dispatching another job is followed until no
 * new class appears.
 */
final class DispatchableCollector
{
    public function __construct(
        private readonly MethodInspector $inspector,
        private readonly DispatchClassifier $classifier,
    ) {}

    /**
     * @param  list<DispatchReference>  $seeds
     * @param  array<string, true>  $eventClasses
     * @return list<DispatchableData>
     */
    public function collect(array $seeds, DiscoveryContext $context, array $eventClasses): array
    {
        $collected = [];
        $queue = $seeds;

        while ($queue !== []) {
            $reference = array_shift($queue);

            if ($reference->kind === DispatchKind::Event || isset($collected[$reference->targetId()])) {
                continue;
            }

            $dispatchable = $this->dispatchable($reference, $context, $eventClasses);
            $collected[$dispatchable->id] = $dispatchable;
            $queue = [...$queue, ...$dispatchable->dispatches];
        }

        ksort($collected, SORT_STRING);

        return array_values($collected);
    }

    /**
     * @param  array<string, true>  $eventClasses
     */
    private function dispatchable(DispatchReference $reference, DiscoveryContext $context, array $eventClasses): DispatchableData
    {
        $dispatches = [];
        $warnings = [];
        $file = null;

        try {
            $path = class_exists($reference->class) ? (new ReflectionClass($reference->class))->getFileName() : false;
            $file = is_string($path) ? PackagePath::relative($path, $context->basePath) : null;
        } catch (Throwable) {
            $file = null;
        }

        if ($reference->kind === DispatchKind::Job && method_exists($reference->class, 'handle')) {
            $findings = $this->inspector->inspect(
                new ReflectionMethod($reference->class, 'handle'),
                $context,
                $eventClasses,
            );

            if (! $findings->readable) {
                $warnings[] = 'Source of [handle] could not be read.';
            }

            $dispatches = $findings->dispatches;
        }

        return new DispatchableData(
            id: $reference->targetId(),
            kind: $reference->kind,
            class: $reference->class,
            file: $file,
            queued: $this->classifier->isQueued($reference->class),
            dispatches: $dispatches,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }
}
