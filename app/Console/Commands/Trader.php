<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use function Ratchet\Client\connect;

class Trader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trader:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch trader.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        connect(url: 'wss://fstream.binance.com/stream?streams=btcusdt@aggTrade')
            ->then(function (\Ratchet\Client\WebSocket $conn) {
                $conn->on('message', function (\Ratchet\RFC6455\Messaging\Message $msg) use ($conn) {
                    $data = (array)json_decode($msg->getPayload(), true);

                    var_dump($data);
//                     $prices[] = $data['data']['p'];
                });

            }, function ($e) {
                echo "Could not connect: {$e->getMessage()}\n";
            });

        return 0;
    }
}
