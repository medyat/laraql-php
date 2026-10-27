<?php

declare(strict_types=1);

namespace Medyat\Laraql\Source;

/**
 * A relation a source exposes to `include`. The related side is itself a
 * source, so its whitelist and authorization apply.
 */
final readonly class Relation
{
    /**
     * @param  class-string<Source>  $source
     * @param  string  $foreignKey  Column id on the related source pointing at the parent.
     * @param  string|null  $localKey  Column id on the parent; defaults to its key.
     */
    private function __construct(
        public string $source,
        public string $foreignKey,
        public ?string $localKey = null,
        public ?string $description = null,
    ) {}

    /** @param  class-string<Source>  $source */
    public static function hasMany(string $source, string $foreignKey, ?string $localKey = null, ?string $description = null): self
    {
        return new self($source, $foreignKey, $localKey, $description);
    }
}
