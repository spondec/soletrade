<template>
  <main-layout v-bind:title="title">
    <div v-if="sel.exchange && sel.symbol" class="grid lg:grid-cols-5 md:grid-cols-2 sm:grid-cols-1 gap-4 form-group">
      <div class="mb-3">
        <label class="form-label">Strategy</label>
        <vue-multiselect v-model="sel.strategy" :allow-empty="true" :options="strategies"/>
      </div>
      <div class="mb-3">
        <label class="form-label">Exchange</label>
        <vue-multiselect v-model="sel.exchange" :options="exchanges" :allow-empty="false"/>
      </div>
      <div class="mb-3">
        <label class="form-label">Symbol</label>
        <vue-multiselect v-model="sel.symbol" :options="symbols[sel.exchange]" :allow-empty="false"/>
      </div>
      <div class="mb-3">
        <label class="form-label">Interval</label>
        <vue-multiselect v-model="sel.interval" :options="intervals" :allow-empty="false"/>
      </div>
      <div class="mb-3">
        <label class="form-label">Indicators</label>
        <vue-multiselect v-model="sel.indicators" :options="indicators" :multiple="true"/>
      </div>
    </div>

    <DatePicker v-model="range" :model-config="modelConfig" is-dark is-range is24hr>
      <template v-slot="{ inputValue, inputEvents }">
        <div class="flex justify-center items-center">
          <input v-on="inputEvents.start"
                 :value="inputValue.start"
                 class="text-dark border px-2 py-1 w-32 rounded focus:outline-none focus:border-indigo-300"/>
          <svg class="w-4 h-4 mx-2"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path d="M14 5l7 7m0 0l-7 7m7-7H3"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"/>
          </svg>
          <input v-on="inputEvents.end"
                 :value="inputValue.end"
                 class="text-dark border px-2 py-1 w-32 rounded focus:outline-none focus:border-indigo-300"/>
        </div>
      </template>
    </DatePicker>

    <div class="mb-3">
      <label class="form-label">Magnifier</label>
      <vue-multiselect v-model="magnifier.interval" :allow-empty="false" :options="intervals"/>
    </div>

    <div class="chart-container" ref="chart">
      <v-spinner v-if="this.loading"/>
      <p v-else-if="!this.symbol" class="text-lg-center">Requested chart is not available.</p>
      <div v-if="symbol && symbol.strategy">
        <div class="py-2">
          <tabs nav-class="text-lg grid grid-cols-2 text-center bg-gray rounded border border-solid"
                nav-item-active-class="bg-primary"
                nav-item-class="py-2"
                nav-item-disabled-class="bg-secondary"
                @changed="toggle = !toggle">
            <tab name="Trade Setups">
              <div class="body divide-y my2">
                <div v-for="(setup, id) in symbol.strategy.trades" id="trade-setups" class="my-2">
                  <h1 class="text-2xl text-center">{{ id }}</h1>
                  <div class="grid grid-cols-10 text-center">
                    <h1 class="text-lg" v-bind:class="{
                        'text-danger': setup.summary.roi < 0,
                        'text-success': setup.summary.roi > 0 }">
                      {{ 'ROI: ' + setup.summary.roi + '%' }} </h1>
                    <h1 class="text-lg" v-bind:class="{
                        'text-danger': setup.summary.roi < 0,
                        'text-success': setup.summary.roi > 0 }">
                      {{ 'Average ROI: ' + setup.summary.avg_roi + '%' }} </h1>
                    <h1 class="text-lg">Avg Highest ROI: {{ setup.summary.avg_highest_roi + '%' }}</h1>
                    <h1 class="text-lg">Avg Lowest ROI: {{ setup.summary.avg_lowest_roi + '%' }}</h1>
                    <h1 class="text-lg">Risk/Reward: {{ setup.summary.risk_reward_ratio }}</h1>
                    <h1 class="text-lg">Success Ratio: {{ setup.summary.success_ratio }}</h1>
                    <h1 class="text-lg">Profit: {{ setup.summary.profit }}</h1>
                    <h1 class="text-lg">Loss: {{ setup.summary.loss }}</h1>
                    <h1 class="text-lg">Ambiguous: {{ setup.summary.ambiguous }}</h1>
                    <h1 class="text-lg">Failed: {{ setup.summary.failed }}</h1>
                  </div>
                  <trade-table chart-id="chart" v-bind:trades="setup.evaluations" @dateClick="showRange"
                               @magnify="magnify"></trade-table>
                </div>
              </div>
            </tab>
            <tab name="Signals">
              <div class="body divide-y">
                <div v-for="(setup, id) in symbol.strategy.signals" id="signals" class="my-2">
                  <h1 class="text-2xl text-center">{{ id }}</h1>
                  <div class="grid grid-cols-10 text-center">
                    <h1 class="text-lg" v-bind:class="{
                        'text-danger': setup.summary.roi < 0,
                        'text-success': setup.summary.roi > 0 }">
                      {{ 'ROI: ' + setup.summary.roi + '%' }} </h1>
                    <h1 class="text-lg " v-bind:class="{
                        'text-danger': setup.summary.roi < 0,
                        'text-success': setup.summary.roi > 0 }">
                      {{ 'Average ROI: ' + setup.summary.avg_roi + '%' }} </h1>
                    <h1 class="text-lg">Avg Highest ROI: {{ setup.summary.avg_highest_roi + '%' }}</h1>
                    <h1 class="text-lg">Avg Lowest ROI: {{ setup.summary.avg_lowest_roi + '%' }}</h1>
                    <h1 class="text-lg">Risk/Reward: {{ setup.summary.risk_reward_ratio }}</h1>
                    <h1 class="text-lg">Success Ratio: {{ setup.summary.success_ratio }}</h1>
                    <h1 class="text-lg">Profit: {{ setup.summary.profit }}</h1>
                    <h1 class="text-lg">Loss: {{ setup.summary.loss }}</h1>
                    <h1 class="text-lg">Ambiguous: {{ setup.summary.ambiguous }}</h1>
                    <h1 class="text-lg">Failed: {{ setup.summary.failed }}</h1>
                  </div>
                  <trade-table chart-id="chart" v-bind:trades="setup.evaluations" @dateClick="showRange"
                               @magnify="magnify"></trade-table>
                </div>
              </div>
            </tab>
          </tabs>
        </div>
      </div>
    </div>
    <span id="chart" class="anchor"></span>
  </main-layout>
</template>

<script>

import MainLayout from '../layouts/Main';

import VSpinner from "../components/VSpinner";
import TradeTable from "../components/TradeTable";

import ApiService from "../services/ApiService";
import Chart from "../services/Chart";

import VueMultiselect from 'vue-multiselect'

import {Tabs, Tab} from 'vue3-tabs-component';

import {DatePicker} from 'v-calendar';

export default {
  title: "Chart",
  components: {
    VSpinner, MainLayout, VueMultiselect, TradeTable, Tabs, Tab, DatePicker
  },
  watch:
      {
        sel: {
          handler: 'onSelect',
          deep: true
        },
        range: 'onSelect',
        magnifier: {
          handler: 'magnifyUpdate',
          deep: true
        }
      },
  data: function ()
  {
    return {
      range: {},
      modelConfig: {
        start: {
          timeAdjust: '00:00:00',
        },
        end: {
          timeAdjust: '23:59:59',
        },
      },

      magnifier: {
        startDate: null,
        endDate: null,
        interval: null,
      },

      magnifiedCharts: [],

      toggle: true,

      loading: false,
      notFound: false,

      cache: [],
      useCache: false,

      strategies: [],
      exchanges: [],
      symbols: [],
      intervals: [],
      indicators: [],
      title: this.title,

      symbol: null,
      limit: null,
      limitReached: false,

      sel: {
        strategy: null,
        exchange: null,
        symbol: null,
        interval: null,
        indicators: []
      },

      candlesPerRequest: 1000,
      isCrossHairMoving: false,

      charts: [],
      series: []
    }
  },

  async created()
  {
    const data = await ApiService.chartData();

    this.strategies = data.strategies;
    this.exchanges = data.exchanges;
    this.indicators = data.indicators;
    this.symbols = data.symbols;
    this.intervals = data.intervals;

    this.sel.exchange = data.exchanges[0];
    this.sel.symbol = 'BTC/USDT';
    this.sel.interval = '1h';
  },

  mounted()
  {
    this.init();
  },

  methods: {

    magnifyUpdate: async function ()
    {
      if (!this.magnifier.startDate || !this.magnifier.endDate || !this.magnifier.interval)
      {
        return;
      }

      const range = {
        start: new Date(this.magnifier.startDate).toISOString(),
        end: new Date(this.magnifier.endDate).toISOString()
      }

      console.log(range)
      const symbol = this.prepareSymbol(await ApiService.candles(this.sel.exchange,
          this.sel.symbol,
          this.magnifier.interval,
          null,
          null,
          null,
          range));

      console.log(symbol);
      //separate magnifier container
      const container = this.$refs.chart;

      for (let i in this.magnifiedCharts)
      {
        this.magnifiedCharts[i].remove();
      }
      this.magnifiedCharts = [];

      this.magnifiedCharts[0] = this.newChart(container);
      const candlestickSeries = this.magnifiedCharts[0].addCandlestickSeries();

      candlestickSeries.setData(symbol.candles);
      const indicators = this.symbol.indicators;

      const handlers = this.handlers();
      for (let key in indicators)
      {
        let handler = handlers[key]
        let magnified = handler['magnify'](this.magnifier.startDate / 1000, this.magnifier.endDate / 1000, indicators[key])
        let chart;
        if (handler['requiresNewChart'])
        {
          chart = this.newChart(container);
          this.magnifiedCharts.push(newChart);
        } else
        {
          chart = this.magnifiedCharts[0];
        }
        let series = handler['init'](magnified, chart)
        handler['update'](series, magnified)
      }
    },

    magnify: function (startDate, endDate)
    {
      if (!startDate || !endDate)
      {
        throw Error('startDate or endDate is undefined');
      }

      this.magnifier.startDate = startDate;
      this.magnifier.endDate = endDate;
    },

    showRange: function (timestampA, timestampB)
    {
      for (let i in this.charts)
      {
        this.charts[i].showRange(timestampA / 1000, timestampB / 1000);
      }
    },

    registerEvents: function ()
    {
      this.charts[0].timeScale().subscribeVisibleLogicalRangeChange(newVisibleLogicalRange =>
      {
        if (!this.series['candlestick']) return;

        const barsInfo = this.series['candlestick'].barsInLogicalRange(newVisibleLogicalRange);
        // if there less than 50 bars to the left of the visible area
        if (barsInfo !== null && barsInfo.barsBefore < 50)
        {
          this.lazyLoad();
        }
      });

      for (let i in this.charts)
      {
        // this.charts[i].subscribeCrosshairMove(param =>
        // {
        //   if (!param.point) return;
        //   if (!param.time) return;
        //   if (this.isCrossHairMoving) return;
        //
        //   this.isCrossHairMoving = true;
        //
        //   for (let j in this.charts)
        //     if (j !== i)
        //       this.charts[j].moveCrosshair(param.point);
        //
        //   this.isCrossHairMoving = false;
        // });

        this.charts[i].timeScale().subscribeVisibleLogicalRangeChange(range =>
        {
          for (let j in this.charts)
          {
            if (j !== i)
            {
              this.charts[j].timeScale().setVisibleLogicalRange(range);
            }
          }
        });
      }
    },

    objectMap: function (callback, object)
    {
      let o = [];

      for (let key in object)
      {
        o.push(callback(object[key], key));
      }

      return o;
    },

    handlers: function ()
    {
      return {

        Fib: {
          requiresNewChart: false,
          prepare: (data, length) =>
          {
            for (let key in data)
            {
              data[key] = this.objectMap((val, k) =>
              {
                return {time: k / 1000, value: val};
              }, data[key]);

              this.fillFrontGaps(length, data[key]);
            }
            return data;
          },
          init: (data, chart) =>
          {
            const colors = {
              0: '#7c7c7c',
              236: '#e03333',
              382: '#e0d733',
              500: '#148cb2',
              618: '#36fa00',
              702: '#dd00ff',
              786: '#ff4500',
              886: '#00d8ff',
              1000: '#ffffff',
            }
            let series = {};

            for (let i in data)
            {
              series[i] = chart.addLineSeries({
                color: colors[i],
                lastValueVisible: true,
                lineWidth: 1,
                crosshairMarkerVisible: false,
                priceLineVisible: false,
              });
            }

            return series;
          },
          update: (series, data) =>
          {
            for (let i in series)
              series[i].setData(data[i]);
          },
          setMarkers: markers =>
          {
          },
          magnify: (start, end, data = []) =>
          {
            const magnified = {};

            for (let key in data)
            {
              magnified[key] = data[key].filter(item => item.time >= start && item.time <= end)
            }
            return magnified;
          }
        },
        RSI: {
          requiresNewChart: true,
          prepare: (data, length) =>
          {
            data = this.objectMap((val, key) =>
            {
              return {time: key / 1000, value: val}
            }, data);

            this.fillFrontGaps(length, data);

            return data;
          },
          init: (data, chart) =>
          {
            const lineSeries = chart.addLineSeries();

            lineSeries.createPriceLine({
              price: 70.0,
              color: 'gray',
            });
            lineSeries.createPriceLine({
              price: 30.0,
              color: 'gray',
            });

            return lineSeries;
          },
          update: (series, data) =>
          {
            series.setData(data);
          },
          setMarkers: markers =>
          {
            this.series.RSI.setMarkers(markers);
          }
        },
        MACD: {
          requiresNewChart: true,
          prepare: (data, length) =>
          {
            for (let key in data)
            {
              data[key] = this.objectMap((val, k) =>
              {
                return {time: k / 1000, value: val};
              }, data[key]);

              this.fillFrontGaps(length, data[key]);
            }

            let prevHist = 0;
            for (let i in data.divergence)
            {
              var point = data.divergence[i];
              var hist = data.macd[i].value - data.signal[i].value;

              if (point.value > 0)
                if (prevHist > hist) point.color = '#B2DFDB';
                else point.color = '#26a69a';
              else if (prevHist < hist) point.color = '#FFCDD2';
              else point.color = '#EF5350';

              prevHist = hist;
            }

            return data;
          },
          init: (data, chart) =>
          {
            let series = {};

            series.divergence = chart.addHistogramSeries({
              lineWidth: 1,
              // title: 'divergence',
              crosshairMarkerVisible: true,
            });

            series.macd = chart.addLineSeries({
              color: '#0094ff',
              lineWidth: 1,
              // title: 'macd',
              crosshairMarkerVisible: true
            });

            series.signal = chart.addLineSeries({
              color: '#ff6a00',
              lineWidth: 1,
              // title: 'signal',
              crosshairMarkerVisible: true,
            });

            return series;
          },
          update: (series, data) =>
          {
            for (let i in series)
              series[i].setData(data[i]);
          },
          setMarkers: markers =>
          {
            this.series.MACD.macd.setMarkers(markers);
          }
        }
      }
    },

    calcInterval: function (collection, key)
    {
      let start = 0;
      let interval = 0;

      for (let i in collection)
      {
        if (!start) start = collection[i][key];
        else if (!interval)
        {
          interval = collection[i][key] - start;

          if (!Number.isInteger(interval))
          {
            throw Error('Index interval must be a integer.');
          }
          break;
        }
      }

      return {start: start, interval: interval};
    },

    fillFrontGaps: function (length, indicator, value = 0)
    {
      const gap = length - Object.keys(indicator).length;

      if (gap > 0)
      {
        let {start, interval} = this.calcInterval(indicator, 'time');
        let time = start;
        for (let i = 0; i < gap; i++)
        {
          time -= interval;
          indicator.unshift({time: time, value: value});
        }
      }
    },

    prepareIndicatorData: function (indicators, length)
    {
      const handlers = this.handlers();

      for (let key in handlers)
      {
        if (indicators[key])
        {
          indicators[key] = handlers[key]['prepare'](indicators[key], length);
        }
      }
      return indicators;
    },

    initIndicators: function (container)
    {
      const indicators = this.symbol.indicators;
      const handlers = this.handlers();

      for (let key in handlers)
      {
        let data = indicators[key];
        if (data)
        {
          let handler = handlers[key];
          let chart = handler['requiresNewChart'] ? this.createChart(container) : this.charts[0];
          this.series[key] = handler['init'](data, chart);
          handler['update'](this.series[key], data);
        }
      }
    },

    prepareSignalMarker: function (data, namePrefix)
    {
      return {
        time: data.timestamp / 1000,
        position: data.side === 'BUY' ? 'belowBar' : 'aboveBar',
        color: data.side === 'BUY' ? '#00ff68' : '#ff0062',
        shape: data.side === 'BUY' ? 'arrowUp' : 'arrowDown',
        text: typeof namePrefix === String ? namePrefix + ': ' + data.name : data.name
      }
    },

    initMarkers: function ()
    {
      const strategy = this.symbol.strategy;

      if (strategy)
      {
        if (strategy.trades)
        {
          let markers = [];
          for (let id in strategy.trades)
          {
            markers = this.prepareSignalMarkers(markers, strategy.trades[id].evaluations, true);
          }
          this.series['candlestick'].setMarkers(markers);
        }

        if (strategy.signals)
        {
          const handlers = this.handlers();
          for (let id in strategy.signals)
          {
            if (handlers[id] === undefined)
              throw  Error('No handler found for ' + id);

            if (handlers[id].setMarkers === undefined)
              throw Error(id + ' handler must contain a setMarkers() function for handling markers.');

            let markers = [];
            markers = this.prepareSignalMarkers(markers, strategy.signals[id].evaluations);
            handlers[id].setMarkers(markers);
          }
        }
      }
    },

    prepareSignalMarkers: function (markers, collection, prefix = false)
    {
      for (let id in collection)
      {
        markers.push(this.prepareSignalMarker(collection[id]['entry'], prefix ? prefix + ' - ' + 'Entry' : ''));
        markers.push(this.prepareSignalMarker(collection[id]['exit'], prefix ? prefix + ' - ' + 'Exit' : ''));
      }

      return markers.sort((a, b) => (a.time - b.time));
    },

    replaceCandlestickChart: async function ()
    {
      this.resetLimit();

      if (!this.sel.exchange || !this.sel.symbol || !this.sel.interval)
      {
        return;
      }

      this.purge();

      await this.updateSymbol();

      if (!this.symbol) return;

      const container = this.$refs.chart;

      const chart = this.createChart(container);
      const candlestickSeries = chart.addCandlestickSeries();
      candlestickSeries.setData(this.symbol.candles);

      this.series['candlestick'] = candlestickSeries;

      this.initIndicators(container);
      this.initMarkers();
      this.registerEvents();
    },

    cacheKey: function ()
    {
      return this.sel.exchange + this.sel.symbol + this.sel.interval + this.sel.indicators;
    },

    updateSymbol: async function ()
    {
      if (this.useCache)
      {
        const key = this.cacheKey();
        const cached = this.cache[key];

        if (cached && cached.limit >= this.limit)
        {
          console.log('Cache access: ' + key);
          this.symbol = cached.symbol;
          return;
        }
      }

      this.loading = true;
      this.symbol = await this.prepareSymbol(await this.fetchSymbol());
      this.loading = false;

      if (this.useCache)
        this.cache[key] = {symbol: this.symbol, limit: this.limit};
    },

    lazyLoad: async function ()
    {
      if (this.loading || this.limitReached || !this.charts['candlestick']) return;
      this.loading = true;
      this.limit = this.symbol.candles.length + this.candlesPerRequest;

      const length = this.symbol.candles.length;

      await this.updateSymbol();
      await this.updateSeries();

      this.loading = false;
      this.limitReached = length === this.symbol.candles.length;
    },

    updateSeries: async function ()
    {
      try
      {
        this.series['candlestick'].setData(await this.symbol.candles);

      } catch (e)
      {
        console.log(e)
      }

      const handlers = this.handlers();

      for (let key in handlers)
      {
        if (this.symbol.indicators[key])
          handlers[key]['update'](this.series[key], this.symbol.indicators[key]);
      }
    },

    newChart: function (container, options = {})
    {
      if (!container) throw Error('Chart container was not found.');

      options.height = container.offsetHeight;
      options.width = container.offsetWidth;

      return new Chart(container, options);
    },
    createChart: function (container, options = {})
    {
      const chart = this.newChart(container, options);
      this.charts.push(chart);

      return chart;
    },

    resize: function ()
    {
      const container = this.$refs.chart;

      if (!container || !this.charts[0])
      {
        return;
      }

      for (let i in this.charts)
        this.charts[i].resize(container.offsetWidth, container.offsetHeight);
    },

    onSelect: function ()
    {
      this.replaceCandlestickChart();
    },

    purge: function ()
    {
      if (this.charts.length)
      {
        for (let i in this.charts)
          this.charts[i].remove();
        this.series = [];
        this.charts = [];
        this.symbol = null;
      }
    },

    fetchSymbol: async function ()
    {
      return await ApiService.candles(this.sel.exchange,
          this.sel.symbol,
          this.sel.interval,
          this.sel.indicators,
          this.limit,
          this.sel.strategy,
          this.range);
    },

    prepareSymbol: function (symbol)
    {
      if (!Object.keys(symbol).length) return;

      symbol.volumes = symbol.candles.map(x =>
      {
        return {time: x.t / 100, value: x.v}
      });

      symbol.candles = symbol.candles.map(x =>
      {
        return {time: x.t / 1000, open: x.o, close: x.c, high: x.h, low: x.l}
      });

      symbol.indicators = this.prepareIndicatorData(symbol.indicators, symbol.candles.length)

      return symbol;
    },

    resetLimit: function ()
    {
      this.limit = this.candlesPerRequest;
      this.limitReached = false;
    },

    init: function ()
    {
      window.addEventListener('resize', this.resize);
    }
  }
}
</script>

<style lang="scss">

html, body, #app, .container {
  height: 100%;
}

.chart-container {
  height: 30%;
  margin-top: 10px;
}

.multiselect__single {
  overflow: hidden !important;
}

.anchor {
  position: absolute;
  bottom: 0;
}
</style>