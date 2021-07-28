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
      <p class="text-lg-center" v-if="!this.chart">Requested chart is not available.</p>
    </div>
  </main-layout>
</template>

<script>

import MainLayout from '../layouts/Main';
import {createChart, CrosshairMode, isBusinessDay} from 'lightweight-charts';

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
      exchanges: null,
      symbols: null,
      intervals: null,
      indicators: [],
      title: this.title,

      sel: {
        exchange: null,
        symbol: null,
        interval: null,
        indicators: []
      },


      chart: null,
      series: [],
      options: {
        width: 400,
        height: 600,

        rightPriceScale: {
          visible: true,
          borderColor: 'rgba(197, 203, 206, 1)',
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
          mode: CrosshairMode.Normal,
        },
        timeScale: {
          borderColor: 'rgba(197, 203, 206, 1)',
          timeVisible: true,
          // tickMarkFormatter: (time, tickMarkType, locale) =>
          // {
          //   console.log(time, tickMarkType, locale);
          //   const year = isBusinessDay(time) ? time.year : new Date(time * 1000).getUTCFullYear();
          //   return String(year);
          // },
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

  async mounted()
  {
    this.init();
  },

  methods: {
    onSelect: function (e)
    {
      this.setupNewCandlestickChart();
    },

    mapKeys: function (data)
    {
      for (let key in data)
      {
        this.reassignKey(data[key], 't', 'time');
        this.reassignKey(data[key], 'h', 'high');
        this.reassignKey(data[key], 'l', 'low');
        this.reassignKey(data[key], 'o', 'open');
        this.reassignKey(data[key], 'c', 'close');

        data[key]['time'] /= 1000;
        delete data[key]['v'];
        delete data[key]['symbol_id'];
        delete data[key]['id'];
      }

      return data;
    },

    setupNewCandlestickChart: async function ()
    {
      if (!this.sel.exchange || !this.sel.symbol || !this.sel.interval)
      {
        return;
      }

      if (this.chart)
      {
        this.chart.remove();
        this.series = [];
        this.chart = null;
      }

      const candles = await ApiService.candles(this.sel.exchange, this.sel.symbol, this.sel.interval);

      if (!Object.keys(candles).length)
      {
        return;
      }

      let data = this.mapKeys(candles.data);

      this.createChart();
      const series = this.chart.addCandlestickSeries({priceScaleId: 'right'});
      series.setData(await data);
      // series.setMarkers(chartData.markers);

      this.series.push(series);
    },

    createChart: function ()
    {
      let options = this.options;
      const container = this.$refs.chart;

      options.height = container.offsetHeight;
      options.width = container.offsetWidth;

      this.chart = createChart(container, options);
    },
    reassignKey: function (o, oldKey, newKey)
    {
      delete Object.assign(o, {[newKey]: o[oldKey]})[oldKey];
    },
    removeAllSeries: function ()
    {
      if (this.chart && this.series.length)
      {
        for (let key in this.series)
        {
          this.chart.removeSeries(this.series[key]);
          delete this.series[key];
        }
      }
    },
    resize: function ()
    {
      if (!this.chart)
        return;

      const container = this.$refs.chart;
      this.chart.resize(container.offsetWidth, container.offsetHeight, false);
    },
    init: function ()
    {
      window.addEventListener('resize', this.resize);
    },
    addLineSeries: function (data)
    {
      const lineSeries = this.chart.addLineSeries();
      lineSeries.setData(data);

      this.series.push(lineSeries);
    }
  }
}
</script>

<style lang="scss">

html, body, #app, .container {
  height: 100%;
}

.chart-container {
  height: 35%;
}

.multiselect__single {
  overflow: hidden !important;
}

</style>