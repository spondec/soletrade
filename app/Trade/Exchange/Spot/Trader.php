<?php

namespace App\Trade\Exchange\Spot;

class Trader
{
    protected string $symbol;

    protected array $positions;

    const MAX_LEVERAGE = 20;
    //amounts
    protected float $cash;
    protected float $item;

    protected float $commissionRate = 0.00075;

    protected float $netProfit;

    protected float $profit = 0;
    protected float $loss = 0;
    protected float $paidCommission = 0;

    //chart data
    protected array $chart = [];

    //current
    protected float $price;
    protected float $currentWorth;

    //ratios
    protected float $sell;
    protected float $stop;

    protected AbstractExchange $exchange;

    protected function getOpenPositions()
    {

    }

    public function __construct(AbstractExchange $exchange)
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

    public function takeProfit()
    {

    }

    public function stopLoss()
    {

    }

    public function closePosition(int $time, $amount = null): void
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

        $this->item = $this->cutCommission($this->item, $this->price);
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

    protected function cutCommission(float $item, float $price = null)
    {
        $commission = $item * $this->commissionRate;
        $this->paidCommission += $price ? $commission * $price : $commission;

        return $item - $commission;
    }

    public function buy(int $time, $amount = null): void
    {
        $this->assertCash();

        $this->cash = $this->cutCommission($this->cash);

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
            throw new \Exception("There is no item to proceed further.");
        }
        if (!$this->cost)
            throw new \LogicException('Cost is not defined.');
    }

    protected function isLosing(): bool
    {
        $this->assertItem();
        return $this->price <= $this->cost;
    }

    public function isProfiting(?float $ratio = null): bool
    {
        $this->assertItem();

        return $this->price > $this->cost && (!$ratio || $this->price > $this->cost * $ratio / 100 + $this->cost);
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

    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }

    protected function determineLeverageLevelFromVolatility()
    {

    }

    public function setLeverage(int $leverage): void
    {
        if ($leverage > self::MAX_LEVERAGE && $leverage < 1)
        {
            throw new \InvalidArgumentException('Leverage should be between 1 and 20.');
        }

        $this->leverage = $leverage;
    }

    protected function getStopPrice(): float
    {
        return $this->cost - $this->cost * $this->stop / 100;
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
            'color'    => 'red',
            'shape'    => 'arrowUp',
            'text'     => ucfirst($action),
            'size'     => 2,
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

    public function updatePrice(float $price): void
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
