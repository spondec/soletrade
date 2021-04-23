<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache as Cache;

class CandleBtcUsd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'candle:btcusd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch BTCUSDT candles.';

    protected string $url = 'https://api-pub.bitfinex.com/v2/';

    protected string $symbol = 'tBTCUSD';
    public string $timeFrame = '1m';
    protected string $section = 'hist';

    /**
     * https://docs.bitfinex.com/reference#rest-public-candles
     *
     * Response Details
     *
     * Fields    Type    Description
     * MTS        int    millisecond time stamp
     * OPEN     float    First execution during the time frame
     * CLOSE    float    Last execution during the time frame
     * HIGH     float    Highest execution during the time frame
     * LOW      float    Lowest execution during the timeframe
     * VOLUME   float    Quantity of symbol traded within the timeframe
     */
    protected array $data = [];

    protected int $start;
    protected int $limit = 10000;
    protected int $sort = 1;

    protected float $year = 1/365;

    protected \DateTime $dateTime;
    protected Client $httpClient;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(bool $construct = true)
    {
        if ($construct)
            parent::__construct();

        $this->dateTime = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        $this->httpClient = new Client();
        $this->start = (time() - 86400 * 365 * $this->year) * 1000;
        $this->data = (array)$this->getCache();
    }

    protected function save()
    {
        Cache::put($this->symbol, $this->data);
    }

    public function getPathParams()
    {
        return "candles/trade:$this->timeFrame:$this->symbol/$this->section";
    }

    public function getCache(bool $withPathParams = false): ?array
    {
        $cache = Cache::get($this->symbol);

        if ($withPathParams) return $cache[$this->getPathParams()] ?? null;

        return $cache;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', -1);

        $pathParams = $this->getPathParams();
        $startDate = $this->start;

        $this->data[$pathParams] = [];
        $data = &$this->data[$pathParams];

        while (true)
        {
            if ($data) $startDate = end($data)[0];

            $result = $this->httpClient->request('get', $this->url . $pathParams, [
                    'query' => [
                        'start' => $startDate,
                        'limit' => $this->limit,
                        'sort' => $this->sort
                    ]
                ]
            );

            if ($result->getStatusCode() == 200)
            {
                if ($newData = (array)json_decode($result->getBody()->getContents()))
                {
                    if (end($data) == ($last = end($newData)))
                    {
                        foreach ($data as $ochl)
                            $keyed[$ochl[0]] = $ochl;

                        ksort($keyed);

                        $this->data[$pathParams] = $keyed;
                        $this->save();
                        break;
                    }

                    $startDate = $last[0];

                    echo $this->dateTime
                        ->setTimestamp($startDate / 1000)
                        ->format(\DateTimeInterface::RSS), PHP_EOL;

                    $this->data[$pathParams] = array_merge($data, $newData);
                    $this->save();
                }
                else
                {
                    echo 'No further data received.';
                    break;
                }
            }
            else
            {
                echo $result->getStatusCode();
                break;
            }

        }

        return 0;
    }
}
