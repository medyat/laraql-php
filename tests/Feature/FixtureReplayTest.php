<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

/**
 * Every fixture in fixtures/v1 is replayed against the workbench sources:
 * the request goes through the real route and the answer must equal the
 * fixture body exactly. The fixtures are the contract; a mismatch here is a
 * contract question, not a reason to edit the fixture.
 */
dataset('replay', function (): array {
    $files = array_filter(glob(__DIR__.'/../../fixtures/v1/*.json') ?: [], fn (string $f): bool => basename($f) !== 'dataset.json');

    return array_combine(array_map(fn (string $f): string => basename($f, '.json'), $files), $files);
});

it('replays the fixture', function (string $file): void {
    $fixture = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    $this->seed(DatabaseSeeder::class);
    Carbon::setTestNow(CarbonImmutable::parse($fixture['context']['now']));

    $dataset = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/v1/dataset.json'), true, 512, JSON_THROW_ON_ERROR);
    $name = array_key_exists('user', $fixture['context']) ? $fixture['context']['user'] : 'admin';
    $record = collect($dataset['users'])->firstWhere('name', $name);

    $request = $this;

    if ($name !== null) {
        $user = new User(['name' => $name]);
        $user->id = $record['id'] ?? null;
        $request = $this->actingAs($user);
    }

    $request
        ->withHeader('X-Timezone', $fixture['context']['timezone'])
        ->postJson('/laraql', $fixture['request'])
        ->assertStatus($fixture['response']['status'])
        ->assertExactJson($fixture['response']['body']);
})->with('replay');
