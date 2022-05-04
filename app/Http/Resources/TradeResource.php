<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $trade = parent::toArray($request);

        $trade['roi'] = \round($trade['roi'], 2);
        $trade['relative_roi'] = \round($trade['relative_roi'], 2);

        return $trade;
    }
}
