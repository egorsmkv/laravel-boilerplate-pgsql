<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenExplainQueriesCommand extends Command
{
    protected $signature = 'app:gen-explain-queries {format}';
    protected $description = 'Generate EXPLAIN ANALYZE queries from Telescope entries.';

    /**
     * @var string[] We ignore SQL queries that contain any of these substrings.
     */
    protected $ignoredSubStrings = [
        'telescope_entries',
        'information_schema.tables',
    ];

    public function handle(): void
    {
        $format = $this->argument('format');

        if ($format === 'json') {
            $prependSQL = 'EXPLAIN (ANALYZE, COSTS, VERBOSE, BUFFERS, FORMAT JSON)';
        } else {
            $prependSQL = 'EXPLAIN (ANALYZE, COSTS, VERBOSE, BUFFERS)';
        }

        DB::table('telescope_entries')->where('type', 'query')->orderBy('sequence')->chunk(50, function (Collection $entries) use ($prependSQL) {
            /** @var \stdClass $entry */
            foreach ($entries as $entry) {
                /** @var array<string> $content */
                $content = json_decode($entry->content, true);

                if (Str::contains($content['sql'], $this->ignoredSubStrings)) {
                    continue;
                }

                $this->info('-- ' . $content['file'] . ':' . $content['line']);
                $this->info($prependSQL . ' ' . $content['sql']);
                $this->comment("\n\n***");
                $this->comment('');
            }
        });
    }
}
