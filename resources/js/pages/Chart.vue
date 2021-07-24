<template>
  <main-layout>
      <div class="chart-container grid" ref="chart">
      </div>
  </main-layout>
</template>

<script>

import MainLayout from '../layouts/Main';
import {createChart} from 'lightweight-charts';

export default {
  title: "Chart",
  components: {MainLayout, createChart},

  data: function ()
  {
    return {
      chart: null,
      options: {width: 400, height: 600}
    }
  },

  methods: {
    resizeChart: function ()
    {
      const chart = this.$refs.chart;
      this.chart.resize(chart.offsetWidth, chart.offsetHeight, false);
    },
    initChart: function ()
    {
      this.chart = createChart(this.$refs.chart, this.options);

      this.addLineSeries([
        {time: '2019-04-11', value: 80.01},
        {time: '2019-04-12', value: 96.63},
        {time: '2019-04-13', value: 76.64},
        {time: '2019-04-14', value: 81.89},
        {time: '2019-04-15', value: 74.43},
        {time: '2019-04-16', value: 80.01},
        {time: '2019-04-17', value: 96.63},
        {time: '2019-04-18', value: 76.64},
        {time: '2019-04-19', value: 81.89},
        {time: '2019-04-20', value: 74.43},
      ]);

      window.addEventListener('resize', this.resizeChart);
      this.resizeChart();
    },
    addLineSeries: function (data)
    {
      const lineSeries = this.chart.addLineSeries();
      lineSeries.setData(data);
    }
  },

  mounted()
  {
    this.initChart();
  }
}
</script>

<style lang="scss">

html, body, #app, .container {
  height: 100%;
}

.chart-container {
  height: 30%;
}
</style>