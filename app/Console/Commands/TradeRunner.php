<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\AllocatedAsset;
use App\Trade\Exchange\Exchange;
use App\Trade\Log;
use App\Trade\Status;
use App\Trade\Strategy\Strategy;
use App\Trade\Telegram\Bot;
use App\Trade\TradeAsset;
use App\Trade\Util;
use Illuminate\Console\Command;
use Longman\TelegramBot\Entities\Update;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class TradeRunner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trader:run {strategy} {exchange} {symbol} {interval} {asset} {amount} {leverage=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch live trade runner.';

    protected ConsoleSectionOutput $header;
    protected ConsoleSectionOutput $section;

    public function __construct(protected SymbolRepository $symbolRepo)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $start = time();
        $args = $this->arguments();

        if (!is_numeric($args['amount']))
        {
            $this->error('Amount must be numeric.');
            return 1;
        }

        $asset = $args['asset'];
        $amount = (float)$args['amount'];
        $leverage = (float)$args['leverage'];

        if ($leverage < 1)
        {
            $this->error('Leverage can not be less than 1.');
            return 1;
        }

        /** @var ConsoleOutput $output */
        $output = $this->output->getOutput();
        $this->header = $output->section();
        $this->header->writeln('<info>Preparing...</info>');

        $trader = $this->getTrader();
        $bot = $this->getTelegramBot();

        $this->section = $output->section();

        $detailsTable = new Table($this->section);
        $detailsTable->setHorizontal();
        $positionTable = new Table($this->section);
        $positionTable->setHorizontal();

        $telegramUpdateFreq = 5;
        $telegramLastUpdate = time();
        $lastPrint = 0;

        while (true)
        {
            if (time() >= $telegramLastUpdate + $telegramUpdateFreq)
            {
                $telegramLastUpdate = time();
                $this->handleTelegramUpdates($bot, $trader);
            }

            $status = $trader->run();

            if (time() - $lastPrint >= 1)
            {
                $position = $status?->getPosition();

                $this->section->clear();

                $allocAmount = $trader->tradeAsset->allocation->amount() / $leverage;
                $balanceRoi = $allocAmount / $amount * 100 - 100;

                $this->renderDetailsTable($detailsTable,
                    $start,
                    $trader->getStatus(),
                    $allocAmount,
                    $asset,
                    $balanceRoi);

                if ($position && $position->isOpen())
                {
                    $roi = $position->roi($trader->symbol->lastPrice()) * $leverage;
                    $this->renderPositionTable($positionTable,
                        $args['symbol'],
                        $position,
                        $roi,
                        $trader->tradeAsset,
                        $asset);
                }

                $lastPrint = time();
            }
        }

        return 0;
    }

    protected function getTrader(): \App\Trade\Trader
    {
        $args = $this->arguments();

        $amount = $args['amount'];
        $asset = $args['asset'];
        $leverage = $args['leverage'] ?? null;

        if (!strategy_exists($args['strategy']))
        {
            $this->error('Strategy not found.');
            exit(1);
        }

        $strategy = new (get_strategy_class($args['strategy']))();
        $exchange = Exchange::from($args['exchange']);
        $symbol = $this->symbolRepo->fetchSymbol($exchange, $args['symbol'], $args['interval']);

        if (!$symbol)
        {
            throw new \UnexpectedValueException("Symbol '{$args['symbol']}' not found");
        }

        $this->renderHeader($strategy, $symbol, $amount, $asset, $leverage, $exchange);

        $balance = $exchange->fetch()->balance();

        $allocation = new AllocatedAsset($balance, $balance[$asset], $amount, $leverage ?? 1);
        $tradeAsset = new TradeAsset($allocation);
        $trader = new \App\Trade\Trader($strategy, $exchange, $symbol, $tradeAsset);

        if ($leverage)
        {
            $trader->setLeverage($leverage);
        }

        return $trader;
    }

    protected function renderHeader(Strategy $strategy,
                                    Symbol   $symbol,
                                    float    $amount,
                                    string   $asset,
                                    float    $leverage,
                                    Exchange $exchange): void
    {
        $content = str("\n")
            ->append(" Running <info>{$strategy::name()}</info>")
            ->append(" on <info>$symbol->symbol $symbol->interval</info>")
            ->append(" with <info>$amount $asset {$leverage}x</info>")
            ->append(" at <info>{$exchange::name()}</info>.")
            ->newLine();

        $this->header->overwrite($content);
    }

    protected function getTelegramBot(): Bot
    {
        $c = \Config::get('trade.telegram');
        return new Bot($c['token'], $c['name'], $c['password']);
    }

    protected function handleTelegramUpdates(Bot $bot, \App\Trade\Trader $trader): void
    {
        /** @var Update $update */
        foreach ($bot->updates() as $update)
        {
            $message = $update->getMessage() ?? $update->getEditedMessage();
            $text = $message->getText();
            $id = $message->getChat()->getId();

            switch ($text)
            {
                case '/start':
                    $trader->setStatus(Status::AWAITING_TRADE);
                    $bot->sendMessage($trader->getStatus()->value, $id);
                    break;

                case '/stop':
                    $trader->setStatus(Status::STOPPED);
                    $bot->sendMessage($trader->getStatus()->value, $id);
                    break;

                case '/status':
                    $bot->sendMessage(Helper::removeDecoration($this->section->getFormatter(), $this->section->getContent()), $id);
                    break;

                case '/orders':
                    $bot->sendMessage($trader->loop()?->order->orders()->toJson() ?? 'No orders.', $id);
                    break;

                case '/memory':
                    $bot->sendMessage(Util::memoryUsage(), $id);
                    break;

                case '/memory_peak':
                    $bot->sendMessage((int)(memory_get_peak_usage(true) / 1024 / 1024) . 'MB', $id);
                    break;

                case '/errors':
                    if ($errors = Log::getErrors())
                    {
                        $bot->sendMessage(
                            implode("\n",
                                array_map(static fn(\Throwable $e) => $e->getMessage(),
                                    $errors)), $id
                        );
                    }
                    else
                    {
                        $bot->sendMessage('No errors.', $id);
                    }
            }
        }
    }

    protected function renderDetailsTable(Table  $detailsTable,
                                          int    $start,
                                          Status $status,
                                          float  $allocAmount,
                                          mixed  $asset,
                                          float  $roi): void
    {
        $detailsTable
            ->setHeaders([
                'Up Time',
                'Memory',
                'Errors',
                'Status',
                'Balance',
                'ROI'
            ])->setRows([
                [
                    elapsed_time($start),
                    Util::memoryUsage(),
                    count(Log::getErrors()),
                    $status->value,
                    "$allocAmount $asset",
                    Util::formatRoi($roi)
                ]
            ])->render();
    }

    protected function renderPositionTable(Table                          $positionTable,
                                           string                         $symbol,
                                           \App\Trade\Evaluation\Position $position,
                                           float                          $roi,
                                           TradeAsset                     $tradeAsset,
                                           string                         $asset): void
    {
        $positionTable->setHeaders([
            'Symbol',
            'Side',
            'ROI',
            'Leverage',
            'Amount',
            'Entry Price'
        ]);
        $positionTable->setRows([
            [
                $symbol,
                $position->side->value,
                Util::formatRoi($roi),
                $tradeAsset->allocation->leverage . 'x',
                $tradeAsset->real($position->getUsedSize()) . " $asset",
                $position->price('entry')->get()
            ]
        ]);
        $positionTable->render();
    }
}