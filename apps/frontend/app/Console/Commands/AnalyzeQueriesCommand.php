<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZMQ;
use ZMQContext;
use ZMQSocket;
use ZMQSocketException;

class AnalyzeQueriesCommand extends Command
{
    protected $signature = 'app:analyze-queries';
    protected $description = 'Analyze SQL queries using pg_query_go.';

    public function handle(): void
    {
        try {
            $socket = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_REQ, 'app');

            /** @var string $zmqHost */
            $zmqHost = config('rpc.go_analyze_query');
            $socket = $socket->connect($zmqHost);

            DB::table('telescope_entries')->where('type', 'query')->orderBy('sequence')->chunk(50, function (Collection $entries) use ($socket) {
                /** @var \stdClass $entry */
                foreach ($entries as $entry) {
                    /** @var array<string> $content */
                    $content = json_decode($entry->content, true);

                    $this->info('-- ' . $content['file'] . ':' . $content['line']);
                    $this->info($content['sql']);

                    // Send and receive
                    $send = $socket->send($content['sql']);
                    $result = $send->recv();

                    $data = json_encode(json_decode($result), JSON_PRETTY_PRINT);
                    if (!$data) {
                        $this->error('Failed to decode JSON');
                        $this->error($result);
                        continue;
                    }

                    $this->comment($data);
                    $this->comment('');
                }
            });
        } catch (ZMQSocketException $e) {
            Log::error($e);
        }
    }
}
