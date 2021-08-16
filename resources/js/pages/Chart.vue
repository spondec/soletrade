<template>
  <main-layout v-bind:title="title">
    <div class=" grid lg:grid-cols-4 md:grid-cols-2 sm:grid-cols-1 gap-4 form-group" v-if="sel.exchange && sel.symbol">
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
    <div class="chart-container" ref="chart">
      <p class="text-lg-center" v-if="!this.charts.length">Requested chart is not available.</p>
    </div>

    <div id="macd"></div>
  </main-layout>
</template>

<script>

import MainLayout from '../layouts/Main';
import {createChart, CrosshairMode} from 'lightweight-charts';

import ApiService from "../services/ApiService";
import VSpinner from "../components/VSpinner";
import VueMultiselect from 'vue-multiselect'

export default {
  title: "Chart",
  components: {VSpinner, MainLayout, VueMultiselect},
  watch:
      {
        sel: {
          handler: 'onSelect',
          deep: true
        }
      },
  data: function ()
  {
    return {
      loading: false,

      cache: [],
      useCache: false,

      exchanges: null,
      symbols: null,
      intervals: null,
      indicators: [],
      title: this.title,

      symbol: null,
      limit: null,
      limitReached: false,

      sel: {
        exchange: null,
        symbol: null,
        interval: null,
        indicators: [],
      },

      candlesPerRequest: 1000,
      isCrossHairMoving: false,

      charts: [],
      series: [],
      seriesOptions: {
        priceFormat: {
          type: 'price',
          precision: 2,
          minMove: 0.0000000001,
        },
      },
      options: {
        width: 400,
        height: 600,

        priceFormat: {
          type: 'price',
          precision: 10,
          minMove: 0.000000001,
        },

        rightPriceScale: {
          visible: true,
          borderColor: 'rgba(197, 203, 206, 1)',
          precision: 10,
          width: 60
        },
        leftPriceScale: {
          visible: true,
          borderColor: 'rgba(197, 203, 206, 1)',
        },
        layout: {
          backgroundColor: 'rgb(0,0,0)',
          textColor: 'white',
        },
        grid: {
          horzLines: {
            color: 'rgb(59,59,59)',
          },
          vertLines: {
            color: 'rgb(59,59,59)',
          },
        },
        crosshair: {
          mode: CrosshairMode.Normal
        },
        timeScale: {
          borderColor: 'rgba(197, 203, 206, 1)',
          timeVisible: true,
        },
        handleScroll: {
          vertTouchDrag: false,
        },
      }
    }
  },

  async created()
  {
    const data = await ApiService.chartData();

    this.exchanges = data.exchanges;
    this.indicators = data.indicators;
    this.symbols = data.symbols;
    this.intervals = data.intervals;

    this.sel.exchange = this.exchanges[Object.keys(this.exchanges)[0]];
    this.sel.symbol = this.symbols[this.sel.exchange][0];
    this.sel.interval = this.intervals[0];
  },

  mounted()
  {
    this.init();
  },

  methods: {
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
        RSI: {
          prepare: (data, length) =>
          {
            data = this.objectMap((val, key) =>
            {
              return {time: key / 1000, value: val}
            }, data);

            this.fillFrontGaps(length, data);

            return data;
          },
          init: (data, container) =>
          {
            const chart = this.createChart(container, this.options);
            const lineSeries = chart.addLineSeries(this.seriesOptions);

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
          }
        },
        MACD: {
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

          init: (data, container) =>
          {
            const chart = this.createChart(container, this.options);
            let series = {};

            series.divergence = chart.addHistogramSeries({
              ...{
                lineWidth: 1,
                // title: 'divergence',
                crosshairMarkerVisible: true,
              }, ...this.seriesOptions
            });

            series.macd = chart.addLineSeries({
              ...{
                color: '#0094ff',
                lineWidth: 1,
                // title: 'macd',
                crosshairMarkerVisible: true
              }, ...this.seriesOptions
            });

            series.signal = chart.addLineSeries({
              ...{
                color: '#ff6a00',
                lineWidth: 1,
                // title: 'signal',
                crosshairMarkerVisible: true,
              }, ...this.seriesOptions
            });

            return series;
          },

          update: (series, data) =>
          {
            for (let i in series)
              series[i].setData(data[i]);
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

    prepareIndicators: function (indicators, length)
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

    initIndicators: function ()
    {
      const container = this.$refs.chart;
      const indicators = this.symbol.indicators;

      const handlers = this.handlers();

      for (let key in handlers)
      {
        if (indicators[key])
        {
          this.series[key] = handlers[key]['init'](indicators[key], container);
          handlers[key]['update'](this.series[key], indicators[key]);
        }
      }
    },

    initSeries: function ()
    {
      const candlestickSeries = this.charts[0].addCandlestickSeries(this.seriesOptions);
      candlestickSeries.setData(this.symbol.candles);

      this.series['candlestick'] = candlestickSeries;

      this.initIndicators();
    },

    replaceCandlestickChart: async function ()
    {
      this.resetLimit();

      if (!this.sel.exchange || !this.sel.symbol || !this.sel.interval)
      {
        return;
      }

      this.removeChart();

      await this.updateSymbol();

      if (!this.symbol) return;

      this.createChart();
      this.initSeries();
      this.registerEvents();

      // series.setMarkers(chartData.markers);
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
      if (this.loading || this.limitReached) return;
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

      const handlers = this.handlers();

      for (let key in handlers)
      {
        if (this.symbol.indicators[key])
          handlers[key]['update'](this.series[key], this.symbol.indicators[key]);
      }
    },

    createChart: function ()
    {
      const container = this.$refs.chart;

      if (!container) throw Error('Chart container was not found.');

      this.options.height = container.offsetHeight;
      this.options.width = container.offsetWidth;

      const chart = createChart(container, this.options)
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
        this.charts[i].resize(container.offsetWidth, container.offsetHeight, false);
    },

    onSelect: function (e)
    {
      this.replaceCandlestickChart();
    },

    removeChart: function ()
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
          this.limit);
    },

    prepareSymbol: function (symbol)
    {
      if (!Object.keys(symbol).length) return;
      symbol.candles = symbol.candles.map(x =>
      {
        return {time: x.t / 1000, open: x.o, close: x.c, high: x.h, low: x.l,}
      });

      symbol.indicators = this.prepareIndicators(symbol.indicators, symbol.candles.length)

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
  height: 25%;
}

.multiselect__single {
  overflow: hidden !important;
}

</style>