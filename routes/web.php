<?php

use Illuminate\Support\Facades\Cache as Cache;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

class Calculator
{
    protected float $high;
    protected float $low;
    protected float $close;

    protected float $pivot;

    public function setHlc(float $high, float $low, float $close)
    {
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;

        $this->pivot = ($high + $low + $close) / 3;
    }

    public function getResistanceOne()
    {
        return $this->pivot * 2 - $this->low;
    }

    public function getSupportOne()
    {
        return $this->pivot * 2 - $this->high;
    }

    public function getResistanceTwo()
    {
        return $this->pivot + ($this->high - $this->low);
    }

    public function getSupportTwo()
    {
        return $this->pivot - ($this->high - $this->low);
    }
}

class Strategy
{

    protected array $indicators;
}

abstract class AbstractIndicator
{

}

class Price
{
    protected $interval;
    protected $ochl;

    /**
     * Price constructor.
     *
     * @param int   $interval Seconds
     * @param array $ochl     Open Close High Low
     */
    public function __construct(int $interval, array $ochl)
    {
        $this->interval = $interval;
        $this->ochl = $ochl;
    }

    public function price()
    {
        $price = $this->fetchLastPrice();
        $this->updateOchl($price);
    }

    protected function fetchLastPrice(): float
    {
        return 1;
    }

    protected function calculateNextCandleClose()
    {

    }

    protected function updateOchl(float $price): void
    {
        $key = array_key_last($this->ochl);

        $last = &$this->ochl[$key];
        $prev = &$this->ochl[$key - 1];

        if ($last['time'] < $prev['time'] + $this->interval)
        {
            $last['price'] = $price;

            if ($last['high'] < $price) $last['high'] = $price;
            else if ($last['low'] > $price) $last['low'] = $price;
        }
        else
        {
            $last = $this->fetchOchl($prev['time']);
        }

        //TODO:: Update indicator
    }

    protected function fetchOchl(int $start): array
    {
        return [];
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getOchl(): array
    {
        return $this->ochl;
    }
}

class OrderBook
{

}

class DecisionMaker
{
    //amounts
    protected float $cash;
    protected float $item;

    //various prices
    protected float $cost;

    protected float $netProfit;

    protected float $profit = 0;
    protected float $loss = 0;

    //chart data
    protected array $chart = [];

    //current
    protected float $price;
    protected float $currentWorth;

    //ratios
    protected float $sell;
    protected float $stop;

    public function __construct(float $cash, float $sellRatio, float $stopRatio)
    {
        $this->cash = $cash;
        $this->sell = $sellRatio;
        $this->stop = $stopRatio;
    }

    public function inCash()
    {
        if ($this->cash) return true;
        return false;
    }

    public function sell(int $time, $amount = null): void
    {
        if ($profit = $this->isProfiting())
        {
            $this->profit += $this->calculateProfit();
        }
        else
        {
            $this->loss += $this->calculateLoss();
        }

        $this->assertItem();
        $this->cash = $this->item * $this->price;
        $this->cost = 0;
        $this->item = 0;

        $this->netProfit = $this->profit - $this->loss;

        $action = $profit ? 'sell' : 'stop';
        $this->chart[$action][] = $this->createChartPoint($time, $this->price);
        $this->chart['profit'][] = $this->createChartPoint($time, $this->profit - $this->loss);
    }

    public function createChartPoint(int $time, float $value): array
    {
        return ['time' => $time, 'value' => $value];
    }

    public function buy(int $time, $amount = null): void
    {
        $this->assertCash();

        $this->item = $this->cash / $this->price;
        $this->cash = 0;
        $this->cost = $this->price;

        $this->chart['buy'][] = $this->createChartPoint($time, $this->price);
    }

    public function determineBuyOrSell()
    {

    }

    protected function assertCash(): void
    {
        if (!$this->cash)
            throw new Exception("No cash to buy.");
    }

    protected function assertItem(): void
    {
        if (!$this->item)
        {
            dump($this);
            throw new Exception("There is no item to proceed further.");
        }
        if (!$this->cost)
            throw new LogicException('Cost is not defined.');
    }

    protected function isLosing(): bool
    {
        $this->assertItem();
        return $this->price <= $this->cost;
    }

    public function isProfiting(): bool
    {
        $this->assertItem();
        return $this->price >= $this->cost;
    }

    protected function calculateLoss(): float
    {
        if (!$this->isLosing())
            throw new Exception('Not losing at the moment.');
        return $this->item * $this->cost - $this->item * $this->price;
    }

    protected function calculateProfit(): float
    {
        if (!$this->isProfiting())
            throw new Exception('Not profiting at the moment.');
        return $this->item * $this->price - $this->getTotalCost();
    }

    protected function getTotalCost(): float
    {
        $this->assertItem();
        return $this->item * $this->cost;
    }

    protected function getSellingPrice(): float
    {
        return $this->cost + $this->cost * $this->sell / 100;
    }

    protected function getStopPrice(): float
    {
        return $this->cost - $this->cost * $this->stop / 100;
    }

    protected function shouldLong(): bool
    {
        return true;
    }

    protected function shouldShort(): bool
    {
        return true;
    }

    public function shouldBuy(): bool
    {
        $this->assertCash();

        return $this->shouldLong();
    }

    public function shouldSell(): bool
    {
        if ($this->isProfiting())
            return $this->price >= $this->getSellingPrice();

        return $this->shouldStop();
    }

    public function shouldStop()
    {
        $res = $this->isLosing() && $this->calculateLoss() >= $this->getTotalCost() * $this->stop / 100;
        return $res;
    }

    public function shouldTakeProfit()
    {

    }

    /**
     * @return float
     */
    public function getCash(): float
    {
        return $this->cash;
    }

    protected function getMarkerTemplate(string $action)
    {
        $template = [
            'position' => 'belowBar',
            'color' => 'red',
            'shape' => 'arrowUp',
            'text' => ucfirst($action),
            'size' => 2,
        ];

        switch ($action)
        {
            case 'buy':
                $template['color'] = 'green';
                break;
            case 'sell':
            case 'stop':
                break;
            default:
                throw new \InvalidArgumentException('Invalid action.');
        }

        return $template;
    }

    public function getChart(): array
    {
        $chart = [];

        foreach ($this->chart as $action => $points)
        {
            if (in_array($action, ['buy', 'sell', 'stop']))
                foreach ($points as $point)
                    $chart['markers'][] = array_merge($this->getMarkerTemplate($action), $point);
            else
                $chart[$action] = $points;
        }

        if ($chart['markers'] ?? null)
            usort($chart['markers'], fn($a, $b) => $a['time'] <=> $b['time']);

        return $chart;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;

        if (!$this->inCash()) $this->currentWorth = $this->price * $this->item;
    }

    public function getProfit(): float
    {
        return $this->profit;
    }

    public function getLoss(): float
    {
        return $this->loss;
    }
}

Route::get('/',
    function () {
        ini_set('memory_limit', -1);
        ini_set('trader.real_precision', 2);

        echo '<script src="trading-view.js"></script>';

        $btcCandle = new \App\Console\Commands\CandleBtcUsd(false);
        $btcCandle->timeFrame = '1m';
        $data = (array)$btcCandle->getCache(1);
//        $data = array_slice($data, -2880 / 4, 2880 / 4);

        foreach ($data as $ochl)
        {
            $chart['candles'][] = [
                'time' => $ochl[0] / 1000,
                'open' => $ochl[1],
                'close' => $ochl[2],
                'high' => $ochl[3],
                'low' => $ochl[4]
            ];

            $closes[$ochl[0] / 1000] = $ochl[2];
            $time[] = $ochl[0] / 1000;
        }

        $timeFrame = 14;
        /** @noinspection PhpUndefinedFunctionInspection */
        $rsi = trader_rsi($closes, $timeFrame);
        /** @noinspection PhpUndefinedFunctionInspection */
        $macd = trader_macd($closes, 12, 26, 9);

        foreach ($rsi as $k => $val)
        {
            $chart['rsi'][] = ['time' => $time[$k], 'value' => $val];
        }

        foreach ($macd as $k => $macdData)
        {
            foreach ($macdData as $_k => $val)
            {
                $chart['macd'][$k][] = ['time' => $time[$_k], 'value' => $val];
            }
        }

        $cash = 1000;
        $stop = 5;
        $rsiApprove = false;

        $maker = new DecisionMaker($cash, $stop * 2, $stop);

        $data = array_values($data);
        foreach ($data as $key => $ochl)
        {
            if (!isset($prev))
            {
                $prev = $ochl;
                continue;
            }

//            $price = mt_rand($ochl[4], $ochl[3]);
            $price = $ochl[2];
            $time = $ochl[0] / 1000;
            $rsiVal = $key >= $timeFrame ? $rsi[$key] : 0;

            $maker->setPrice($price);

//            if ($rsiVal)
//            {
//                if ($maker->inCash())
//                {
//                    if ($rsiVal <= 30)
//                    {
//                        $maker->buy($time);
//                    }
//                }
//                else
//                {
//                    if ($rsiVal >= 70)
//                    {
//                        $maker->sell($time);
//                    }
//                }
//            }

            //TODO:: MACD uyumsuzluğu
            //TODO:: Hareketli ortalama
            //TODO:: Fibbonacci


            if (isset($macd[0][$key]))
            {
                $macdVal = ceil($macd[0][$key]);
                $signalVal = ceil($macd[1][$key]);

                if (abs($macdVal - $signalVal) <= 0.99)
                {
                    if (!$maker->inCash() && $macdVal > 0)
                    {
                        if (!$rsiApprove || $rsiApprove && $rsiVal >= 70)
                            $maker->sell($time);
                    }
                    else if ($maker->inCash() && $macdVal < 0)
                    {
                        if (!$rsiApprove || $rsiApprove && $rsiVal < 50)
                            $maker->buy($time);
                    }
                }
            }

//            if ($maker->inCash() && $maker->shouldBuy())
//            {
//                $maker->buy($time);
//            }
//            else
//            {
//                if (!$maker->inCash() && ($maker->shouldSell() || $maker->shouldStop()))
//                {
//                    $maker->sell($time);
//                }
//            }
            $prev = $ochl;
        }

        if (!$maker->inCash()) $maker->sell($time);
        dump($maker);

        $chartData = array_merge($chart, $maker->getChart());

        echo '<script> chartData = ' . json_encode($chartData) . '</script>';
//        echo '<script> chartData = ' . json_encode($chart) . '</script>';
        echo "<script src='app.js'></script>";

//        $cashIn = $cash;
//        $precision = 0;
//        $net = number_format(round($maker->getCash() / $cash, 2), 1);
//        $profit = number_format(round($maker->getProfit(), $precision));
//        $loss = number_format(round($maker->getLoss(), $precision));
//        $cash = number_format(round($maker->getCash(), $precision));
//        echo "Cash in: <b>$cashIn</b> -
//              Cash out: <b>$cash</b> -
//              Profit: <b>$profit</b> -
//              Loss: <b>$loss</b> -
//              Net: <b>$net</b>";
    });
