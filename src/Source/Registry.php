<?php

declare(strict_types=1);

namespace Medyat\Laraql\Source;

use Illuminate\Contracts\Container\Container;
use LogicException;
use Medyat\Laraql\Exceptions\UnknownSourceException;

/**
 * Source instances by name. Sources are registered at boot (config
 * `laraql.sources` or `register()`), resolved once through the container.
 */
final class Registry
{
    /** @var array<string, Source> */
    private array $byName = [];

    /** @var array<class-string<Source>, Source> */
    private array $byClass = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<Source>  ...$classes */
    public function register(string ...$classes): void
    {
        foreach ($classes as $class) {
            $source = $this->ofClass($class);
            $this->byName[$source->name()] = $source;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }

    public function get(string $name): Source
    {
        return $this->byName[$name] ?? throw new UnknownSourceException($name);
    }

    /**
     * The source behind a relation; registered or not, the class decides.
     *
     * @param  class-string<Source>  $class
     */
    public function ofClass(string $class): Source
    {
        if (! isset($this->byClass[$class])) {
            $source = $this->container->make($class);

            if (! $source instanceof Source) {
                throw new LogicException("[{$class}] is not a laraql source.");
            }

            $this->byClass[$class] = $source;
        }

        return $this->byClass[$class];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->byName);
    }
}
