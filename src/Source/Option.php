<?php

declare(strict_types=1);

namespace Medyat\Laraql\Source;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Medyat\Laraql\Input;

/**
 * A named, server-side behaviour a source declares; clients send its name in
 * `options`. Three kinds: narrow the row set, eager-load a relation, add keys
 * to each row. Closures receive the query Input after their usual arguments.
 */
final readonly class Option
{
    public const string SCOPE = 'scope';

    public const string LOAD = 'load';

    public const string DECORATE = 'decorate';

    /**
     * @param  Closure(Builder<Model>, Input): void|null  $scope
     * @param  Closure(Model, array<string, mixed>, Input): array<string, mixed>|null  $decorator
     */
    private function __construct(
        public string $kind,
        public ?string $description = null,
        public ?Closure $scope = null,
        public ?string $relation = null,
        public ?Closure $decorator = null,
    ) {}

    /** @param  Closure(Builder<Model>, Input): void  $scope */
    public static function scope(Closure $scope, ?string $description = null): self
    {
        return new self(self::SCOPE, $description, scope: $scope);
    }

    public static function load(string $relation, ?string $description = null): self
    {
        return new self(self::LOAD, $description, relation: $relation);
    }

    /** @param  Closure(Model, array<string, mixed>, Input): array<string, mixed>  $decorator */
    public static function decorate(Closure $decorator, ?string $description = null): self
    {
        return new self(self::DECORATE, $description, decorator: $decorator);
    }
}
