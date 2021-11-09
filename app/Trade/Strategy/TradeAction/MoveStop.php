<?php

namespace App\Trade\Strategy\TradeAction;

use App\Trade\Evaluation\Price;

class MoveStop extends AbstractTradeActionHandler
{
    protected array $config = [
        'target'         => [
            'price' => null,
            'roi'   => null
        ],
        'new_stop_price' => null,
        'lock'           => true
    ];

    protected array $required = [
        'new_stop_price',
        'target',
    ];

    protected Price $stop;

    protected function setup(): void
    {
        $this->stop = $this->position->price('stop');

        if ($this->config('target.price') && $this->config('target.roi'))
        {
            throw new \UnexpectedValueException(
                'Both target price and target ROI have been defined. 
                Only one target definition is allowed.');
        }
    }

    protected function applyLocks(): void
    {
        $this->lockIfUnlocked($this->stop, $this);
    }

    protected function stopIfShould(\stdClass $candle): void
    {
        if (!$this->position->isOpen())
        {
            return;
        }

        $stopPrice = $this->stop->get();

        if ($this->position->isBuy())
        {
            if ($candle->c <= $stopPrice)
            {
                $this->setStop($candle->c, 'The stop price is missed. Stopping at close price.');
                $this->position->stop($candle->t);
            }
        }
        else if ($candle->c >= $stopPrice)
        {
            $this->setStop($candle->c, 'The stop price is missed. Stopping at close price.');
            $this->position->stop($candle->t);
        }
    }

    protected function setStop(float $price, string $reason): void
    {
        if ($this->stop->isLocked())
        {
            $this->stop->unlock($this);
        }

        $this->stop->set($price, $this->prepareReason($reason));
    }

    protected function performAction(\stdClass $candle): bool
    {
        $newStop = $this->config('new_stop_price');

        if ($targetPrice = $this->config('target.price'))
        {
            if ($this->position->isBuy())
            {
                if ($candle->h >= $targetPrice)
                {
                    $this->setStop($newStop, "Move stop to $newStop if the price is higher than or equal to $targetPrice");
                    $this->stopIfShould($candle);
                    return true;
                }
            }
            else
            {
                if ($candle->l <= $targetPrice)
                {
                    $this->setStop($newStop, "Move stop to $newStop if the price is lower than or equal to $targetPrice");
                    $this->stopIfShould($candle);
                    return true;
                }
            }
        }
        else if ($targetRoi = $this->config('target.roi'))
        {
            if ($this->position->isBuy())
            {
                if ($this->position->roi($candle->h) >= $targetRoi)
                {
                    $this->setStop($newStop, "Move stop to $newStop if the ROI is higher than or equal to %$targetRoi");
                    $this->stopIfShould($candle);
                    return true;
                }
            }
            else
            {
                if ($this->position->roi($candle->l) <= $targetRoi)
                {
                    $this->setStop($newStop, "Move stop to $newStop if the ROI is lower than or equal to %$targetRoi");
                    $this->stopIfShould($candle);
                    return true;
                }
            }
        }

        return false;
    }
}