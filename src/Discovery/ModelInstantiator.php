<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnexpectedValueException;

/**
 * Instantiates Eloquent models without touching the database.
 *
 * Instances are memoized for the lifetime of the discovery run so metadata
 * and relation discovery reuse the same object.
 */
final class ModelInstantiator
{
    /** @var array<string, Model> */
    private array $instances = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string  $class
     *
     * @throws Throwable When neither the container nor a direct construction can produce the model.
     */
    public function instantiate(string $class): Model
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        try {
            $instance = $this->container->make($class);
        } catch (Throwable) {
            $instance = new $class;
        }

        if (! $instance instanceof Model) {
            throw new UnexpectedValueException(sprintf('Class [%s] is not an Eloquent model.', $class));
        }

        return $this->instances[$class] = $instance;
    }

    public function flush(): void
    {
        $this->instances = [];
    }
}
