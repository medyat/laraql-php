<?php

declare(strict_types=1);

namespace Medyat\Laraql;

/**
 * What a source's hooks see of one query: the batch context plus the
 * validated params and the ids of the columns the answer will carry.
 * Includes run with the parent's context and no params.
 */
final readonly class Input extends Context
{
    /**
     * @param  array<string, mixed>  $params  Values keyed by the names `Source::params()` declares.
     * @param  list<string>  $fields  Output column ids, key first; empty for `count`.
     */
    public function __construct(Context $context, public array $params = [], public array $fields = [])
    {
        parent::__construct($context->timezone, $context->now, $context->user);
    }
}
