<p align="center">
<a href="https://github.com/spondec/soletrade/actions">
<img src="https://github.com/spondec/soletrade/workflows/tests/badge.svg" alt="Build Status"></a>
</p>

## Soletrade — Algorithmic crypto trading platform for PHP

Features include:

* Minimalist GUI to review your back-test results in depth with charting.
* Real-time bot to automate trading on an exchange.
* Strategy optimization to find the best parameters along with Walk Forward Analysis.
* A well-commented strategy template with additional helper options on creation to get started quickly.
* Telegram Bot support for basic controls.

## Requirements

**You don't need anything other than [Docker](https://www.docker.com/products/docker-desktop/). The listed requirements are for standalone installations.**

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

### Docker

Docker helps you to get started quickly without installing any dependencies on your computer other than Docker itself.

* Install Docker Desktop and make sure it's running and ready. Then open a terminal.
    * If you're on Windows, make sure to activate WSL2 and open a WSL session:
   ```bash
   wsl
   ```
    * Enter the home directory:
   ```bash
   cd ~ 
   ```
* Clone the repository:
   ```bash
   git clone https://github.com/spondec/soletrade.git 
   ```
    * To run the rest of the commands, we must enter the root directory of the repository:
   ```bash
   cd soletrade 
   ```
* Install the composer dependencies with the following command. Just copy and paste it into your terminal:

```
docker run --rm     -u "$(id -u):$(id -g)"     -v $(pwd):/var/www/html     -w /var/www/html     spondec/soletrade-composer:latest /bin/bash -c "composer install --ignore-platform-reqs; php -r \"file_exists('.env') || copy('.env.sail.example', '.env');\"; php artisan key:generate"
```

Now we're ready to build our Docker container. We're going to do that with Sail. From now on, any interaction with the
app will go through our dear friend, Sail. Sail is just a proxy between your machine and Docker container, that's all.
We'll pretty much prefix every command with `./vendor/bin/sail`.

You can create a bash alias for `sail` by running this command:

```bash
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
```

* Boot up the container(it can take a while, only for once):

   ```bash
   sail up -d
   ```

* After boot, run these commands in order:

```bash
sail artisan migrate
```

```bash
sail artisan db:seed
```

```bash
sail npm install
```

```bash
sail npm run production
```

* Now you're ready. You can go to localhost in your browser and use CLI.
* To see available trade commands, run:

```bash
sail artisan trade
```

#### Running commands on Docker

We *must* prefix any commands to our app with `./vendor/bin/sail` or `sail`(if you've created the bash alias) when using
Docker.
So `php artisan some:command` becomes `./vendor/bin/sail artisan some:command` on Docker or `sail artisan some:command`
if you aliased it to `sail`.

### Standalone

For standalone installation, after installing all the dependencies, run these commands in order:

```bash
git clone https://github.com/spondec/soletrade.git
```

```bash
cd soletrade
```

```bash
composer install --optimize-autoloader --no-dev
```

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
```

```bash
php artisan key:generate
```

```bash
php artisan migrate
```

```bash
php artisan db:seed
```

```bash
npm install
```

```bash
npm run production
```

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

A signal is a specific indicator state and if preferred, it becomes a required step to trigger your trade setup
function. Required signals are defined in the signals array in the strategy.

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
app/Trade/Action/Handler is the core class of trade actions and you can invent your own just like in indicators by
extending the handler.

### Strategy Testing

You can test your strategies in CLI or GUI. CLI is the recommended way for faster results.

`php artisan trade:strategy-test {strategyName} {symbol} {interval} {exchange}`

An example result:

```bash
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

You can optimize your strategy if you've filled the optimizableParameters() method in your strategy class. Testing a
strategy is as simple as adding an --optimize parameter to the strategy test command.

```bash
$ php artisan trade:strategy-test MACross BTC/USDT 1d Binance --start=01-01-2019 --end=01-01-2021 --optimize

Updating symbols... This may take a while for the first time, please be patient...
Done.

Parameters to be optimized: shortTermPeriod, longTermPeriod
Total simulations: 81

 Do you want to proceed? (y|n):
 > y

 Do you want to run Walk Forward Analysis? (y|n):
 > y

 Enter the walk forward period start date (DD-MM-YYYY):
 > 02-01-2021

 Enter the walk forward period end date (DD-MM-YYYY) (optional):
 >

Done.

Elapsed time: 0:0:2:14
+----------+----------+-------------+--- MACross Binance BTC/USDT 1d Optimization Summary (01-01-2019 ~ 01-01-2021) ----------------------------------------+
| ROI      | Avg. ROI | Avg. Profit | Avg. Loss | Reward/Risk | Success | Profit | Loss | Ambiguous | Failed | Parameters                             |
+----------+----------+-------------+-----------+-------------+---------+--------+------+-----------+--------+----------------------------------------+
| 2565.14% | 14.66%   | 6.4%        | -1.72%    | 3.72        | 46.86%  | 82     | 93   | 105       | 2      | shortTermPeriod: 2 longTermPeriod: 3   |
| 2296.11% | 18.98%   | 8.97%       | -1.85%    | 4.85        | 44.63%  | 54     | 67   | 64        | 0      | shortTermPeriod: 2 longTermPeriod: 4   |
| 2038.9%  | 13.87%   | 6.67%       | -1.75%    | 3.81        | 48.3%   | 71     | 76   | 84        | 1      | shortTermPeriod: 3 longTermPeriod: 4   |
| 1402.01% | 8.45%    | 3.57%       | -1.69%    | 2.11        | 64.46%  | 107    | 59   | 114       | 1      | shortTermPeriod: 3 longTermPeriod: 2   |
| 1295.77% | 12.58%   | 8.73%       | -1.75%    | 4.99        | 44.66%  | 46     | 57   | 78        | 0      | shortTermPeriod: 6 longTermPeriod: 7   |
| 1041.11% | 8.75%    | 8.28%       | -1.81%    | 4.57        | 41.18%  | 49     | 70   | 58        | 0      | shortTermPeriod: 5 longTermPeriod: 6   |
| 1039.38% | 10.83%   | 9.87%       | -1.92%    | 5.14        | 41.67%  | 40     | 56   | 49        | 0      | shortTermPeriod: 2 longTermPeriod: 5   |
| 821.88%  | 7.61%    | 7.98%       | -1.85%    | 4.31        | 42.59%  | 46     | 62   | 48        | 0      | shortTermPeriod: 3 longTermPeriod: 5   |
| 788.16%  | 8.96%    | 7.99%       | -1.69%    | 4.73        | 47.73%  | 42     | 46   | 51        | 0      | shortTermPeriod: 9 longTermPeriod: 10  |
| 783.3%   | 7.19%    | 6.84%       | -1.85%    | 3.7         | 47.71%  | 52     | 57   | 62        | 0      | shortTermPeriod: 7 longTermPeriod: 8   |
+----------+----------+-------------+-----------+-------------+---------+--------+------+-----------+--------+----------------------------------------+
+---------+----------+-------------+-----------+--- Walk Forward Period (2021-01-02 ~ 2022-05-30) -+--------+----------------------------------------+
| ROI     | Avg. ROI | Avg. Profit | Avg. Loss | Reward/Risk | Success | Profit | Loss | Ambiguous | Failed | Parameters                             |
+---------+----------+-------------+-----------+-------------+---------+--------+------+-----------+--------+----------------------------------------+
| 411.27% | 4.96%    | 6.51%       | -1.95%    | 3.34        | 48.19%  | 40     | 43   | 110       | 0      | shortTermPeriod: 2 longTermPeriod: 3   |
| 445.26% | 7.95%    | 8.58%       | -1.95%    | 4.4         | 50%     | 28     | 28   | 83        | 0      | shortTermPeriod: 2 longTermPeriod: 4   |
| 220.36% | 3.87%    | 6.75%       | -1.88%    | 3.59        | 47.37%  | 27     | 30   | 91        | 0      | shortTermPeriod: 3 longTermPeriod: 4   |
| 696.72% | 7.26%    | 5.29%       | -1.91%    | 2.77        | 58.33%  | 56     | 40   | 97        | 0      | shortTermPeriod: 3 longTermPeriod: 2   |
| 198.32% | 3.61%    | 6.53%       | -2%       | 3.27        | 49.09%  | 27     | 28   | 72        | 0      | shortTermPeriod: 6 longTermPeriod: 7   |
| 59.64%  | 1.05%    | 5.6%        | -2%       | 2.8         | 38.6%   | 22     | 35   | 73        | 0      | shortTermPeriod: 5 longTermPeriod: 6   |
| 355.03% | 6.96%    | 12.35%      | -1.89%    | 6.53        | 37.25%  | 19     | 32   | 68        | 0      | shortTermPeriod: 2 longTermPeriod: 5   |
| 124.59% | 2.44%    | 8.12%       | -2%       | 4.06        | 37.25%  | 19     | 32   | 65        | 0      | shortTermPeriod: 3 longTermPeriod: 5   |
| 411.77% | 9.15%    | 9.4%        | -2%       | 4.7         | 53.33%  | 24     | 21   | 52        | 0      | shortTermPeriod: 9 longTermPeriod: 10  |
| 236.35% | 3.81%    | 7.52%       | -1.95%    | 3.86        | 43.55%  | 27     | 35   | 48        | 0      | shortTermPeriod: 7 longTermPeriod: 8   |
+---------+----------+-------------+-----------+-------------+---------+--------+------+-----------+--------+----------------------------------------+
 10/10 [============================] 100%
```

These are the used parameters in the optimization:

```php
public function optimizableParameters(): array
{
   return [
      'shortTermPeriod' => new RangedSet(min: 2, max: 10, step: 1),
      'longTermPeriod'  => new RangedSet(min: 2, max: 10, step: 1),
   ];
}
```

### Live Trading

The live trading system takes a strategy and runs it on a preferred symbol on a preferred exchange with a specified
capital.

#### Going live

To go live with a strategy, you need to fill credentials for at least one exchange in your .env file. After that, you
can use `php artisan trade:run` command:

`php artisan trade:run {strategy} {exchange} {symbol} {interval} {asset} {amount} {leverage=1}`

An example for trading 100 USD at 5x leverage would be:

```bash
php artisan trade:run MACross FTX BTC/USDT 1h USDT 100 5
```

[Run this command on Docker](#running-commands-on-docker)

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
them a lot in the following examples.

We're going to build a basic strategy that buys when short-period-moving-average crosses over long-term-moving-average
and sells vice versa.
It's the hello world of trading strategies.

#### Crossing two moving averages

Crossing two moving averages require at least two indicator instances.
So we are going to use the Combined indicator for things like this.
Combined is a special kind of indicator. It basically embeds an unlimited amount of indicators in itself and allows us
to use
them as one.

Run this command to create a strategy template named `MACross` which combines two moving averages in the Combined
indicator and uses the Combined indicator as a primary signal that is sufficient on itself to trigger a trade setup.

```bash
php artisan trade:strategy MACross --combined=MA,MA --signals=Combined
```

[Run this command on Docker](#running-commands-on-docker)

Change every alias you see to something unique and explanatory. In this example we'll use `shortTerm` and `longTerm` for
the indicators.

If we want to look up the values of these moving averages at any point in signal function, we need to get it using the aliases:

```php
$value['shortTerm'];
//or
$indicator->current()['shortTerm'];
```

This only applies to the Combined indicator. Other indicators that only provide one value, such as RSI, won't need any
alias to get the value only if you're not using them inside Combined.
Indicators that provide multiple values such as MACD, it's simple as:

```php
$value['macd'];
$value['divergence'];
$value['signal'];
```

But if we were to include MACD inside Combined, we would need to use its alias (let's just say 'MACD') as well.

```php
$value['MACD']['macd'];
$value['MACD']['divergence'];
$value['MACD']['signal'];
```

In the setup function, you can also access indicators:

```php
$macd = $this->indicators('MACD');
$macdVal = $macd->current();
$macdValue['macd'];
$macdValue['divergence'];
$macdValue['signal'];
```

Now we need to define the indicator configurations and write our signal logic inside the signal function. 
So our configuration should look like this:

```php
protected function indicatorConfig(): array
{
    return [
        [
            'alias'  => 'maCross',
            'class'  => Combined::class,
            'config' => [
                'indicators' => [
                    0 => [
                        'alias'  => 'shortTerm',
                        'class'  => MA::class,
                        'config' => [
                            'timePeriod' => $this->config('shortTermPeriod'),
                        ],
                    ],
                    1 => [
                        'alias'  => 'longTerm',
                        'class'  => MA::class,
                        'config' => [
                            'timePeriod' => $this->config('longTermPeriod'),
                        ],
                    ],
                ],
            ],
            'signal' => function (Signal $signal, Combined $indicator, mixed $value): ?Signal {

                if ($indicator->crossOver('shortTerm', 'longTerm'))
                {
                    $signal->name = 'MA-CROSS-OVER';
                    $signal->side = Side::BUY;

                    return $signal;
                }

                if ($indicator->crossUnder('shortTerm', 'longTerm'))
                {
                    $signal->name = 'MA-CROSS-UNDER';
                    $signal->side = Side::SELL;

                    return $signal;
                }

                return null;
            }
        ]
    ];
}
```

Let's add the config parameters to the config array and define default values for them:

```php
protected array $config = [

        //...

        'shortTermPeriod' => 10,
        'longTermPeriod'  => 20
    ];
```

We can leave trade configuration mostly the same as before, 
we don't need to do anything for simplicity's sake but there are useful things to do here mostly on risk management.

```php
protected function tradeConfig(): array
{
    return [
        'signals' => [
            'maCross'
        ],
        'setup' => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {
             
            $trade->setStopPrice(ratio: 2/100); //sets the stop price accounting for 2% loss 
            
            return $trade;
        }
    ];
} 
```

You see that we just return the $trade without any changes other than setting a %2 stop loss.
Normally, we would need to at least define a name and a side but since we did that in the signal function, we can just
return the $trade because we base our trades on signals in this strategy.
$trade object will inherit the last signal's name and side by default. Of course, we could override that by setting it
manually...

#### Testing the strategy

Now let's test the strategy and see what happens.

```bash
php artisan trade:strategy-test MACross BTC/USDT 1d Binance
```

[Run this command on Docker](#running-commands-on-docker)

```bash
85 possible trades found.
Evaluated 83 trades.

Elapsed time: 0:0:0:9
+-------------+----------+
| ROI         | 2028.21% |
| Avg. ROI    | 30.73%   |
| Avg. Profit | 33.06%   |
| Avg. Loss   | -4.53%   |
| Reward/Risk | 7.3      |
| Success     | 31.82%   |
| Profit      | 21       |
| Loss        | 45       |
| Ambiguous   | 17       |
| Failed      | 0        |
| Parameters  |          |
+-------------+----------+
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
