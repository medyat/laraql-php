<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The batch endpoint accepts `{ "queries": { key: Query } }` and answers
    | `{ "results": { key: Result | Error } }`. Add your authentication
    | middleware here; the package never assumes a guard.
    |
    */

    'route' => [
        'path' => 'laraql',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Date presets (today, this_week, …) and day ranges are resolved in the
    | timezone sent by the client in this header; without it `app.timezone`
    | applies. Unknown zone names are rejected with 422.
    |
    */

    'timezone' => [
        'header' => 'X-Timezone',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Hard ceilings enforced by the validator (422 when exceeded).
    |
    */

    'limits' => [
        'page_size' => ['default' => 50, 'max' => 500],
        'filters' => 20,
        'in_values' => 500,
        'sort' => 3,
        'search_tokens' => 10,
        'include' => ['limit' => 100, 'max_limit' => 500, 'depth' => 2],
        'queries_per_request' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Sources are discovered through the #[Source('key')] attribute in the
    | namespaces listed under `discover`; explicit classes may be added too.
    |
    */

    'sources' => [
        'discover' => ['App\\Laraql'],
        'classes' => [],
    ],

];
