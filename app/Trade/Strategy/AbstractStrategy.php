<?php

namespace App\Trade\Strategy;

//TODO:: düşüşlerden destek bulma, hangi indikatörlerin desteği başarılı vs.
//TODO:: kısa vadede en güzel destek nedir?
//TODO:: Fib çizgisinin hemen üstünde ya da altında destek/direnç var mı?

//TODO:: saatlik/4 saatlik destekten al, kırarsa sat? supertrend
//TODO::

//TODO:: dirençten short stratejisi: eğer güçlü bir direnç ise shortta kal, değilse scalp ile yetin
//TODO:: fundamental haber geldiğinde paritedeki artış son 10 mumun artış ortalamasından yüksekse işleme gir

use App\Trade\Indicator\AbstractIndicator;

class AbstractStrategy
{
    public function __construct(protected array $indicators)
    {
        foreach ($this->indicators as $indicator)
        {
            if (!$indicator instanceof AbstractIndicator)
            {
                throw new \InvalidArgumentException('Indicator type is invalid.');
            }
        }
    }
}