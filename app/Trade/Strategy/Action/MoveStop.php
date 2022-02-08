<?php

namespace App\Trade\Strategy\Action;

use App\Trade\Evaluation\Price;

class MoveStop extends Handler
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

    protected function stopIfShould(\stdClass $candle, int $priceDate): void
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
                $this->setStop($candle->c, $priceDate, 'The stop price was missed. Stopping at close price.');
                $this->position->stop($priceDate);
            }
        }
        else if ($candle->c >= $stopPrice)
        {
            $this->setStop($candle->c, $priceDate, 'The stop price was missed. Stopping at close price.');
            $this->position->stop($priceDate);
        }
    }

    protected function setStop(float $price, int $timestamp, string $reason): void
    {
        if ($this->stop->isLocked())
        {
            $this->stop->unlock($this);
        }

        $this->stop->set($price, $timestamp, $this->prepareReason($reason));
    }

    protected function performAction(\stdClass $candle, int $priceDate): bool
    {
        $newStop = $this->config('new_stop_price');

        if ($targetPrice = $this->config('target.price'))
        {
            if ($this->position->isBuy())
            {
                if ($candle->h >= $targetPrice)
                {
                    $this->setStop($newStop, $priceDate, "Move stop to $newStop if the price is higher than or equal to $targetPrice");
                    $this->stopIfShould($candle, $priceDate);
                    return true;
                }
            }
            else
                if ($candle->l <= $targetPrice)
                {
                    $this->setStop($newStop, $priceDate, "Move stop to $newStop if the price is lower than or equal to $targetPrice");
                    $this->stopIfShould($candle, $priceDate);
                    return true;
                }
        }
        else if ($targetRoi = $this->config('target.roi'))
        {
            if ($this->position->isBuy())
            {
                if ($this->position->roi($candle->h) >= $targetRoi)
                {
                    $this->setStop($newStop, $priceDate, "Move stop to $newStop if the ROI is higher than or equal to %$targetRoi");
                    $this->stopIfShould($candle, $priceDate);
                    return true;
                }
            }
            else
                if ($this->position->roi($candle->l) >= $targetRoi)
                {
                    $this->setStop($newStop, $priceDate, "Move stop to $newStop if the ROI is higher than or equal to %$targetRoi");
                    $this->stopIfShould($candle, $priceDate);
                    return true;
                }
        }

        return false;
    }
}