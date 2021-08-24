<template>
  <table class="table table-responsive text-white">
    <thead>
    <tr>
      <th>Side</th>
      <th>Entry</th>
      <th>Exit</th>
      <th>Entry Date</th>
      <th>Exit Date</th>
      <th>Entry Price</th>
      <th>Valid Price</th>
      <th>Exit Price</th>
      <th>Highest Price</th>
      <th>Lowest Price</th>
      <th>ROI</th>
      <th>Highest ROI</th>
      <th>Lowest ROI</th>
    </tr>
    </thead>
    <tbody>
    <tr v-for="trade in paginated" class="bg-opacity-50" v-bind:class="{
        'bg-danger': trade.result.realized_roi < 0,
        'bg-success': trade.result.realized_roi > 0
      }">
      <td>{{ trade.entry.side }}</td>
      <td>{{ trade.entry.name }}</td>
      <td>{{ trade.exit.name }}</td>
      <td v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
        <a href="{{ '#' + chartId }}">{{ timestampToString(trade.entry.timestamp) }}</a>
      </td>
      <td v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
        <a href="{{ '#' + chartId }}">{{ timestampToString(trade.exit.timestamp) }}</a>
      </td>
      <td>{{ trade.entry.price }}</td>
      <td>{{ trade.entry.valid_price ? 'Yes' : 'No' }}</td>
      <td>{{ trade.exit.price }}</td>
      <td>{{ trade.result.highest_price }}</td>
      <td>{{ trade.result.lowest_price }}</td>
      <td>{{ trade.result.realized_roi + '%' }}</td>
      <td>{{ trade.result.highest_roi + '%' }}</td>
      <td>{{ trade.result.lowest_roi + '%' }}</td>
    </tr>
    </tbody>
  </table>
  <pagination v-model="page" :per-page="perPage" :records="trades.length" @paginate="paginate"/>
</template>

<script>

import Pagination from 'v-pagination-3';

export default {
  name: "TradeTable",
  props: ['trades', 'chartId'],
  emits: ['dateClick'],
  components: {Pagination},

  data: function ()
  {
    return {
      paginated: [],
      perPage: 5,
      page: 1
    }
  },

  created()
  {
    this.paginate(this.page);
  },

  methods: {

    dateClick: function (a, b)
    {
      this.$emit('dateClick', a, b);
    },
    paginate: function (page)
    {
      const start = (page - 1) * this.perPage;
      const end = start + this.perPage;
      this.paginated = this.$props.trades.slice(start, end);
    },
    timestampToString: function (timestamp)
    {
      return new Date(timestamp).toISOString().slice(0, 19).replace('T', ' ');
    }
  }
}
</script>

<style scoped>

</style>