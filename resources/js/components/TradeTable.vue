<template>
  <table class="table table-responsive text-white">
    <thead>
    <tr>
      <th>Side</th>
      <th>Entry</th>
      <th>Exit</th>
      <th>Entry Signal</th>
      <th>Entry Date</th>
      <th>Exit Date</th>
      <th>Exit Signal</th>
      <th>Highest Entry</th>
      <th>Lowest Entry</th>
      <th>Entry Price</th>
      <th>Exit Price</th>
      <th>Close Price</th>
      <th>Stop Price</th>
      <!--      <th>Highest Price</th>-->
      <!--      <th>Lowest Price</th>-->
      <th>ROI</th>
      <th>Highest ROI</th>
      <th>Lowest ROI</th>
    </tr>
    </thead>
    <tbody>
    <tr v-for="trade in paginated" class="bg-opacity-50" v-bind:class="{
        'bg-danger': trade.result.realized_roi < 0,
        'bg-success': trade.result.realized_roi > 0,
        'bg-warning' : !trade.result.valid_price,
        'bg-info': trade.result.ambiguous
      }">
      <td>{{ trade.entry.side }}</td>
      <td>{{ trade.entry.name }}</td>
      <td>{{ trade.exit.name }}</td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
          {{ timestampToString(trade.entry.timestamp) }}
        </a>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.result.entry_time, trade.result.close_time)">
          {{ timestampToString(trade.result.entry_time) }}
        </a>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.result.entry_time, trade.result.close_time)">
          {{ timestampToString(trade.result.close_time) }}
        </a>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
          {{ timestampToString(trade.exit.timestamp) }}
        </a>
      </td>
      <td>{{ trade.result.highest_entry || 'None' }}</td>
      <td>{{ trade.result.lowest_entry || 'None' }}</td>
      <td v-bind:class="{ 'text-danger': !trade.result.valid_price }">{{ trade.entry.price }}</td>
      <td>{{ trade.exit.price }}</td>
      <td>
        <p v-bind:class="{'text-warning': trade.result.close }">
          {{ trade.entry.close_price || 'None' }}</p>
      </td>
      <td>
        <p v-bind:class="{'text-warning': trade.result.stop }">
          {{ trade.entry.stop_price || 'None' }}</p>
      </td>
      <!--      <td>{{ trade.result.highest_price }}</td>-->
      <!--      <td>{{ trade.result.lowest_price }}</td>-->
      <td>{{ trade.result.realized_roi + '%' }}</td>
      <td>{{ trade.result.highest_roi + '%' }}</td>
      <td>{{ trade.result.lowest_roi + '%' }}</td>
    </tr>
    </tbody>
  </table>
  <pagination v-model=" page" :per-page="perPage" :records="trades.length" @paginate="paginate"/>
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
      if (a && b)
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
      try
      {
        return new Date(timestamp).toISOString().slice(0, 19).replace('T', ' ');
      } catch (e)
      {
        return 'None';
      }
    }
  }
}
</script>

<style scoped>

</style>