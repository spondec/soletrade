<template>
  <main-layout v-bind:title="title">
    <v-spinner v-if="this.loading"></v-spinner>

    <div class="container">
      <div v-if="sel.exchange && sel.symbol" class="grid lg:grid-cols-5 md:grid-cols-2 sm:grid-cols-1 gap-4 form-group">
        <div class="mb-3">
          <label class="form-label">Strategy</label>
          <vue-multiselect v-model="sel.strategy" :allow-empty="true" :disabled="loading" :options="strategies"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Exchange</label>
          <vue-multiselect v-model="sel.exchange" :allow-empty="false" :disabled="loading" :options="exchanges"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Symbol</label>
          <vue-multiselect v-model="sel.symbol" :allow-empty="false" :disabled="loading"
                           :options="symbols[sel.exchange]"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Interval</label>
          <vue-multiselect v-model="sel.interval" :allow-empty="false" :disabled="loading" :options="intervals"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Indicators</label>
          <vue-multiselect v-model="sel.indicators" :disabled="loading || !!symbol?.strategy" :multiple="true"
                           :options="indicators"/>
        </div>
      </div>
      <DatePicker v-model="range" :model-config="modelConfig" is-dark is-range is24hr timezone="UTC">
        <template v-slot="{ inputValue, inputEvents }">
          <div class="flex justify-center items-center">
            <input :disabled="loading"
                   :value="inputValue.start"
                   class="text-dark border px-2 py-1 w-32 rounded focus:outline-none focus:border-indigo-300"
                   v-on="inputEvents.start"/>
            <svg class="w-4 h-4 mx-2"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path d="M14 5l7 7m0 0l-7 7m7-7H3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"/>
            </svg>
            <input :disabled="loading"
                   :value="inputValue.end"
                   class="text-dark border px-2 py-1 w-32 rounded focus:outline-none focus:border-indigo-300"
                   v-on="inputEvents.end"/>

            <button v-if="!loading && this.range?.start" class="button w-16"
                    v-on:click="this.preventLoad = true; this.range = {};">
              Clear
            </button>
          </div>
        </template>
      </DatePicker>
      <div v-show="sel.indicators.length && !sel.strategy" class="indicator-config-editor py-2">
        <div class="flex justify-center items-center">
          <button class="bg-sky-500/50 text-white font-bold2 px-4 rounded focus:outline-none focus:shadow-outline"
                  v-on:click="toggleJsonEditor">
            Indicator Configuration
          </button>
        </div>
        <div v-show="jsonEditorEnabled" class="py-2">
          <Vue3JsonEditor
              v-model="indicatorConfig"
              :expandedOnStart="true"
              :show-btns="false"
              class="bg-white"
              @json-change="onJsonChange"
          />
          <div class="flex justify-center items-center py-2">
            <button class="bg-sky-500/50 text-white font-bold2 px-4 rounded focus:outline-none focus:shadow-outline"
                    v-on:click="onSelect(); toggleJsonEditor();">
              Apply
            </button>
          </div>
        </div>
      </div>
      <div v-if="hasTrades()" class="mb-3 inline-block">
        <label class="form-label">Magnifier Interval</label>
        <vue-multiselect v-model="magnifier.interval" :allow-empty="false" :options="intervals"/>
      </div>
    </div>

    <div class="chart-container">
      <p v-if="!loading && !sel.symbol" class="text-lg-center">Requested chart is not available. Did you seed?</p>
      <div v-if="hasTrades()">
        <div class="grid grid-cols-10 text-center">
          <h1 class="text-lg" v-bind:class="{
                        'text-danger': symbol.strategy.trades.summary.roi < 0,
                        'text-success': symbol.strategy.trades.summary.roi > 0 }">
            {{ 'ROI: ' + symbol.strategy.trades.summary.roi + '%' }} </h1>
          <h1 class="text-lg" v-bind:class="{
                        'text-danger': symbol.strategy.trades.summary.roi < 0,
                        'text-success': symbol.strategy.trades.summary.roi > 0 }">
            {{ 'Avg ROI: ' + symbol.strategy.trades.summary.avg_roi + '%' }} </h1>
          <h1 class="text-lg">Avg Profit: {{ symbol.strategy.trades.summary.avg_profit_roi + '%' }}</h1>
          <h1 class="text-lg">Avg Loss: {{ symbol.strategy.trades.summary.avg_loss_roi + '%' }}</h1>
          <h1 class="text-lg">Reward/Risk: {{ symbol.strategy.trades.summary.risk_reward_ratio }}</h1>
          <h1 class="text-lg">Success Ratio: {{ symbol.strategy.trades.summary.success_ratio }}</h1>
          <h1 class="text-lg">Profit: {{ symbol.strategy.trades.summary.profit }}</h1>
          <h1 class="text-lg">Loss: {{ symbol.strategy.trades.summary.loss }}</h1>
          <h1 class="text-lg">Ambiguous: {{ symbol.strategy.trades.summary.ambiguous }}</h1>
          <h1 class="text-lg">Failed: {{ symbol.strategy.trades.summary.failed }}</h1>
        </div>
        <trade-table chart-id="chart" v-bind:trades="symbol.strategy.trades.evaluations"
                     @dateClick="showRange"
                     @magnify="magnify"/>
      </div>
      <div v-else-if="symbol?.strategy" class="text-lg-center">
        <p>No trades.</p>
      </div>
      <div v-show="balanceChart" ref="balanceChart" class="balance-chart"/>
      <div ref="chart" class="chart"/>
      <span id="chart" class="anchor"></span>
    </div>
  </main-layout>
</template>

<script>

import MainLayout from '../layouts/Main';

import VSpinner from "../components/VSpinner";
import TradeTable from "../components/TradeTable";

import ApiService from "../services/ApiService";
import Chart from "../services/Chart";

import VueMultiselect from 'vue-multiselect'
import {reactive, toRefs} from 'vue'
import {Vue3JsonEditor} from 'vue3-json-editor'

import {DatePicker} from 'v-calendar';

import IndicatorManager from "../indicators/IndicatorManager";

import { default as indicatorHandlers } from "../indicator_view.js";

export default {
  title: "Chart",
  components: {
    VSpinner, MainLayout, VueMultiselect, TradeTable, DatePicker, Vue3JsonEditor
  },
  watch: {
    sel: {
      handler: 'onSelect',
      deep: true
    },
    range: 'onSelect',
    magnifier: {
      handler: 'magnifyUpdate',
      deep: true
    },
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
        trade: null,
        interval: null,
      },

      magnifiedCharts: [],

      indicatorHandlers: indicatorHandlers,
      indicatorManager: null,
      jsonEditorEnabled: false,

      toggle: true,

      loading: false,
      preventLoad: false,

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
        indicators: [],
      },

      candlesPerRequest: 1000,

      balanceChart: null,
      charts: [],
      series: []
    }
  },

  setup()
  {
    const state = reactive({
      indicatorConfig: {}
    })

    function onJsonChange(value)
    {
      state.indicatorConfig = value;
    }

    return {
      ...toRefs(state),
      onJsonChange
    }
  }
  ,

  async created()
  {
    const data = await ApiService.chartData();

    this.strategies = data.strategies;
    this.exchanges = data.exchanges;
    this.indicators = Object.keys(this.indicatorHandlers);
    this.symbols = data.symbols;
    this.intervals = data.intervals;

    this.sel.exchange = data.exchanges[0];
    this.sel.symbol = 'BTC/USDT';
    this.sel.interval = '1d';

    this.indicatorManager = new IndicatorManager(this.indicatorHandlers);
  },

  mounted()
  {
    this.init();
  },

  methods: {

    purgeMagnifierCharts()
    {
      for (let i in this.magnifiedCharts)
      {
        this.magnifiedCharts[i].remove();
      }
      this.magnifiedCharts = [];
    }
    ,

    toggleJsonEditor: function ()
    {
      this.jsonEditorEnabled = !this.jsonEditorEnabled;
    },

    reduceSeriesData: function (start, end, seriesData)
    {
      const acc = Array.isArray(seriesData) ? [] : {};

      for (let i in seriesData)
      {
        let item = seriesData[i];
        if (item.time)
        {
          if (item.time >= start && item.time <= end)
          {
            acc.push(item);
          } else
          {
            let last = acc[acc.length - 1];

            if (last && last.time < end)
            {
              acc.push(item);
            }
          }
        } else
        {
          acc[i] = this.reduceSeriesData(start, end, item);
        }
      }

      return acc;
    },

    preparePriceChangeMarker: function (log, color, size = 0.5)
    {
      return {
        time: log.time,
        position: 'belowBar',
        color: color,
        shape: 'circle',
        size: size,
        text: log.reason ? log.reason : ''
      }
    },

    prepareTransactionMarker: function (log, size = 0.5)
    {
      return {
        time: log.timestamp / 1000,
        position: 'belowBar',
        color: log.value.increase ? '#05ea24' : '#e80505',
        shape: 'square',
        size: size,
        text: (log.value.increase ? 'Increase' : 'Decrease') + ' ' + log.value.size + ': ' + (log.reason ? log.reason : '')
      }
    },

    prepareMagnifiedPriceLog: function (log)
    {
      return Object.values(log.map(function (entry)
          {
            return {
              value: entry.value,
              time: entry.timestamp / 1000,
              reason: entry.reason
            }
          }).reduce(function (acc, entry)
          {
            acc[entry.time] = entry;

            return acc;
          }, {})
      );
    },

    getIndicatorHandler: function (name)
    {
      return this.indicatorManager.handler(name);
    },

    magnifyUpdate: async function ()
    {
      const trade = this.magnifier.trade;
      const interval = this.magnifier.interval;

      if (!trade || !interval)
      {
        return;
      }

      const start = trade.entry.timestamp / 1000;
      const end = Math.max(trade.exit_timestamp, trade.exit.price_date) / 1000;

      const range = {
        start: new Date(start * 1000).toISOString(),
        end: new Date(end * 1000).toISOString()
      }

      const symbol = this.prepareSymbol(await ApiService.candles(this.sel.exchange,
          this.sel.symbol,
          interval,
          null,
          null,
          null,
          null,
          range));

      //TODO separate magnifier container
      const container = this.$refs.chart;
      this.purgeMagnifierCharts();
      this.magnifiedCharts[0] = this.newChart(container, symbol.symbol + symbol.interval);

      const candlestickSeries = this.magnifiedCharts[0].addCandlestickSeries();
      candlestickSeries.setMarkers(this.reduceSeriesData(start, end, this.symbol.markers.trades));
      candlestickSeries.setData(symbol.candles);

      if (trade.log.position)
      {
        const transactions = trade.log.position.transactions.reduce((acc, item) =>
        {
          if (item.timestamp)
          {
            acc.push(this.prepareTransactionMarker(item));
          }
          return acc;
        }, []);

        candlestickSeries.setMarkers(transactions);

        const priceHistory = trade.log.position.price_history;
        const entryColor = 'rgb(255,255,255)';
        const entrySeries = this.magnifiedCharts[0].addLineSeries({
          color: entryColor,
          lineWidth: 1,
          lineType: 1,
        });
        const entryLog = this.prepareMagnifiedPriceLog(priceHistory.entry);
        entrySeries.setData(entryLog.map(item => ({time: item.time, value: item.value})));
        entrySeries.setMarkers(entryLog.map(item => this.preparePriceChangeMarker(item, entryColor)))

        const stopColor = '#ff0000';
        const stopSeries = this.magnifiedCharts[0].addLineSeries({
          color: stopColor,
          lineWidth: 1,
          lineType: 1,
        });
        const stopLog = this.prepareMagnifiedPriceLog(priceHistory.stop);
        stopSeries.setData(stopLog.map(item => ({time: item.time, value: item.value})));
        stopSeries.setMarkers(stopLog.map(item => this.preparePriceChangeMarker(item, stopColor)))

        const exitColor = '#4aff00';
        const exitSeries = this.magnifiedCharts[0].addLineSeries({
          color: exitColor,
          lineWidth: 1,
          lineType: 1,
        });
        const exitLog = this.prepareMagnifiedPriceLog(priceHistory.exit);
        exitSeries.setData(exitLog.map(item => ({time: item.time, value: item.value})));
        exitSeries.setMarkers(exitLog.map(item => this.preparePriceChangeMarker(item, exitColor)))
      }

      const indicators = this.symbol.indicators;

      for (let alias in indicators)
      {
        let handler = this.getIndicatorHandler(indicators[alias].name);
        let magnified = this.reduceSeriesData(start, end, indicators[alias].data); //TODO use recalculated
        let chart = this.magnifiedCharts[0];
        let series = handler.init(magnified, chart, indicators)
        handler.update(series, magnified, indicators)
      }
    },

    magnify: function (trade)
    {
      if (!trade)
      {
        throw Error('trade is undefined.');
      }

      this.magnifier.trade = trade;
    },

    showRange: function (timestampA, timestampB)
    {
      for (let i in this.charts)
      {
        this.charts[i].showRange(timestampA / 1000, timestampB / 1000);
      }
    },

    registerVisibleLogicalRangeChangeEvent: function (charts)
    {
      for (let i in charts)
      {
        charts[i].timeScale().subscribeVisibleLogicalRangeChange(range =>
        {
          for (let j in charts)
          {
            if (j !== i)
            {
              charts[j].timeScale().setVisibleLogicalRange(range);
            }
          }
        });
      }
    },

    registerLazyLoadEvent: function ()
    {
      this.charts[0].timeScale().subscribeVisibleLogicalRangeChange(newVisibleLogicalRange =>
      {
        if (!this.series['candlestick']) return;

        const barsInfo = this.series['candlestick'].barsInLogicalRange(newVisibleLogicalRange);
        if (barsInfo !== null && barsInfo.barsBefore < 50)
        {
          this.lazyLoad();
        }
      });
    },

    registerChartEvents: function ()
    {
      this.registerLazyLoadEvent();
      this.registerVisibleLogicalRangeChangeEvent(this.charts);
    },

    prepareIndicatorData: function (indicators, length)
    {
      for (let alias in indicators)
      {
        const indicator = indicators[alias];
        indicator.data = this.getIndicatorHandler(indicator.name).prepare(indicator.data, length, indicators);
      }
      return indicators;
    },

    initIndicators: function ()
    {
      const indicators = this.symbol.indicators;

      for (let alias in indicators)
      {
        let indicator = indicators[alias];
        if (indicator)
        {
          let data = indicator.data;
          let handler = this.getIndicatorHandler(indicator.name);
          let chart = this.charts[0];
          this.series[alias] = handler.init(data, chart, indicators);
          handler.update(this.series[alias], data, indicators);
        }
      }
    },

    prepareSignalMarker: function (data)
    {
      return {
        time: data.price_date / 1000,
        position: data.side === 'BUY' ? 'belowBar' : 'aboveBar',
        color: data.side === 'BUY' ? '#00ff68' : '#ff0062',
        shape: data.side === 'BUY' ? 'arrowUp' : 'arrowDown',
        text: data.name
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

          markers = this.prepareSignalMarkers(markers, strategy.trades.evaluations);

          this.symbol.markers.trades = markers;
          this.series['candlestick'].setMarkers(markers);
        }
      }
    },

    prepareSignalMarkers: function (markers, evaluations)
    {
      for (let id in evaluations)
      {
        markers.push(this.prepareSignalMarker(evaluations[id]['entry']));
        markers.push(this.prepareSignalMarker(evaluations[id]['exit']));
      }

      return markers.sort((a, b) => (a.time - b.time));
    },

    initBalanceHistoryChart: function (container)
    {
      this.balanceChart = this.newChart(container, 'Balance History');

      container.style.display = 'block';
      this.resize();

      const lineSeries = this.balanceChart.addLineSeries({
        lineType: 0
      });
      const balanceHistory = this.symbol.strategy.trades.summary.balance_history;

      const mapped = [];
      for (let i in balanceHistory)
      {
        mapped.push({
          time: i / 1000,
          value: balanceHistory[i]
        });
      }

      lineSeries.setData(mapped);
    },

    purgeBalanceHistoryChart: function ()
    {
      if (this.balanceChart)
      {
        this.balanceChart.remove();
        this.balanceChart = null;
      }
    },

    purgeCharts: function ()
    {
      this.purgeMainCharts();
      this.purgeMagnifierCharts();
      this.purgeBalanceHistoryChart();
    },

    hasTrades: function ()
    {
      return this.symbol?.strategy?.trades?.evaluations?.length > 0;
    },

    replaceCandlestickChart: async function ()
    {
      if (this.preventLoad)
      {
        this.preventLoad = false;
        return;
      }

      this.resetLimit();

      if (!this.sel.exchange || !this.sel.symbol || !this.sel.interval)
      {
        return;
      }

      this.purgeCharts();

      await this.updateSymbol();

      if (!this.symbol) return;

      if (this.hasTrades())
      {
        this.initBalanceHistoryChart(this.$refs.balanceChart);
        this.magnifier.interval = this.sel.interval;
      }

      const chart = this.createChart(this.$refs.chart, this.sel.exchange + ' • ' + this.sel.symbol + ' • ' + this.sel.interval);
      const candlestickSeries = chart.addCandlestickSeries();
      candlestickSeries.setData(this.symbol.candles);
      this.series['candlestick'] = candlestickSeries;

      this.initIndicators();
      this.initMarkers();
      this.registerChartEvents();
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

      this.indicatorConfig = this.prepareIndicatorConfig(this.symbol?.indicators);

      this.loading = false;

      if (this.useCache)
        this.cache[key] = {symbol: this.symbol, limit: this.limit};
    },

    lazyLoad: async function ()
    {
      if (this.loading || this.limitReached || !this.charts[0] || this.sel.strategy) return;
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
      this.series['candlestick'].setData(await this.symbol.candles);

      const indicators = this.symbol.indicators;
      for (let alias in indicators)
      {
        let indicator = indicators[alias];
        this.getIndicatorHandler(indicator.name).update(this.series[alias], indicator.data, indicators);
      }
    },

    newChart: function (container, name, options = {})
    {
      if (!container) throw Error('Chart container was not found.');

      options.height = container.offsetHeight;
      options.width = container.offsetWidth;

      return new Chart(container, name, options);
    },

    createChart: function (container, name, options = {})
    {
      const chart = this.newChart(container, name, options);
      this.charts.push(chart);

      return chart;
    },

    resize: function ()
    {
      const container = this.$refs.chart;
      const balanceContainer = this.$refs.balanceChart;

      if (this.charts.length)
        for (let i in this.charts)
          this.charts[i].resize(container.offsetWidth, container.offsetHeight);

      if (this.magnifiedCharts.length)
        for (let i in this.magnifiedCharts)
          this.magnifiedCharts[i].resize(container.offsetWidth, container.offsetHeight);

      if (this.balanceChart)
        this.balanceChart.resize(balanceContainer.offsetWidth, balanceContainer.offsetHeight);
    },

    onSelect: function ()
    {
      if (!this.symbols[this.sel.exchange]?.includes(this.sel.symbol))
      {
        this.purgeCharts();
        return;
      }

      this.replaceCandlestickChart();
    },

    purgeMainCharts: function ()
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
          this.indicatorConfig,
          this.limit,
          this.sel.strategy,
          this.range);
    },

    prepareSymbol: function (symbol)
    {
      if (!symbol || !Object.keys(symbol).length) return;

      symbol.volumes = symbol.candles.map(x =>
      {
        return {time: x.t / 100, value: x.v}
      });

      symbol.candles = symbol.candles.map(x =>
      {
        return {time: x.t / 1000, open: x.o, close: x.c, high: x.h, low: x.l}
      });

      symbol.indicators = this.prepareIndicatorData(symbol.indicators, symbol.candles.length)

      symbol.markers = {trades: [], signals: []};

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
    },

    prepareIndicatorConfig: function (indicators)
    {
      const config = {};
      if (indicators)
      {
        for (let i in indicators)
        {
          config[i] = indicators[i].config;
        }
      }
      return config;
    }
  }
}
</script>

<style lang="scss">

html, body, #app, .main-container {
  height: 100%;
}

.chart-container {
  height: 100%;
  margin-top: 10px;
}

.chart {
  height: 50%;
}

.balance-chart {
  height: 20%;
}

.multiselect__single {
  overflow: hidden !important;
}

.anchor {
  position: absolute;
  bottom: 0;
}
</style>