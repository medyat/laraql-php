<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Source\Source;

/** A validated query, ready to compile. Built only by the Validator. */
final readonly class Query
{
    /**
     * @param  'rows'|'count'  $kind
     * @param  list<Column>|null  $fields  null = every column of the source
     * @param  list<Condition>  $filters
     * @param  list<Sort>  $sort
     * @param  list<string>  $searchTokens
     * @param  array<string, IncludeQuery>  $include
     * @param  list<string>  $options
     */
    public function __construct(
        public Source $source,
        public string $kind,
        public ?array $fields,
        public array $filters,
        public array $sort,
        public Page $page,
        public array $searchTokens,
        public array $include,
        public array $options,
    ) {}
}
