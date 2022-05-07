<p align="center">
<a href="https://github.com/spondec/soletrade/actions">
<img src="https://github.com/spondec/soletrade/workflows/tests/badge.svg" alt="Build Status"></a>
</p>

## SoleTrade - Crypto trading tools for PHP community

Features include:

* A minimalist GUI to review your back-test results in depth with charting.
* A real-time bot to automate trading on an exchange.
* A well-commented strategy template with additional helper options on creation to help you get started quickly.
* A Telegram Bot for basic controls.

## Requirements

* PHP 8.1+
* PHP Trader Extension
* PHP PCNTL Extension
* MariaDB 10.7+

## Supported Exchanges

* [FTX](https://ftx.com)
* [Binance Spot](https://www.binance.com/en/markets/spot)(**Only for testing**)
    * Binance has no order module, so it can not be used to trade live.
      Use Binance for testing and FTX for live trading since FTX doesn't provide enough historical price data.

## Installation

For installation, clone the repository and run these commands in the project root directory:

`composer install --optimize-autoloader --no-dev`

`php -r "file_exists('.env') || copy('.env.example', '.env');"`

`php artisan key:generate`

`php artisan migrate`

`php artisan db:seed`

`npm install`

`npm run production`

## Documentation

### Strategy

Strategy is a class that produces a TradeSetup object for each possible trade.
A TradeSetup object defines the entry/exit/target/stop prices and other various aspects for a trade.
Let's take a look at the strategy create command.

`php artisan trade:strategy YourStrategyName --indicators=Indicator1,Indicator2 --signals=Indicator1,Indicator2 --combined=Indicator1,Indicator2 --actions=Action1,Action2`

--indicators and --signals are optional, but you probably need to use at least one of them.

* `--indicators` just initializes the given indicators in the strategy with default configuration.
* `--signals` both initializes the given indicator and **require** signals from each given indicator to build trades.
    * The signal function for each indicator must be able to return a Signal when your desired configuration emerges in
      order to building trades. So don't leave those functions as returning only null.
    * Indicator order will be followed as signal order.
* `--combined` is used to combine multiple indicators into one.
    * If you're going to use the combined indicator as a signal, also add Combined to the --signals option.
        * You can only have one Combined via this command. If you need more, just copy/paste, add it as a next item to
          the array and make sure to change its **alias**. Each indicator must have a unique alias to refer to.
* `--actions` inserts the action register calls inside of trade setup function.

Strategies will be saved in `app/Strategies` directory.

You can refer to strategies and indicators by their class name when using CLI commands since the class names are unique.

#### Indicators

Indicators are located in `app/Indicators/` directory.
You can create your own indicators, just create a new final class that extends `App\Trade\Indicator\Indicator` and
define configuration parameters in the $config property
and implement the `protected function calculate(CandleCollection $candles): array` method.
Most of the built-in indicators just calls one of the PHP Trader extension functions, so they are pretty light on code.

#### Signals

A signal is a specific indicator state and if preferred, it becomes a required step to trigger your trade setup function. Required signals are defined in the signals array in the strategy.

#### Price Bindings

It is possible to change entry/exit/stop prices dynamically by binding them to an indicator such as ATR or MA so that it
can be used as a trailing entry/stop/target method.
Binding also takes a callback function that takes the indicator value and expects you to return entry/exit/stop price in
result of a calculation.
Without a callback function, the indicator value replaces the price field which is not always what we want.

In following example, we take the ATR value and multiply it by 2 and add or subtract from the current price depending on
which side we're on, and bind it as our stop price in the setup function:

```php
'setup'   => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {

                //...
                
                /** @var ATR $atr */
                $atr = $this->indicator('ATR');
                $atr->bind($trade, 'stop_price', 'ATR',
                    fn(float $atrValue, \stdClass $candle) => $trade->isBuy() 
                    ? $candle->c - $atrValue * 1 
                    : $candle->c + $atrValue * 1
                );
                    
                //...
            }
```

#### Trade Actions

To execute code when in a trade, use a Trade Action which can be assigned to TradeSetup object.
Trade actions are designed to be used as a trade management tool such as taking profits, moving stops or closing the
entire position in result of
complex computation.
There are built-in trade action classes, but you can also write your own.
A trade action class only takes a Position object as an argument and only performs things on this object.
app/Trade/Action/Handler is the core class of trade actions.

### Strategy Testing

You can test your strategies in CLI or GUI. CLI is the recommended way for faster results.

`php artisan trade:strategy-test {strategyName} {symbol} {interval} {exchange}`

An example result:

```
Running strategy...
151 possible trades found.
Evaluated 150 trades.
Elapsed time: 0:0:0:6
+---------------+--------+
| ROI           | 83.13% |
| Avg. ROI      | 0.55%  |
| Avg. Profit   | 15.22% |
| Avg. Loss     | -7.82% |
| Reward/Risk   | 1.95   |
| Success Ratio | 41.33% |
| Profit        | 62     |
| Loss          | 88     |
| Ambiguous     | 0      |
| Failed        | 0      |
+---------------+--------+
```

#### Ambiguous trades

An ambiguous trade is a trade when its target and stop prices kicked in at the same candlestick but since the
candlestick is closed, the testing module can not tell which price trade has closed with so these trades are marked as "
ambiguous" and excluded from the trade summary which will have no effect on the final test report.

#### Optimization

There are currently no optimization modules, but it is a necessary tool and is planned to be implemented in the future.

### Live Trading

The live trading system takes a strategy and runs it on a preferred symbol on a preferred exchange with a specified
capital.

#### Going live

To go live with a strategy, you need to fill credentials for at least one exchange in your .env file. After that, you
can use `php artisan trade:run` command:

`php artisan trade:run {strategy} {exchange} {symbol} {interval} {asset} {amount} {leverage=1}`

An example for trading 100 USD at 5x leverage would be:

`php artisan trade:run GoldenDeathCross FTX BTC/USDT 1h USDT 100 5`

Your total trading volume will be 500 USD because of that 5x leverage.

#### Telegram

Telegram credentials are stored in your .env file. Telegram controls are pretty simple. Available commands:

* `/start` Starts the trader bot.
* `/stop` Stops the trader bot.
* `/status` Gets the status in detail.
* `/orders` Gets the list of open orders.
* `/errors` Gets the list of recovered errors.
* `/password {password}` Authenticates the device for communication with the trader bot for that instance.
    * If you fill TELEGRAM_BOT_PASSWORD in your .env file, you have to authenticate yourself with this command every
      time you start a new trader instance or switch to a different device.
    * If the bot doesn't communicate with you even if it's running, that's probably because you haven't authenticated
      that device.

#### Leverage

Leverage trading is implemented on supported exchanges but liquidations won't be handled by the bot.
It is because the live trading is based on orders. It doesn't track open positions or even open orders other than the
ones that are placed by the bot.
Basically, any liquidation event will leave the live trading system in an unconscious state.
It will keep running until an exchange error about some order crashes the instance.
This shouldn't be a problem since there is nothing left to lose anyway because you got wrecked.

#### Trading fees

Trading fees are registered but not accounted for nor reflected on any ROI. So an extra amount of capital must be always
present on the account for the fees.

#### Partially filled positions

No stop/target orders will be sent for partially filled positions.
This is because these orders may fail due to the exchange's minimum order size not being met by the filled amount.
Orders must be filled fully to send these orders.
Partially filled positions will be closed/stopped at market price with market orders by the internal tracker when the
stop/target price has been reached if able to do so.

## Get Started

### Building a basic strategy

Strategies are designed to be based on indicators. You can use no indicator and still be fine, but we are going to use
them
a lot in the following examples.

We're going to build a basic strategy that longs when 50MA crosses over 200MA and shorts when 50MA crosses under 200MA.
This is known as Golden/Death Cross.

#### Crossing two moving averages

Crossing two moving averages require at least two indicator instances.
So we are going to use the Combined indicator for things like this.
Combined is a special kind of indicator. It basically embeds an unlimited amount of indicators in itself and allows us
to use
them as one.

Run this command to create the strategy template:

`php artisan trade:strategy GoldenDeathCross --combined=MA,MA --signals=Combined`

Change every alias you see to something unique and explanatory.
The default alias for an indicator is going to be the name of its class.
We can change that to include one of the key config parameters.
In moving averages, that important parameter is the length of the time period.
So we change the alias for each MA to MA-{length} format. In this example, we'll use 50MA and 200MA as our aliases.

If we want to look up the values of these moving averages at any point in signal function, we need to get it like
on of these:

```php
$value['50MA'];
//or
$indicator->current()['50MA'];
```

This only applies to the Combined indicator. Other indicators that only provide one value, such as RSI, won't need any
alias to get the value only if you're not using them inside Combined.
Indicators that provide multiple values such as MACD, it's simple as:

```php
$value['macd'];
$value['divergence'];
$value['signal'];
```

But if we were to include MACD inside Combined, we would need to use its alias as well.

```php
$value['MACD-12-26-9']['macd'];
$value['MACD-12-26-9']['divergence'];
$value['MACD-12-26-9']['signal'];
```

In the setup function, you can also access indicators:

```php
$macd = $this->indicators('MACD-12-26-9');
$macdValue = $macd->current();
$macdValue['macd'];
$macdValue['divergence'];
$macdValue['signal'];
```

Now we need to define the indicator configurations and write our signal logic inside the signal function. So our
indicator
the configuration will look like this:

```php
protected function indicatorConfig(): array
{
    return [
        [
            'alias' => 'ma-cross',
            'class' => Combined::class,
            'config' => [
                'indicators' => [
                    0 => [
                        'alias' => 'short-term',
                        'class' => MA::class,
                        'config' => [
                            'timePeriod' => 50,
                        ],
                    ],
                    1 => [
                        'alias' => 'long-term',
                        'class' => MA::class,
                        'config' => [
                            'timePeriod' => 200,
                        ],
                    ],
                ],
            ],
            'signal' => function (Signal $signal, Combined $indicator, mixed $value): ?Signal {

                if ($indicator->crossOver('short-term', 'long-term'))
                {
                    $signal->name = 'Golden Cross';
                    $signal->side = Side::BUY;

                    return $signal;
                }

                if ($indicator->crossUnder('short-term', 'long-term'))
                {
                    $signal->name = 'Death Cross';
                    $signal->side = Side::SELL;

                    return $signal;
                }

                return null;
            }
        ]
    ];
}
```

And our trade configuration will look like this:

```php
protected function tradeConfig(): array
{
    return [
        'signals' => [
            'ma-cross'
        ],
        'setup' => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {
             
            return $trade;
        }
    ];
} 
```

You see that we just return the $trade without any changes.
Normally, we would need to at least define a name and a side but since we did that in the signal function, we can just
return the $trade here because we base our trades on signals in this example.
$trade object will inherit the last signal's name and side by default. Of course, we could override that by setting it
manually.

#### Testing the strategy

Now let's test our strategy and see what happens.

```
php artisan trade:strategy-test GoldenDeathCross BTC/USDT 1h Binance
Running strategy...
257 possible trades found.
Evaluated 255 trades.
Elapsed time: 0:0:1:10
+---------------+---------+
| ROI           | 255.81% |
| Avg. ROI      | 1%      |
| Avg. Profit   | 11.71%  |
| Avg. Loss     | -4.72%  |
| Reward/Risk   | 2.48    |
| Success Ratio | 36.08%  |
| Profit        | 92      |
| Loss          | 163     |
| Ambiguous     | 0       |
| Failed        | 0       |
+---------------+---------+
```

It's done. Now you can create a new strategy from scratch and experiment with provided tools to see how it really works.

## Contribution

Any contribution is welcomed and encouraged. Please do not hesitate to report any bugs or any dissatisfaction through
issues.
For pull requests, please open an issue first before committing your time, and we can discuss the changes.

## License

For license details, see the LICENSE file in the root directory of this repository.

## Disclaimer

The author or any contributor to this software is not responsible for any loss of capital or profit.
Do not risk money that you cannot afford to lose. Monitor the software and the exchange it's trading at regular
intervals to make sure both parties are in a consistent state.
The software is provided as-is. No guarantees are made. Use at your own risk and discretion.

## Screenshots
<img src="https://user-images.githubusercontent.com/19874501/167264486-d4bfbbd5-cdf4-492e-8e72-22b7eaad41fc.png" alt="Indicator Chart View"></a>
<img src="https://user-images.githubusercontent.com/19874501/167264488-c1497620-556f-4ea7-b921-4646a1790672.png" alt="Strategy View"></a>
<img src="https://user-images.githubusercontent.com/19874501/167264489-d0ca6b5d-38a5-4bbb-ba02-b1661018da74.png" alt="Strategy Chart View"></a>
