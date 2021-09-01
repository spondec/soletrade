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
      <th>Lowest Price</th>
      <th>Lowest Entry</th>
      <th>Highest Entry</th>
      <th>Highest Price</th>
      <th>Entry Price</th>
      <th>Exit Price</th>
      <th>Close Price</th>
      <th>Stop Price</th>
      <!--      <th>Highest Price</th>-->
      <!--      <th>Lowest Price</th>-->
      <th>ROI %</th>
      <th>Highest ROI %</th>
      <th>Lowest ROI %</th>
    </tr>
    </thead>
    <tbody>
    <tr v-for="trade in paginated" class="bg-opacity-50" v-bind:class="{
        'bg-danger': trade.realized_roi < 0,
        'bg-success': trade.realized_roi > 0,
        'bg-warning' : !trade.is_entry_price_valid,
        'bg-info': trade.is_ambiguous
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
           v-on:click="dateClick(trade.entry_timestamp, trade.exit_timestamp)">
          {{ timestampToString(trade.entry_timestamp) }}
        </a>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry_timestamp, trade.exit_timestamp)">
          {{ timestampToString(trade.exit_timestamp) }}
        </a>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
          {{ timestampToString(trade.exit.timestamp) }}
        </a>
      </td>
      <td>{{ trade.lowest_price || 'N/A' }}</td>
      <td>{{ trade.lowest_entry_price || 'N/A' }}</td>
      <td>{{ trade.highest_entry_price || 'N/A' }}</td>
      <td>{{ trade.highest_price || 'N/A' }}</td>
      <td v-bind:class="{ 'text-danger': !trade.is_entry_price_valid }">{{ trade.entry.price }}</td>
      <td>{{ trade.exit.price }}</td>
      <td>
        <p v-bind:class="{'text-warning': trade.is_closed }">
          {{ trade.entry.close_price || 'N/A' }}</p>
      </td>
      <td>
        <p v-bind:class="{'text-warning': trade.is_stopped }">
          {{ trade.entry.stop_price || 'N/A' }}</p>
      </td>
      <!--      <td>{{ trade.highest_price }}</td>-->
      <!--      <td>{{ trade.lowest_price }}</td>-->
      <td>{{ round(trade.realized_roi) || 'N/A' }}</td>
      <td>{{ round(trade.highest_roi) || 'N/A' }}</td>
      <td>{{ round(trade.lowest_roi) || 'N/A' }}</td>
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

    round: function (float)
    {
      return float ? float.toFixed(2) : float;
    },

    timestampToString: function (timestamp)
    {
      try
      {
        return new Date(timestamp).toISOString().slice(0, 19).replace('T', ' ');
      } catch (e)
      {
        return 'N/A';
      }
    }
  }
}
</script>

<style scoped>

</style>