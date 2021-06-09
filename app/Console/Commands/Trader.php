<?php

namespace App\Console\Commands;

use App\Trade\Indicator\FibonacciRetracement;
use App\Trade\Indicator\RSI;
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
                $conn->on('message', function (\Ratchet\RFC6455\Messaging\Message $msg) {
                    $data = (array)json_decode($msg->getPayload(), true);

                    static $count = 0;
                    static $start = 0;
                    static $finish = 0;

                    if ($start === 0)
                    {
                        $start = time();
                        $finish = $start + 60;
                    }

                    if ($finish == time())
                    {
                        $count = 0;
                        $start = 0;
                        $finish = 0;

                    }
                    $count++;
                    echo $data['data']['p'], " - {$count}\n";

                });
            }, function ($e) {
                echo "Could not connect: {$e->getMessage()}\n";
            });

        return 0;
    }
}
