<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Trade\Exchange\Exchange;
use App\Trade\Repository\ConfigRepository;
use Illuminate\Support\Collection;

class ExchangeController extends Controller
{
    public function __construct(protected ConfigRepository $configRepo)
    {
    }

    public function index(): Collection
    {
        return collect($this->configRepo->exchanges)
            ->map(fn (string|Exchange $e) => $e::instance())
            ->filter(fn (Exchange $e) => $e->hasApiAccess())
            ->map(fn (Exchange $e) => $e->info());
    }

    /**
     * @param string|Exchange $exchange
     */
    public function symbols(string $exchange): array
    {
        if (\in_array($exchange, $this->configRepo->exchanges)) {
            return $exchange::instance()->fetch()->symbols();
        }

        abort(404, "Exchange $exchange doesn't exist.");
    }

    public function balances(): array
    {
        $balances = [];

        foreach ($this->configRepo->exchanges as $exchange) {
            /** @var Exchange $exchange */
            $exchange = $exchange::instance();

            if (!$exchange->hasApiAccess()) {
                continue;
            }

            foreach ($exchange->fetch()->balance()->assets as $assetName => $asset) {
                $balances[] = [
                    'name'      => $assetName,
                    'exchange'  => $exchange::name(),
                    'available' => $asset->available(),
                    'total'     => $asset->total(),
                ];
            }
        }
        $names = \array_column($balances, 'name');
        \array_multisort($names, SORT_ASC, $balances);

        return $balances;
    }
}
