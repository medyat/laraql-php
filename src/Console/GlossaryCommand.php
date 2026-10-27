<?php

declare(strict_types=1);

namespace Medyat\Laraql\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Medyat\Laraql\Glossary\Glossary;

/**
 * Writes the glossary pages MCP resources serve. `--check` compares instead
 * of writing and fails when a page is missing or stale, for CI.
 */
final class GlossaryCommand extends Command
{
    protected $signature = 'laraql:glossary
        {--path= : Output directory (default: config laraql.glossary.path, else resources/laraql/glossary)}
        {--check : Fail when the files on disk differ from what would be generated}';

    protected $description = 'Generate the protocol and per-source glossary pages from the registered sources.';

    public function handle(Glossary $glossary, Filesystem $files): int
    {
        $path = $this->path();
        $stale = [];

        foreach ($glossary->pages() as $name => $content) {
            $file = $path.DIRECTORY_SEPARATOR.$name;

            if ($this->option('check')) {
                if (! $files->exists($file) || $files->get($file) !== $content) {
                    $stale[] = $name;
                }

                continue;
            }

            $files->ensureDirectoryExists($path);
            $files->put($file, $content);
            $this->line("Wrote {$file}");
        }

        if ($stale !== []) {
            $this->error('Glossary is stale: '.implode(', ', $stale).'. Run `php artisan laraql:glossary`.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('Glossary is up to date.');
        }

        return self::SUCCESS;
    }

    private function path(): string
    {
        $option = $this->option('path');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $configured = config('laraql.glossary.path');

        return is_string($configured) && $configured !== '' ? $configured : resource_path('laraql/glossary');
    }
}
